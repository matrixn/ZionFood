<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\TenantAccess;
use App\Models\WorkspaceNotification;
use Illuminate\Http\Request;

class WorkspaceNotificationController extends Controller
{
    private function visible(Request $request, WorkspaceNotification $notification): bool
    {
        $draft = $notification->draft;
        if (! $draft || ! $draft->location || ! $draft->item) {
            return false;
        }
        $location = $draft->location;
        $item = $draft->item;
        $access = app(TenantAccess::class);

        return $draft->organization_id === $location->organization_id && $item->organization_id === $location->organization_id
            && $access->canFeature($request->user(), $location, 'menu')
            && $location->menus()->where('menus.id', $item->category->menu_id)->exists()
            && $access->canMenu($request->user(), $location->organization_id, $item->category->menu_id, false);
    }

    public function index(Request $request)
    {
        $records = WorkspaceNotification::where('user_id', $request->user()->id)->with('draft.location', 'draft.item.category')->latest()->limit(100)->get();

        return response()->json(['notifications' => $records->filter(fn ($row) => $this->visible($request, $row))->map(fn ($row) => [
            'public_id' => $row->public_id, 'read_at' => $row->read_at, 'created_at' => $row->created_at,
            'draft' => $row->draft->public_id, 'status' => $row->draft->status, 'kind' => $row->draft->kind,
            'location' => $row->draft->location->public_id, 'item_name' => $row->draft->item->name,
        ])->values()]);
    }

    public function read(Request $request, string $notification)
    {
        $model = WorkspaceNotification::where('public_id', $notification)->where('user_id', $request->user()->id)->with('draft.location', 'draft.item.category')->firstOrFail();
        abort_unless($this->visible($request, $model), 404);
        $model->update(['read_at' => now()]);

        return response()->json(['read' => true]);
    }
}
