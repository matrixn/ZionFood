<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Throwable;

class MailStatus extends Command
{
    protected $signature = 'zionfood:mail-status';

    protected $description = 'Inspect mail configuration, queue, quota and active Horizon workers without sending email';

    public function handle(): int
    {
        $healthy = true;
        $mailer = config('mail.default');
        $this->line('Environment: '.app()->environment());
        $this->line('Mailer: '.$mailer);
        $this->line('Configuration cached: '.(app()->configurationIsCached() ? 'yes' : 'no'));
        if (in_array($mailer, ['log', 'array'], true) || ($mailer === 'resend' && blank(config('services.resend.key')))) {
            $this->error('Delivery mailer is disabled or its API key is missing.');
            $healthy = false;
        } else {
            $this->line('Mailer credentials: configured (values hidden)');
        }
        $connection = config('email_delivery.queue_connection');
        $driver = config('queue.connections.'.$connection.'.driver');
        $queueName = config('queue.connections.'.$connection.'.queue', 'default');
        $this->line('Mail queue: '.$connection.' / '.$queueName);
        try {
            $queue = Queue::connection($connection);
            $this->line('Queue size (all job types): '.$queue->size($queueName));
            if ($driver === 'redis') {
                $this->line('Delayed: '.$queue->delayedSize($queueName).'; reserved: '.$queue->reservedSize($queueName));
                $supervisors = app(SupervisorRepository::class)->all();
                $running = collect($supervisors)->filter(fn ($supervisor) => $supervisor->status === 'running' &&
                    ($supervisor->options['connection'] ?? null) === $connection &&
                    in_array($queueName, explode(',', $supervisor->options['queue'] ?? ''), true) &&
                    ($supervisor->processes[$connection.':'.$queueName] ?? 0) > 0)->count();
                $this->line('Running Horizon supervisors: '.$running);
                if ($running === 0) {
                    $this->error('No active Horizon supervisors. A running master alone does not process jobs. Check the Horizon environment plan and process monitor.');
                    $healthy = false;
                }
            } else {
                $this->warn('Horizon only handles Redis. Verify the separate worker for this connection.');
            }
            $slots = Cache::store(config('email_delivery.cache_store'))->get('mail:quota:slots', []);
            $used = count(array_filter($slots, fn ($time) => $time > now()->timestamp - config('email_delivery.window_seconds')));
            $this->line('Recipient quota used in the last 24 hours: '.$used.'/'.config('email_delivery.limit'));
        } catch (Throwable) {
            $this->error('Queue/cache/Horizon inspection failed. Check Redis connectivity and configuration.');
            $healthy = false;
        }
        $this->line('Delivery logs: storage/logs/mail-delivery-YYYY-MM-DD.log and the PHP process error log.');

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
