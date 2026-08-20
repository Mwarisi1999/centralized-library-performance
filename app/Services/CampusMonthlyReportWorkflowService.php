<?php

namespace App\Services;

use App\Models\CampusMonthlyReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CampusMonthlyReportWorkflowService
{
    public function __construct(
        private readonly CampusMonthlyReportService $reports,
        private readonly CampusMonthlyReportCodeService $codes,
    ) {}

    public function finalize(User $user, int $month, int $year): CampusMonthlyReport
    {
        $campus = $this->reports->campusFor($user);
        abort_unless($campus, 403);

        $existing = CampusMonthlyReport::query()->where('campus_id', $campus->id)
            ->where('reporting_month', $month)->where('reporting_year', $year)->first();
        if ($existing) {
            return $existing;
        }

        return $this->codes->withNextCode(fn (string $code) => DB::transaction(function () use ($user, $campus, $month, $year, $code) {
            $existing = CampusMonthlyReport::query()->where('campus_id', $campus->id)
                ->where('reporting_month', $month)->where('reporting_year', $year)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $snapshot = $this->reports->liveData($user, $campus, $month, $year);
            $snapshot['identity']['status'] = CampusMonthlyReport::STATUS_FINALIZED;
            $report = CampusMonthlyReport::create([
                'report_code' => $code,
                'campus_id' => $campus->id,
                'reporting_month' => $month,
                'reporting_year' => $year,
                'status' => CampusMonthlyReport::STATUS_FINALIZED,
                'finalized_by' => $user->id,
                'finalized_at' => now(),
                'snapshot' => $snapshot,
            ]);
            $report->activities()->create([
                'user_id' => $user->id,
                'event' => 'report_finalized',
                'description' => "Finalized {$report->report_code} for {$snapshot['identity']['period']}.",
            ]);

            return $report;
        }));
    }
}
