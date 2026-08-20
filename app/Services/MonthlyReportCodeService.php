<?php

namespace App\Services;

use App\Models\MonthlyReport;
use Closure;
use Illuminate\Support\Facades\Cache;

class MonthlyReportCodeService
{
    public function withNextCode(Closure $callback): mixed
    {
        $year = now()->year;

        return Cache::lock("monthly-report-code-{$year}", 10)->block(5, function () use ($callback, $year) {
            $prefix = "MRP-{$year}-";
            $last = MonthlyReport::query()
                ->where('report_code', 'like', $prefix.'%')
                ->orderByDesc('report_code')
                ->value('report_code');
            $sequence = $last ? (int) str($last)->afterLast('-')->toString() + 1 : 1;

            return $callback($prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT));
        });
    }
}
