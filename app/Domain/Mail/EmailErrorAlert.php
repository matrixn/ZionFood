<?php

namespace App\Domain\Mail;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class EmailErrorAlert
{
    private bool $reporting = false;

    public function handle(MessageLogged $event): void
    {
        if (($event->context['mail_delivery'] ?? false) || $this->reporting || ! config('email_delivery.alerts_enabled') ||
            ! in_array($event->level, ['error', 'critical', 'alert', 'emergency'], true) ||
            ! filter_var(config('email_delivery.admin_email'), FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $this->reporting = true;
        try {
            $exception = $event->context['exception'] ?? null;
            $frames = [];
            if ($exception instanceof Throwable) {
                $frames[] = $this->location($exception->getFile(), $exception->getLine());
                foreach (array_slice($exception->getTrace(), 0, 20) as $frame) {
                    $frames[] = $this->location($frame['file'] ?? '', $frame['line'] ?? 0).' '.($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '');
                }
            }
            $type = $exception instanceof Throwable ? get_class($exception) : 'Application log';
            $fingerprint = hash('sha256', $type.'|'.$event->level.'|'.implode('\n', $frames).'|'.(string) $event->message);
            $cache = Cache::store(config('email_delivery.cache_store'));
            if (! $cache->add('mail:alert:'.$fingerprint, true, config('email_delivery.alert_dedup_seconds'))) {
                return;
            }
            $id = (string) Str::ulid();
            $text = implode("\n", [
                'ZionFood — Eroare aplicație / Application error',
                'ID: '.$id,
                'UTC: '.now()->utc()->toIso8601String(),
                'Environment: '.app()->environment(),
                'Level: '.$event->level,
                'Type: '.$type,
                'Stack (no arguments):',
                ...$frames,
                'Raw messages, request data and context are excluded. Inspect the server logs at this time for full diagnostics.',
            ]);
            Mail::raw($text, function ($message) use ($id) {
                $message->to(config('email_delivery.admin_email'))->subject('[ZionFood] Eroare / Error '.$id);
                $message->getSymfonyMessage()->getHeaders()->addTextHeader('X-ZionFood-Error-Alert', $id);
            });
        } catch (Throwable) {
            // The original error must still be logged even if cache/queue/mail is unavailable.
        } finally {
            $this->reporting = false;
        }
    }

    private function location(string $file, int $line): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).':'.$line;
    }
}
