<?php

namespace App\Http\Controllers;

use App\Models\WorkEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WeeklyActivityController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('viewAny', WorkEntry::class);

        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,'.(today()->year + 1)],
            'week' => ['nullable', 'integer', 'min:1', 'max:6'],
        ]);

        $latestDate = $request->user()->workEntries()->max('work_date');
        $reference = $latestDate ? CarbonImmutable::parse($latestDate) : CarbonImmutable::today();
        $month = (int) ($validated['month'] ?? $reference->month);
        $year = (int) ($validated['year'] ?? $reference->year);
        $weeks = $this->weeksForMonth($year, $month);
        $selectedWeek = min(max((int) ($validated['week'] ?? $this->weekContaining($weeks, $reference)), 1), count($weeks));
        $week = $weeks[$selectedWeek - 1];
        $weekStart = CarbonImmutable::parse($week['start']);
        $weekEnd = CarbonImmutable::parse($week['end']);

        $entries = $request->user()->workEntries()
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with(['project', 'task', 'subtask'])
            ->orderBy('work_date')->orderBy('start_time')->orderBy('id')->get();

        $days = collect(range(0, 6))->map(function (int $offset) use ($weekStart, $entries) {
            $date = $weekStart->addDays($offset);
            $dayEntries = $entries->filter(fn (WorkEntry $entry) => $entry->work_date->isSameDay($date))->values();

            return [
                'date' => $date,
                'is_weekday' => $date->isWeekday(),
                'entries' => $dayEntries,
                'minutes' => (int) $dayEntries->sum('duration_minutes'),
            ];
        });

        return view('weekly-activities.index', [
            'month' => $month,
            'year' => $year,
            'weeks' => $weeks,
            'selectedWeek' => $selectedWeek,
            'days' => $days,
            'summary' => [
                'entries' => $entries->count(),
                'minutes' => (int) $entries->sum('duration_minutes'),
                'completed' => $entries->where('activity_status', 'completed')->count(),
                'in_progress' => $entries->where('activity_status', 'in_progress')->count(),
                'missing_weekdays' => $days->where('is_weekday', true)->filter(fn (array $day) => $day['entries']->isEmpty())->count(),
            ],
        ]);
    }

    private function weeksForMonth(int $year, int $month): array
    {
        $first = CarbonImmutable::create($year, $month, 1)->startOfWeek(CarbonInterface::MONDAY);
        $last = CarbonImmutable::create($year, $month, 1)->endOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $weeks = [];

        for ($start = $first, $number = 1; $start->lte($last); $start = $start->addWeek(), $number++) {
            $end = $start->addDays(6);
            $weeks[] = [
                'value' => $number,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => $start->format('j M').' – '.$end->format('j M Y'),
            ];
        }

        return $weeks;
    }

    private function weekContaining(array $weeks, CarbonImmutable $date): int
    {
        foreach ($weeks as $week) {
            if ($date->betweenIncluded($week['start'], $week['end'])) {
                return $week['value'];
            }
        }

        return 1;
    }
}
