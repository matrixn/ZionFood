<?php

namespace App\Domain\Platform\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BuildPlatformOverview
{
    public function execute(User $actor, int $days): array
    {
        abort_unless($actor->is_platform_admin, 403);
        abort_unless(in_array($days, [7, 30, 90], true), 422);
        $now = CarbonImmutable::now('UTC');
        $start = $now->startOfDay()->subDays($days - 1);
        $until = $now->addDay()->startOfDay();
        $period = fn (string $table, string $column = 'created_at') => DB::table($table)->where($column, '>=', $start)->where($column, '<', $until);
        $completed = $period('orders')->where('status', 'completed');
        $series = [];
        foreach (['orders', 'service_leads', 'campaign_visits'] as $table) {
            $query = $period($table);
            if ($table === 'orders') {
                $query->where('status', 'completed');
            }
            $series[$table] = $query->selectRaw('DATE(created_at) as day, COUNT(*) as count')->groupByRaw('DATE(created_at)')->pluck('count', 'day');
        }
        $timeline = [];
        for ($day = $start; $day->lt($until); $day = $day->addDay()) {
            $date = $day->toDateString();
            $timeline[] = ['day' => $date, 'orders' => (int) ($series['orders'][$date] ?? 0), 'leads' => (int) ($series['service_leads'][$date] ?? 0), 'visits' => (int) ($series['campaign_visits'][$date] ?? 0)];
        }

        return [
            'days' => $days, 'from' => $start->toDateString(), 'to' => $now->toDateString(), 'updated_at' => $now->toIso8601String(), 'timezone' => 'UTC',
            'totals' => [
                'organizations' => DB::table('organizations')->count(), 'restaurants' => DB::table('restaurants')->count(),
                'locations' => DB::table('restaurant_locations')->count(), 'users' => DB::table('users')->count(),
                'trials' => DB::table('organization_subscriptions')->where('status', 'trial')->where('trial_ends_at', '>', $now)->count(),
                'expiring' => DB::table('organization_subscriptions')->where('status', 'trial')->where('trial_ends_at', '>', $now)->where('trial_ends_at', '<=', $now->addDays(7))->count(),
                'completed' => (clone $completed)->count(), 'leads' => $period('service_leads')->count(),
                'visits' => $period('campaign_visits')->count(), 'new_leads' => DB::table('service_leads')->where('status', 'new')->count(),
                'reservations' => $period('reservations', 'starts_at')->whereNotIn('status', ['cancelled', 'rejected', 'expired'])->count(),
                'active_orders' => DB::table('orders')->whereNotIn('status', ['completed', 'cancelled', 'rejected'])->count(),
            ],
            'values' => (clone $completed)->selectRaw('currency, SUM(total) as value, COUNT(*) as count')->groupBy('currency')->orderBy('currency')->get()->map(fn ($row) => ['currency' => $row->currency, 'value' => (int) $row->value, 'count' => (int) $row->count]),
            'timeline' => $timeline,
            'attention' => $this->attention(),
            'recent' => DB::table('organizations')->select('public_id', 'name', 'created_at')->orderByDesc('id')->limit(5)->get(),
            'subscriptions' => DB::table('organization_subscriptions as s')->join('organizations as o', 'o.id', '=', 's.organization_id')->where('s.status', 'trial')->where('s.trial_ends_at', '>', $now)->where('s.trial_ends_at', '<=', $now->addDays(7))->select('o.public_id', 'o.name', 's.trial_ends_at')->orderBy('s.trial_ends_at')->limit(5)->get(),
        ];
    }

    public function attention(): array
    {
        return [
            'imports' => DB::table('import_batches')->where('status', 'failed')->count(),
            'webhooks' => DB::table('webhook_deliveries')->whereIn('status', ['failed', 'blocked'])->count(),
            'outbox' => DB::table('outbox_messages')->whereNotNull('failed_at')->count(),
            'emails' => DB::table('email_deliveries')->where('state', 'failed')->count(),
            'deferred_emails' => DB::table('email_deliveries')->where('state', 'deferred')->count(),
            'ai' => DB::table('menu_content_drafts')->where('status', 'failed')->count(),
        ];
    }
}
