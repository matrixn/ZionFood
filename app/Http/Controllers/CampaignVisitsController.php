<?php

namespace App\Http\Controllers;

use App\Models\CampaignVisit;
use App\Models\ServiceLead;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CampaignVisitsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $filters = $request->validate(['days' => ['nullable', Rule::in([7, 30, 90])], 'utm_source' => 'nullable|string|max:200', 'utm_medium' => 'nullable|string|max:200', 'utm_campaign' => 'nullable|string|max:200', 'page' => 'nullable|integer|min:1|max:100000']);
        $filters['days'] = (int) ($filters['days'] ?? 30);
        foreach (['utm_source', 'utm_medium'] as $field) {
            if (is_string($filters[$field] ?? null)) {
                $filters[$field] = mb_strtolower(trim($filters[$field]));
            }
        }
        $start = CarbonImmutable::now('Europe/Bucharest')->startOfDay()->subDays($filters['days'] - 1)->utc();
        $end = CarbonImmutable::now('Europe/Bucharest')->startOfDay()->addDay()->utc();
        $query = CampaignVisit::where('created_at', '>=', $start)->where('created_at', '<', $end);
        $options = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $field) {
            $options[$field] = (clone $query)->whereNotNull($field)->selectRaw('min('.$field.') as value')->groupByRaw('hex('.$field.')')->orderBy('value')->limit(200)->pluck('value');
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field])->whereRaw('hex('.$field.') = ?', [strtoupper(bin2hex($filters[$field]))]);
            }
        }
        $ids = (clone $query)->select('id');
        $summary = ['visits' => (clone $query)->count(), 'campaigns' => (clone $query)->distinct()->count('campaign_key'), 'leads' => ServiceLead::whereIn('campaign_visit_id', clone $ids)->count(), 'qualified' => ServiceLead::where('status', 'qualified')->whereIn('campaign_visit_id', clone $ids)->count()];
        $leadCounts = ServiceLead::select('campaign_visit_id', DB::raw('count(*) as attributed_leads'))->whereNotNull('campaign_visit_id')->groupBy('campaign_visit_id');
        $columns = array_map(fn ($field) => 'campaign_visits.'.$field, CampaignVisit::GROUP_FIELDS);
        $top = (clone $query)->leftJoinSub($leadCounts, 'conversions', fn ($join) => $join->on('conversions.campaign_visit_id', '=', 'campaign_visits.id'))
            ->select($columns)->selectRaw('count(*) as visits, coalesce(sum(conversions.attributed_leads), 0) as leads')->groupBy('campaign_key', ...$columns)->orderByDesc('leads')->orderByDesc('visits')->limit(10)->get()
            ->map(fn ($row) => [...$row->only(CampaignVisit::GROUP_FIELDS), 'visits' => (int) $row->visits, 'leads' => (int) $row->leads]);
        $visits = $query->withCount('leads')->latest('id')->paginate(25)->through(fn ($visit) => $visit->only(['public_id', 'created_at', 'landing_path', 'locale', 'leads_count', ...CampaignVisit::FIELDS]));
        Inertia::encryptHistory();

        return Inertia::render('admin/Campaigns', ['visits' => $visits, 'summary' => $summary, 'topCampaigns' => $top, 'filters' => $filters, 'options' => $options])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
