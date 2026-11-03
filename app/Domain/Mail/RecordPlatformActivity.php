<?php

namespace App\Domain\Mail;

use App\Jobs\QueuePlatformActivityEmail;
use App\Models\EmailDelivery;
use App\Models\Organization;
use App\Models\PlatformActivityEmail;
use App\Models\RestaurantLocation;
use App\Models\ServiceLead;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordPlatformActivity
{
    public const SOURCES = ['platform_contact_request', 'platform_account_registered', 'platform_email_verified', 'platform_restaurant_created'];

    public function contact(ServiceLead $lead): void
    {
        $details = ['name' => $lead->name, 'email' => $lead->email, 'phone' => $lead->phone, 'language' => $lead->locale, 'reference' => $lead->public_id];
        foreach ($lead->campaignVisit?->only('utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content') ?? [] as $key => $value) {
            if (filled($value)) {
                $details[$key] = $value;
            }
        }
        $this->record(self::SOURCES[0], 'lead', $lead->id, $details, '/admin/leads');
    }

    public function registered(User $user, bool $invited): void
    {
        $this->record(self::SOURCES[1], 'user', $user->id, [...$this->account($user), 'account_kind' => $invited ? 'invited_staff' : 'new_owner'], '/admin/users');
    }

    public function verified(Verified $event): void
    {
        $user = $event->user;
        if ($user instanceof User && $user->hasVerifiedEmail()) {
            $this->record(self::SOURCES[2], 'user', $user->id, $this->account($user), '/admin/users', hash('sha256', strtolower($user->email)));
        }
    }

    public function restaurant(User $owner, RestaurantLocation $location): void
    {
        $organization = Organization::findOrFail($location->organization_id);
        $this->record(self::SOURCES[3], 'restaurant', $location->restaurant_id, [
            ...$this->account($owner), 'restaurant' => $location->restaurant->name,
            'organization' => $organization->name, 'location' => $location->name,
            'address' => $location->address, 'reference' => $location->public_id,
        ], '/admin/organizations/'.$organization->public_id);
    }

    private function account(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'language' => $user->preferredLocale()];
    }

    private function record(string $source, string $type, int $id, array $details, string $path, string $identity = ''): void
    {
        // The caller's transaction owns both the business change and this durable intent.
        User::where('is_platform_admin', true)->whereNotNull('email_verified_at')->orderBy('id')->chunkById(100, function ($admins) use ($source, $type, $id, $details, $path, $identity) {
            foreach ($admins as $admin) {
                if (! filter_var($admin->email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                DB::transaction(function () use ($admin, $source, $type, $id, $details, $path, $identity) {
                    $notice = PlatformActivityEmail::firstOrCreate([
                        'event_key' => hash('sha256', $source.':'.$type.':'.$id.':'.$identity), 'recipient_id' => $admin->id,
                    ], [
                        'public_id' => (string) Str::ulid(), 'source' => $source, 'subject_type' => $type,
                        'subject_id' => $id, 'details' => ['fields' => $details, 'path' => $path],
                    ]);
                    if (! $notice->wasRecentlyCreated) {
                        return;
                    }
                    EmailDelivery::create([
                        'public_id' => $notice->public_id, 'notification_id' => $notice->public_id,
                        'source' => $source, 'state' => 'notification_queued',
                        'recipients' => ['to' => [$admin->email], 'cc' => [], 'bcc' => []],
                        'recipient_hashes' => [app(EmailLedger::class)->hashRecipient($admin->email)],
                        'context' => ['platform_activity_id' => $notice->id], 'expires_at' => now()->addDays(30),
                        ...app(MailSource::class)->initiator($source),
                    ]);
                    DB::afterCommit(fn () => $this->enqueue($notice->id));
                });
            }
        });
    }

    public function enqueue(int $id): bool
    {
        try {
            QueuePlatformActivityEmail::dispatch($id)->onConnection(config('email_delivery.queue_connection'));

            return true;
        } catch (\Throwable $error) {
            // Intent remains pending; the scheduler retries without failing the public operation.
            EmailDeliveryLog::write('platform_activity_queue_failed', ['activity_id' => $id, 'exception_class' => get_class($error)], 'warning');

            return false;
        }
    }
}
