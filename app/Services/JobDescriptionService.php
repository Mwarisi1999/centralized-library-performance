<?php

namespace App\Services;

use App\Models\Position;
use App\Models\PositionJobDetail;
use App\Models\User;

class JobDescriptionService
{
    public function __construct(private readonly WorkflowNotificationService $notifications) {}

    /**
     * Creates, updates or removes a position's job description from admin form input.
     * Returns the saved detail when its content changed, otherwise null.
     *
     * @param  array{salary_scale?: ?string, reports_to?: ?string, responsible_for?: ?string, job_purpose?: ?string, duties?: ?string}  $input
     */
    public function sync(Position $position, array $input): ?PositionJobDetail
    {
        $purpose = trim((string) ($input['job_purpose'] ?? ''));
        $duties = $this->parseDuties((string) ($input['duties'] ?? ''));

        if ($purpose === '' && $duties === []) {
            $position->jobDetail()->delete();
            $position->unsetRelation('jobDetail');

            return null;
        }

        $detail = $position->jobDetail()->firstOrNew();
        $detail->fill([
            'salary_scale' => $this->nullableTrim($input['salary_scale'] ?? null),
            'reports_to' => $this->nullableTrim($input['reports_to'] ?? null),
            'responsible_for' => $this->nullableTrim($input['responsible_for'] ?? null),
            'job_purpose' => $purpose,
            'duties' => $duties,
        ]);

        if ($detail->exists && ! $detail->isDirty()) {
            return null;
        }

        $detail->save();
        $position->setRelation('jobDetail', $detail);

        return $detail;
    }

    /** Tells every active staff member holding the position that their job description changed. */
    public function notifyHolders(Position $position, PositionJobDetail $detail): void
    {
        User::query()
            ->where('account_status', 'active')
            ->whereHas('staffProfile', fn ($query) => $query->where('position_id', $position->id))
            ->get()
            ->each(fn (User $user) => $this->notifications->send(
                $user,
                'job_description_updated',
                'Your job description was updated',
                "The job description for {$position->name} has been updated. Review your duties before recording new work.",
                route('job-description.show'),
                "job-description-updated:{$position->id}:{$user->id}:{$detail->updated_at?->timestamp}",
                'action',
            ));
    }

    /** Tells a staff member about the job description for a position they were just assigned. */
    public function notifyAssignment(User $user, Position $position): void
    {
        $position->loadMissing('jobDetail');

        if (! $position->jobDetail) {
            return;
        }

        $this->notifications->send(
            $user,
            'job_description_assigned',
            'Your job description is available',
            "You have been assigned the {$position->name} position. Your job description and duties are now on your dashboard.",
            route('job-description.show'),
            "job-description-assigned:{$position->id}:{$user->id}:{$position->jobDetail->updated_at?->timestamp}",
            'action',
        );
    }

    /**
     * One duty per line; leading bullets or numbering ("1.", "a)", "-", "•") are stripped.
     *
     * @return list<string>
     */
    public function parseDuties(string $text): array
    {
        return collect(preg_split('/\R/u', $text))
            ->map(fn (string $line) => trim(preg_replace('/^\s*(?:[-*•▪]+|\(?(?:\d{1,3}|[a-zA-Z])[.)])\s+/u', '', $line)))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
