<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view projects');
    }

    public function view(User $user, Project $project): bool
    {
        if (! $user->can('view projects')) {
            return false;
        }

        return Project::query()->visibleTo($user)->whereKey($project)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('create projects');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('edit projects') && $this->view($user, $project);
    }
}
