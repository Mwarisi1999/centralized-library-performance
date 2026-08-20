<?php

namespace App\Services;

use App\Models\Task;
use Closure;
use Illuminate\Support\Facades\Cache;

class TaskCodeService
{
    public function withNextCode(Closure $callback): mixed
    {
        $year = now()->year;

        return Cache::lock("task-code-{$year}", 10)->block(5, function () use ($callback, $year) {
            $prefix = "TSK-{$year}-";
            $lastCode = Task::withTrashed()
                ->where('task_code', 'like', $prefix.'%')
                ->orderByDesc('task_code')
                ->value('task_code');
            $sequence = $lastCode ? ((int) str($lastCode)->afterLast('-')->toString()) + 1 : 1;

            return $callback($prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT));
        });
    }
}
