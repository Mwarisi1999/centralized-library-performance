<?php

namespace App\Services;

use App\Models\MonthlyReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonthlyReportWorkflowService
{
    public function __construct(
        private readonly IndividualMonthlyReportService $reports,
        private readonly MonthlyReportCodeService $codes,
    ) {}

    public function submit(User $user, int $month, int $year): MonthlyReport
    {
        $supervisor = $user->staffProfile?->supervisor;
        if (! $supervisor || $supervisor->account_status !== 'active') {
            throw ValidationException::withMessages([
                'report' => 'No active supervisor is assigned to your staff profile. Please contact the system administrator.',
            ]);
        }

        $existing = MonthlyReport::query()
            ->where('user_id', $user->id)
            ->where('reporting_month', $month)
            ->where('reporting_year', $year)
            ->first();

        if ($existing) {
            return $this->submitExisting($existing, $user, $supervisor, $month, $year);
        }

        return $this->codes->withNextCode(fn (string $code) => DB::transaction(function () use ($user, $supervisor, $month, $year, $code) {
            $report = MonthlyReport::query()
                ->where('user_id', $user->id)
                ->where('reporting_month', $month)
                ->where('reporting_year', $year)
                ->lockForUpdate()
                ->first();

            if ($report) {
                return $this->submitLocked($report, $user, $supervisor, $month, $year);
            }

            $snapshot = $this->reports->snapshotFor($user, $month, $year);
            $submittedAt = now();
            $report = MonthlyReport::create([
                'report_code' => $code,
                'user_id' => $user->id,
                'reviewer_id' => $supervisor->id,
                'submitted_by' => $user->id,
                'reporting_month' => $month,
                'reporting_year' => $year,
                'status' => MonthlyReport::STATUS_PENDING_REVIEW,
                'submitted_at' => $submittedAt,
                'submitted_snapshot' => $snapshot,
            ]);
            $report->activities()->create([
                'user_id' => $user->id,
                'event' => 'report_submitted',
                'description' => "Submitted {$report->report_code} to {$supervisor->name} for review.",
            ]);

            return $report->refresh();
        }));
    }

    private function submitExisting(MonthlyReport $report, User $user, User $supervisor, int $month, int $year): MonthlyReport
    {
        return DB::transaction(function () use ($report, $user, $supervisor, $month, $year) {
            $report = MonthlyReport::query()->lockForUpdate()->findOrFail($report->id);

            return $this->submitLocked($report, $user, $supervisor, $month, $year);
        });
    }

    private function submitLocked(MonthlyReport $report, User $user, User $supervisor, int $month, int $year): MonthlyReport
    {
        if ($report->user_id !== $user->id
            || ! in_array($report->status, [MonthlyReport::STATUS_DRAFT, MonthlyReport::STATUS_RETURNED_FOR_CORRECTION], true)) {
            throw ValidationException::withMessages(['report' => 'This monthly report cannot be submitted in its current state.']);
        }

        $resubmission = $report->status === MonthlyReport::STATUS_RETURNED_FOR_CORRECTION;
        $snapshot = $this->reports->snapshotFor($user, $month, $year);
        $submittedAt = now();
        $report->update([
            'reviewer_id' => $supervisor->id,
            'submitted_by' => $user->id,
            'status' => MonthlyReport::STATUS_PENDING_REVIEW,
            'submitted_at' => $submittedAt,
            'reviewed_at' => null,
            'approved_at' => null,
            'returned_at' => null,
            'approval_remark' => null,
            'correction_reason' => null,
            'submitted_snapshot' => $snapshot,
        ]);
        $report->activities()->create([
            'user_id' => $user->id,
            'event' => $resubmission ? 'report_resubmitted' : 'report_submitted',
            'description' => ($resubmission ? 'Resubmitted' : 'Submitted')." {$report->report_code} to {$supervisor->name} for review.",
        ]);

        return $report->refresh();
    }
}
