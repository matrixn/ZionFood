<?php

namespace App\Domain\Mail;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

class EmailErrorAlert
{
    private bool $reporting = false;

    /** @var WeakMap<Throwable, array{task: string, exit_code: ?int}> */
    private WeakMap $scheduledFailures;

    public function __construct()
    {
        $this->scheduledFailures = new WeakMap;
    }

    public function scheduledFailure(ScheduledTaskFailed $event): void
    {
        // Only named, application-defined tasks are exposed. Never use the shell command or its arguments.
        $name = $event->task->description;
        $this->scheduledFailures[$event->exception] = [
            'task' => is_string($name) && preg_match('/\A(?:zionfood:)?[a-z][a-z0-9-]{0,63}\z/', $name) ? $name : '(unnamed)',
            'exit_code' => $event->task->exitCode === null ? null : (int) $event->task->exitCode,
        ];
    }

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
            $exception = $exception instanceof Throwable ? $exception : null;
            $type = $exception ? $this->type($exception) : 'Application log';
            $scheduled = $exception ? ($this->scheduledFailures[$exception] ?? null) : null;
            $frames = $this->exceptionDetails($exception);
            $fingerprint = hash('sha256', implode("\n", [$type, $event->level, $scheduled['task'] ?? '', ...$frames, (string) $event->message]));
            $cache = Cache::store(config('email_delivery.cache_store'));
            if (! $cache->add('mail:alert:'.$fingerprint, true, config('email_delivery.alert_dedup_seconds'))) {
                return;
            }
            $generation = app(ErrorAlertCircuit::class)->admit();
            if ($generation === null) {
                return;
            }
            $id = (string) Str::ulid();
            $execution = $this->executionDetails($scheduled);
            $text = implode("\n", [
                'ZionFood — Eroare aplicație / Application error',
                'ID: '.$id,
                'UTC: '.now()->utc()->toIso8601String(),
                'Environment: '.app()->environment(),
                'ZionFood version: '.config('zionfood.version'),
                'Level: '.$event->level,
                'Type: '.$type,
                ...$execution,
                'Stack (no arguments):',
                ...$frames,
                'Raw messages, request data, SQL and command arguments are excluded. Search the mail delivery log for the alert ID and the application log at the UTC time above.',
            ]);
            EmailDeliveryLog::write('alert_created', ['alert_id' => $id, 'exception_class' => $type, ...($scheduled ?? [])]);
            Mail::raw($text, function ($message) use ($id, $generation) {
                $message->to(config('email_delivery.admin_email'))->subject('[ZionFood] Eroare / Error '.$id);
                $message->getSymfonyMessage()->getHeaders()->addTextHeader('X-ZionFood-Error-Alert', $id);
                $message->getSymfonyMessage()->getHeaders()->addTextHeader('X-ZionFood-Alert-Generation', (string) $generation);
            });
        } catch (Throwable) {
            // The original error must still be logged even if cache/queue/mail is unavailable.
        } finally {
            $this->reporting = false;
        }
    }

    private function executionDetails(?array $scheduled): array
    {
        if ($scheduled) {
            return [
                'Execution: Scheduler',
                'Scheduled task: '.$scheduled['task'],
                'Exit code: '.($scheduled['exit_code'] ?? 'unavailable'),
            ];
        }
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return ['Execution: CLI / worker'];
        }
        $request = app(Request::class);
        $route = $request->route()?->uri();

        return [
            'Execution: HTTP',
            ...($route && preg_match('~\A[A-Za-z0-9_/{?}.:-]{1,160}\z~', $route) ? ['Route template: '.$route] : []),
        ];
    }

    private function exceptionDetails(?Throwable $exception): array
    {
        $lines = [];
        for ($depth = 0; $exception && $depth < 3; $depth++, $exception = $exception->getPrevious()) {
            if ($depth > 0) {
                $lines[] = 'Caused by: '.$this->type($exception);
            }
            $lines[] = $this->location($exception->getFile(), $exception->getLine());
            if ($exception instanceof QueryException) {
                $state = $exception->errorInfo[0] ?? null;
                $driverCode = $exception->errorInfo[1] ?? null;
                if (is_string($state) && preg_match('/\A[A-Z0-9]{5}\z/', $state)) {
                    $lines[] = 'SQLSTATE: '.$state;
                }
                if (is_int($driverCode)) {
                    $lines[] = 'Database driver code: '.$driverCode;
                }
            }
            foreach (array_slice($exception->getTrace(), 0, $depth === 0 ? 20 : 5) as $frame) {
                $class = explode('@anonymous', $frame['class'] ?? '', 2)[0];
                $function = explode(':', $frame['function'] ?? '', 2)[0];
                $lines[] = trim($this->location($frame['file'] ?? '', $frame['line'] ?? 0).' '.$class.($frame['type'] ?? '').$function);
            }
        }

        return $lines;
    }

    private function type(Throwable $exception): string
    {
        return explode('@anonymous', get_class($exception), 2)[0];
    }

    private function location(string $file, int $line): string
    {
        $path = str_replace('\\', '/', $file);
        $root = str_replace('\\', '/', base_path()).'/';

        return (str_starts_with($path, $root) ? substr($path, strlen($root)) : basename($path)).':'.$line;
    }
}
