<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkEntry;
use Carbon\CarbonImmutable;

class TimesheetReportService
{
    /** @return array<string, mixed> */
    public function monthlyFor(User $user, int $month, int $year): array
    {
        $period = CarbonImmutable::create($year, $month, 1);
        $user->loadMissing([
            'staffProfile.position', 'staffProfile.campus', 'staffProfile.library',
            'staffProfile.supervisor',
        ]);

        $entries = WorkEntry::query()
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$period->startOfMonth(), $period->endOfMonth()])
            ->with(['project:id,project_code,title', 'task:id,task_code,title', 'subtask:id,subtask_code,title'])
            ->orderBy('work_date')->orderBy('start_time')->orderBy('id')->get();
        $profile = $user->staffProfile;

        return [
            'period' => ['month' => $month, 'year' => $year, 'label' => $period->format('F Y')],
            'staff' => [
                'name' => $user->name,
                'position' => $profile?->position?->name,
                'campus' => $profile?->campus?->name,
                'library' => $profile?->library?->name,
                'supervisor' => $profile?->supervisor?->name,
            ],
            'entries' => $entries,
            'totalMinutes' => (int) $entries->sum('duration_minutes'),
            'totalHours' => WorkEntry::formatMinutes((int) $entries->sum('duration_minutes')),
            'reportingDays' => $entries->pluck('work_date')->map->toDateString()->unique()->count(),
        ];
    }
}
