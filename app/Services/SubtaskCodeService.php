<?php

namespace App\Services;

use App\Models\Subtask;
use Closure;
use Illuminate\Support\Facades\Cache;

class SubtaskCodeService
{
    public function withNextCode(Closure $callback): mixed
    {
        $year = now()->year;

        return Cache::lock("subtask-code-{$year}", 10)->block(5, function () use ($callback, $year) {
            $prefix = "SUB-{$year}-";
            $last = Subtask::withTrashed()->where('subtask_code', 'like', $prefix.'%')->orderByDesc('subtask_code')->value('subtask_code');
            $sequence = $last ? (int) str($last)->afterLast('-')->toString() + 1 : 1;

            return $callback($prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT));
        });
    }
}
