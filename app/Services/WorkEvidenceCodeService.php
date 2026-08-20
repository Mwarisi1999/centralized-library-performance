<?php

namespace App\Services;

use App\Models\WorkEvidence;
use Closure;
use Illuminate\Support\Facades\Cache;

class WorkEvidenceCodeService
{
    public function withNextCode(Closure $callback): mixed
    {
        $year = now()->year;

        return Cache::lock("work-evidence-code-{$year}", 10)->block(5, function () use ($callback, $year) {
            $prefix = "EVD-{$year}-";
            $last = WorkEvidence::query()->where('evidence_code', 'like', $prefix.'%')->orderByDesc('evidence_code')->value('evidence_code');
            $sequence = $last ? (int) str($last)->afterLast('-')->toString() + 1 : 1;

            return $callback($prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT));
        });
    }
}
