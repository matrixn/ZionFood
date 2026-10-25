<?php

use App\Jobs\DeliverOutbox;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('zionfood:outbox', function () {
    OutboxMessage::whereNull('delivered_at')->orderBy('id')->chunkById(100, function ($messages) {
        foreach ($messages as $message) {
            DeliverOutbox::dispatch($message->id);
        }
    });
    $this->info('Undelivered events queued.');
});
Schedule::command('zionfood:outbox')->everyMinute()->withoutOverlapping();
Artisan::command('zionfood:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Register the account first.');

        return 1;
    }
    $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => now()])->save();
    $this->info('Platform administrator access granted.');
});
