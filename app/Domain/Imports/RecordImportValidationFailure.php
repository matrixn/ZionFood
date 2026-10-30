<?php

namespace App\Domain\Imports;

use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordImportValidationFailure
{
    public function execute(ImportBatch $batch, ValidationException $exception): array
    {
        $allowed = ['id', 'restaurant_id', 'currency', 'categories', 'items', 'name', 'description', 'price', 'sizes', 'default', 'groups', 'extras', 'tags', 'required', 'allow_quantity', 'force_min', 'force_max', 'options', 'source'];
        $details = [];
        foreach (array_slice($exception->validator->failed(), 0, 20, true) as $path => $rules) {
            $safePath = implode('.', array_map(fn ($part) => in_array($part, $allowed, true) || (ctype_digit($part) && strlen($part) <= 6) ? $part : '*', explode('.', (string) $path)));
            $safeRules = [];
            foreach ($rules as $rule => $parameters) {
                if (! in_array($rule, ['Required', 'Present', 'Integer', 'String', 'Array', 'Numeric', 'Boolean', 'Distinct', 'Min', 'Max', 'Size'], true)) {
                    $rule = 'Invalid';
                }
                $limits = in_array($rule, ['Min', 'Max', 'Size'], true) ? array_filter($parameters, fn ($value) => is_numeric($value) && $value >= 0 && $value <= 10000000) : [];
                $safeRules[] = strtolower($rule).($limits ? ':'.implode(',', $limits) : '');
            }
            $details[] = ['path' => substr($safePath, 0, 200), 'rules' => array_slice($safeRules, 0, 10)];
        }
        DB::transaction(function () use ($batch, $details) {
            DB::table('import_issues')->where('batch_id', $batch->id)->delete();
            foreach ($details as $detail) {
                DB::table('import_issues')->insert(['batch_id' => $batch->id, 'severity' => 'blocker', 'code' => 'source_validation', 'path' => $detail['path'], 'message' => implode('; ', $detail['rules']), 'created_at' => now(), 'updated_at' => now()]);
            }
            $batch->update(['status' => 'failed', 'failure_code' => 'source_validation_failed', 'credential' => null]);
        });

        return $details;
    }
}
