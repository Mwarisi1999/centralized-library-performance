<?php

namespace App\Services;

use App\Models\WorkEntry;
use Closure;
use Illuminate\Support\Facades\Cache;

class WorkEntryCodeService
{
    public function withNextCode(Closure $callback): mixed
    {
        $year = now()->year;

        return Cache::lock("work-entry-code-{$year}", 10)->block(5, function () use ($callback, $year) {
            $prefix = "WEN-{$year}-";
            $last = WorkEntry::withTrashed()->where('entry_code', 'like', $prefix.'%')->orderByDesc('entry_code')->value('entry_code');
            $sequence = $last ? (int) str($last)->afterLast('-')->toString() + 1 : 1;

            return $callback($prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT));
        });
    }
}
