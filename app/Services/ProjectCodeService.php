<?php

namespace App\Services;

use App\Models\Project;
use Closure;
use Illuminate\Support\Facades\Cache;

class ProjectCodeService
{
    public function withNextCode(Closure $callback): mixed
    {
        $year = now()->year;

        return Cache::lock("project-code-{$year}", 10)->block(5, function () use ($callback, $year) {
            $prefix = "PRJ-{$year}-";
            $lastCode = Project::withTrashed()
                ->where('project_code', 'like', $prefix.'%')
                ->orderByDesc('project_code')
                ->value('project_code');

            $sequence = $lastCode ? ((int) str($lastCode)->afterLast('-')->toString()) + 1 : 1;

            return $callback($prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT));
        });
    }
}
