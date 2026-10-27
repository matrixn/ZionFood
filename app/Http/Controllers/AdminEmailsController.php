<?php

namespace App\Http\Controllers;

use App\Domain\Mail\EmailLedger;
use App\Domain\Mail\IndexQueuedEmails;
use App\Domain\Mail\MailSource;
use App\Domain\Mail\ResendQuota;
use App\Models\EmailDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminEmailsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return Inertia::render('admin/Emails', ['initial' => $this->data($request)]);
    }

    public function data(Request $request): array
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $filters = $request->validate(['source' => ['nullable', Rule::in(MailSource::SOURCES)], 'state' => ['nullable', Rule::in(['notification_queued', 'queued', 'processing', 'deferred', ...EmailLedger::TERMINAL])], 'initiator' => 'nullable|in:system,user,guest,unknown', 'mailer' => 'nullable|string|max:60', 'recipient' => 'nullable|email|max:254', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'page' => 'nullable|integer|min:1|max:100000']);
        $query = EmailDelivery::with('initiator:id,name');
        foreach (['source', 'state', 'mailer'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (filled($filters['initiator'] ?? null)) {
            $query->where('initiator_kind', $filters['initiator']);
        }
        if (filled($filters['recipient'] ?? null)) {
            $query->whereJsonContains('recipient_hashes', app(EmailLedger::class)->hashRecipient($filters['recipient']));
        }
        if (filled($filters['from'] ?? null)) {
            $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'], 'Europe/Bucharest')->startOfDay()->utc());
        }
        if (filled($filters['to'] ?? null)) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['to'], 'Europe/Bucharest')->addDay()->startOfDay()->utc());
        }
        $page = $query->orderByDesc('id')->paginate(25);

        return ['messages' => $page->through(fn ($m) => [...$m->only('public_id', 'source', 'state', 'mailer', 'initiator_kind', 'attempts', 'reason', 'provider_email_id', 'created_at', 'available_at', 'accepted_at', 'expires_at'), 'recipients' => $m->recipients, 'subject' => $m->subject, 'initiator_name' => $m->initiator?->name]), 'summary' => EmailDelivery::select('state', DB::raw('count(*) as total'))->groupBy('state')->pluck('total', 'state'), 'provider' => app(ResendQuota::class)->status(), 'mailers' => EmailDelivery::whereNotNull('mailer')->distinct()->pluck('mailer'), 'application_limit' => config('email_delivery.limit'), 'filters' => $filters];
    }

    public function configure(Request $request, ResendQuota $quota)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $data = $request->validate(['daily_limit' => 'required|integer|min:1|max:100000', 'revision' => 'required|integer|min:1']);
        $quota->configure($data['daily_limit'], $data['revision']);

        return response()->json(['provider' => $quota->status()]);
    }

    public function usage(Request $request, ResendQuota $quota)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $quota->syncUsage();

        return response()->json(['provider' => $quota->status()]);
    }

    public function scan(Request $request, IndexQueuedEmails $index)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return response()->json($index->execute());
    }
}
