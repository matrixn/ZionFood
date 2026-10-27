<?php

namespace App\Domain\Mail;

use App\Jobs\DeliverEmail;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class IndexQueuedEmails
{
    public function payload(string $raw): bool
    {
        try {
            if (strlen($raw) > 16 * 1024 * 1024) {
                return false;
            }
            $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            $class = $payload['data']['commandName'] ?? null;
            if (! in_array($class, [DeliverEmail::class, SendQueuedNotifications::class], true)) {
                return false;
            }
            // Only authenticated encrypted application mail jobs may be deserialized.
            $serialized = app('encrypter')->decrypt($payload['data']['command']);
            $job = unserialize($serialized);
            if ($job instanceof DeliverEmail && filled($job->deliveryId)) {
                app(EmailLedger::class)->message($job->deliveryId, $job->email, $job->mailer, legacy: true);

                return true;
            }
            if ($job instanceof SendQueuedNotifications) {
                app(EmailLedger::class)->queuedNotification($job, true);

                return true;
            }
        } catch (Throwable) {
            // No raw payload, MIME content or decrypt failure text may enter the logs.
        }

        return false;
    }

    public function execute(int $limit = 10000): array
    {
        $count = 0;
        $indexed = 0;
        $truncated = false;
        foreach (array_unique([config('email_delivery.queue_connection'), config('queue.default')]) as $name) {
            $config = config('queue.connections.'.$name, []);
            $driver = $config['driver'] ?? null;
            if (! in_array($driver, ['database', 'redis'], true)) {
                continue;
            }
            $queue = $config['queue'] ?? 'default';
            if ($driver === 'database') {
                $query = DB::connection($config['connection'] ?? null)->table($config['table'] ?? 'jobs')->where('queue', $queue)->orderBy('id');
                foreach ($query->cursor() as $row) {
                    if ($count >= $limit) {
                        $truncated = true;
                        break 2;
                    }
                    $count++;
                    $indexed += (int) $this->payload($row->payload);
                }
            } else {
                $connection = Queue::connection($name);
                $redis = $connection->getConnection();
                $key = $connection->getQueue($queue);
                foreach (['', ':delayed', ':reserved'] as $suffix) {
                    for ($offset = 0; ; $offset += 200) {
                        $batch = $suffix === '' ? $redis->lrange($key, $offset, $offset + 199) : $redis->zrange($key.$suffix, $offset, $offset + 199);
                        foreach ($batch as $raw) {
                            if ($count >= $limit) {
                                $truncated = true;
                                break 4;
                            }
                            $count++;
                            $indexed += (int) $this->payload($raw);
                        }
                        if (count($batch) < 200) {
                            break;
                        }
                    }
                }
            }
        }

        return ['scanned' => $count, 'indexed' => $indexed, 'truncated' => $truncated];
    }
}
