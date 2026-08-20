<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkEvidence;
use Illuminate\Support\Facades\Gate;

class WorkEvidencePolicy
{
    public function view(User $user, WorkEvidence $workEvidence): bool
    {
        return Gate::forUser($user)->allows('view', $workEvidence->workEntry);
    }

    public function delete(User $user, WorkEvidence $workEvidence): bool
    {
        return $workEvidence->user_id === $user->id
            && Gate::forUser($user)->allows('addEvidence', $workEvidence->workEntry);
    }
}
