<?php

namespace App\Http\Controllers;

use App\Domain\Imports\ProcessImport;
use App\Domain\Imports\PublishImport;
use App\Domain\Menus\MenuContent;
use App\Domain\Tenancy\TenantAccess;
use App\Jobs\FetchGloriaFoodMenu;
use App\Models\ImportBatch;
use App\Models\RestaurantLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MigrationCenterController extends Controller
{
    private function location(Request $r, string $id): RestaurantLocation
    {
        $l = RestaurantLocation::where('public_id', $id)->firstOrFail();
        app(TenantAccess::class)->requireManage($r->user(), $l->organization_id);
        app(TenantAccess::class)->requireFeature($r->user(), $l, 'menu', true);

        return $l;
    }

    private function batch(Request $r, string $location, string $id): ImportBatch
    {
        $l = $this->location($r, $location);

        return ImportBatch::where('public_id', $id)->where('location_id', $l->id)->where('organization_id', $l->organization_id)->firstOrFail();
    }

    public function index(Request $r, string $location)
    {
        $l = $this->location($r, $location);

        return Inertia::render('restaurant/MigrationCenter', ['location' => $l->load('restaurant', 'hours', 'fulfillment', 'zones'), 'locations' => app(WorkspaceController::class)->locations($r), 'canManage' => true, 'permissions' => app(TenantAccess::class)->permissions($r->user(), $l->organization_id), 'batches' => ImportBatch::where('location_id', $l->id)->latest()->limit(30)->get(['public_id', 'status', 'source_restaurant', 'created_at'])]);
    }

    public function start(Request $r, string $location)
    {
        $l = $this->location($r, $location);
        $data = $r->validate(['source' => 'required|in:api,file', 'key' => 'required_if:source,api|nullable|string|min:5|max:512|regex:/^[^\r\n]+$/', 'file' => 'required_if:source,file|nullable|file|max:5120', 'authorized' => 'required|accepted', 'size_prices' => ['required', Rule::in(['base_plus_size', 'absolute'])], 'nutrition_basis' => ['required', Rule::in(['per_100g', 'per_100ml', 'per_serving'])], 'tax_basis_points' => 'required|integer|min:0|max:10000']);
        $batch = ImportBatch::create(['organization_id' => $l->organization_id, 'location_id' => $l->id, 'user_id' => $r->user()->id, 'settings' => array_intersect_key($data, array_flip(['size_prices', 'nutrition_basis', 'tax_basis_points'])), 'credential' => $data['source'] === 'api' ? $data['key'] : null]);
        if ($data['source'] === 'api') {
            try {
                Bus::dispatch((new FetchGloriaFoodMenu($batch->id))->afterCommit());
            } catch (\Throwable $e) {
                $batch->update(['credential' => null, 'status' => 'failed', 'failure_code' => 'queue_unavailable']);
                Log::warning('GloriaFood import queue unavailable', ['batch_id' => $batch->public_id, 'exception_class' => $e::class]);
            }
        } else {
            try {
                $source = json_decode($r->file('file')->get(), true, 64, JSON_THROW_ON_ERROR);
                if (! is_array($source)) {
                    throw new \RuntimeException;
                }app(ProcessImport::class)->execute($batch, $source);
            } catch (\Throwable) {
                $batch->update(['status' => 'failed', 'failure_code' => 'invalid_source_file']);
            }
        }

        return response()->json(['batch_id' => $batch->public_id], 201);
    }

    public function detail(Request $r, string $location, string $batch)
    {
        $b = $this->batch($r, $location, $batch);

        return response()->json($this->report($b));
    }

    private function report(ImportBatch $b): array
    {
        $base = ['batch_id' => $b->public_id, 'status' => $b->status, 'failure_code' => $b->failure_code, 'report' => null, 'menu' => null, 'issues' => [], 'conflicts' => [], 'review_hash' => null];
        if (! in_array($b->status, ['ready', 'published', 'reverted'], true)) {
            return $base;
        }

        return [...$base, 'report' => json_decode(DB::table('import_reports')->where('batch_id', $b->id)->value('report'), true), 'menu' => app(ProcessImport::class)->canonical($b), 'issues' => DB::table('import_issues')->where('batch_id', $b->id)->get(['severity', 'code', 'path', 'message']), 'conflicts' => $b->status === 'ready' ? app(PublishImport::class)->conflicts($b) : [], 'review_hash' => $b->status === 'ready' ? app(PublishImport::class)->reviewHash($b) : null, 'publication' => DB::table('import_publications')->where('batch_id', $b->id)->first(['approved_by', 'created_at', 'reverted_at'])];
    }

    public function resolve(Request $r, string $location, string $batch)
    {
        $b = $this->batch($r, $location, $batch);
        $data = $r->validate(['nutrition_map' => 'required|array|max:50', 'nutrition_map.*' => ['required', Rule::in(MenuContent::NUTRIENTS)]]);
        DB::transaction(function () use ($b, $data) {
            $locked = ImportBatch::whereKey($b->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'ready', 409);
            $locked->update(['settings' => [...$locked->settings, 'nutrition_map' => $data['nutrition_map']]]);
            $source = json_decode(Crypt::decryptString(DB::table('import_snapshots')->where('batch_id', $b->id)->value('source')), true, 64, JSON_THROW_ON_ERROR);
            app(ProcessImport::class)->execute($locked, $source);
        });

        return response()->noContent();
    }

    public function printable(Request $r, string $location, string $batch)
    {
        return response()->view('migration-report', ['data' => $this->report($this->batch($r, $location, $batch))])->header('Cache-Control', 'private, no-store');
    }

    public function download(Request $r, string $location, string $batch)
    {
        $b = $this->batch($r, $location, $batch);

        return response()->json($this->report($b))->header('Content-Disposition', 'attachment; filename="migration-'.$b->public_id.'.json"');
    }

    public function publish(Request $r, string $location, string $batch, PublishImport $action)
    {
        $b = $this->batch($r, $location, $batch);
        $d = $r->validate(['acknowledged' => 'required|accepted', 'resolutions' => 'present|array|max:2000', 'resolutions.*' => 'in:keep_local,use_source', 'review_hash' => 'required|string|size:64']);
        $menu = $action->execute($r->user(), $b, $d['resolutions'], true, $d['review_hash']);

        return response()->json(['menu_id' => $menu->public_id]);
    }

    public function revert(Request $r, string $location, string $batch, PublishImport $action)
    {
        $action->revert($r->user(), $this->batch($r, $location, $batch));

        return response()->noContent();
    }
}
