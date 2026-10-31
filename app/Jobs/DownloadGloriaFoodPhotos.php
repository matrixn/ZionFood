<?php

namespace App\Jobs;

use App\Domain\Imports\StageGloriaFoodPhotos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class DownloadGloriaFoodPhotos implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $batchId) {}

    public function handle(): void
    {
        if (app(StageGloriaFoodPhotos::class)->execute($this->batchId)) {
            self::enqueue($this->batchId);
        }
    }

    public static function enqueue(int $batchId): void
    {
        try {
            Bus::dispatch((new self($batchId))->afterCommit());
        } catch (\Throwable $e) {
            (new self($batchId))->failed($e);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        app(StageGloriaFoodPhotos::class)->execute($this->batchId, true);
        Log::warning('GloriaFood photo staging stopped', ['batch_id' => $this->batchId, 'exception_class' => $exception ? $exception::class : null]);
    }
}
