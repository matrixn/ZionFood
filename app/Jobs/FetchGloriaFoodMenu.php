<?php

namespace App\Jobs;

use App\Domain\Imports\GloriaFoodClient;
use App\Domain\Imports\ImportComplexityException;
use App\Domain\Imports\ProcessImport;
use App\Domain\Imports\RecordImportValidationFailure;
use App\Models\ImportBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FetchGloriaFoodMenu implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $batchId) {}

    public function handle(): void
    {
        $batch = ImportBatch::find($this->batchId);
        if (! $batch || $batch->status !== 'queued' || ! $batch->credential) {
            return;
        }
        $stage = 'fetch';
        try {
            $source = app(GloriaFoodClient::class)->fetch($batch->credential);
            $stage = 'validation';
            app(ProcessImport::class)->execute($batch, $source);
        } catch (ImportComplexityException) {
            $batch->update(['status' => 'failed', 'failure_code' => 'import_complexity_limit']);
        } catch (ValidationException $e) {
            $details = app(RecordImportValidationFailure::class)->execute($batch, $e);
            Log::warning('GloriaFood import validation failed', ['batch_id' => $batch->public_id, 'exception_class' => $e::class, 'stage' => $stage, 'validation_failures' => $details]);
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'failure_code' => 'fetch_or_validation_failed']);
            Log::warning('GloriaFood import failed', ['batch_id' => $batch->public_id, 'exception_class' => $e::class, 'stage' => $stage]);
        } finally {
            $batch->update(['credential' => null]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        ImportBatch::whereKey($this->batchId)->update(['credential' => null, 'status' => 'failed', 'failure_code' => 'worker_failed']);
    }
}
