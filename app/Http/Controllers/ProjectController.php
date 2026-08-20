<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Campus;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectCodeService;
use App\Services\ProjectProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Project::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(Project::STATUSES)],
            'priority' => ['nullable', Rule::in(Project::PRIORITIES)],
            'category_id' => ['nullable', 'integer', 'exists:project_categories,id'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $visibleProjects = Project::query()->visibleTo($request->user());

        $projects = (clone $visibleProjects)
            ->with(['category', 'owner', 'campuses'])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $search = trim($search);
                $query->where(function ($query) use ($search) {
                    $query->where('project_code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['priority'] ?? null, fn ($query, $priority) => $query->where('priority_level', $priority))
            ->when($validated['category_id'] ?? null, fn ($query, $categoryId) => $query->where('project_category_id', $categoryId))
            ->when($validated['owner_id'] ?? null, fn ($query, $ownerId) => $query->where('owner_id', $ownerId))
            ->when($validated['campus_id'] ?? null, fn ($query, $campusId) => $query->where(function ($query) use ($campusId) {
                $query->where('scope', 'university_wide')
                    ->orWhereHas('campuses', fn ($query) => $query->whereKey($campusId));
            }))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'categories' => ProjectCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(),
            'owners' => User::query()
                ->whereIn('id', (clone $visibleProjects)->select('owner_id'))
                ->orderBy('name')
                ->get(),
            'summary' => [
                'total' => (clone $visibleProjects)->count(),
                'planned' => (clone $visibleProjects)->whereIn('status', ['planned', 'not_started'])->count(),
                'in_progress' => (clone $visibleProjects)->where('status', 'in_progress')->count(),
                'completed' => (clone $visibleProjects)->where('status', 'completed')->count(),
                'on_hold' => (clone $visibleProjects)->where('status', 'on_hold')->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Project::class);

        return view('projects.create', [
            'categories' => ProjectCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(),
            'activeUsers' => User::query()
                ->where('account_status', 'active')
                ->with(['roles', 'staffProfile.campus'])
                ->orderBy('name')
                ->get(),
            'defaultOwnerId' => $request->user()->id,
        ]);
    }

    public function store(StoreProjectRequest $request, ProjectCodeService $codes)
    {
        $validated = $request->validated();

        $project = $codes->withNextCode(function (string $projectCode) use ($validated, $request) {
            return DB::transaction(function () use ($validated, $request, $projectCode) {
                $project = Project::create([
                    'project_code' => $projectCode,
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'project_category_id' => $validated['project_category_id'] ?? null,
                    'owner_id' => $validated['owner_id'],
                    'created_by' => $request->user()->id,
                    'start_date' => $validated['start_date'],
                    'due_date' => $validated['due_date'],
                    'scope' => $validated['scope'],
                    'priority_level' => $validated['priority_level'],
                    'progress_method' => $validated['progress_method'],
                    'progress_percentage' => 0,
                    'status' => 'planned',
                    'objectives' => $validated['objectives'] ?? null,
                    'expected_deliverables' => $validated['expected_deliverables'] ?? null,
                    'is_active' => true,
                ]);

                if ($validated['scope'] === 'selected_campuses') {
                    $project->campuses()->attach($validated['campus_ids']);
                }

                $memberIds = collect($validated['member_ids'] ?? [])
                    ->push((int) $validated['owner_id'])
                    ->unique()
                    ->mapWithKeys(fn ($userId) => [(int) $userId => [
                        'joined_at' => now(),
                        'is_active' => true,
                    ]]);

                $project->members()->attach($memberIds);

                return $project;
            });
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function show(Request $request, Project $project)
    {
        Gate::authorize('view', $project);

        $project->load([
            'category',
            'owner.roles',
            'creator.roles',
            'campuses',
            'projectMembers' => fn ($query) => $query->where('is_active', true),
            'projectMembers.user.roles',
            'projectMembers.user.staffProfile.position',
            'projectMembers.user.staffProfile.campus',
            'projectMembers.user.staffProfile.library',
        ]);

        return view('projects.show', [
            'project' => $project,
            'projectTasks' => Task::query()
                ->visibleTo($request->user())
                ->where('project_id', $project->id)
                ->with('assignees')
                ->orderByRaw('due_date is null, due_date asc')
                ->limit(10)
                ->get(),
        ]);
    }

    public function edit(Project $project)
    {
        Gate::authorize('update', $project);

        $project->load(['campuses', 'projectMembers']);
        $currentUserIds = $project->projectMembers
            ->where('is_active', true)
            ->pluck('user_id')
            ->push($project->owner_id)
            ->unique();

        return view('projects.edit', [
            'project' => $project,
            'categories' => ProjectCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(),
            'availableUsers' => User::query()
                ->where(function ($query) use ($currentUserIds) {
                    $query->where('account_status', 'active')
                        ->orWhereIn('id', $currentUserIds);
                })
                ->with(['roles', 'staffProfile.campus'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project, ProjectProgressService $progress)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $project, $progress) {
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);

            $lockedProject->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'project_category_id' => $validated['project_category_id'] ?? null,
                'owner_id' => $validated['owner_id'],
                'start_date' => $validated['start_date'],
                'due_date' => $validated['due_date'],
                'scope' => $validated['scope'],
                'priority_level' => $validated['priority_level'],
                'progress_method' => $validated['progress_method'],
                'objectives' => $validated['objectives'] ?? null,
                'expected_deliverables' => $validated['expected_deliverables'] ?? null,
            ]);

            $campusIds = $validated['scope'] === 'selected_campuses'
                ? $validated['campus_ids']
                : [];
            $lockedProject->campuses()->sync($campusIds);

            $desiredMemberIds = collect($validated['member_ids'] ?? [])
                ->push((int) $validated['owner_id'])
                ->map(fn ($id) => (int) $id)
                ->unique();
            $memberships = $lockedProject->projectMembers()->get()->keyBy('user_id');

            foreach ($memberships as $userId => $membership) {
                if ($desiredMemberIds->contains((int) $userId)) {
                    $membership->update([
                        'joined_at' => $membership->joined_at ?? now(),
                        'left_at' => null,
                        'is_active' => true,
                    ]);
                } elseif ($membership->is_active) {
                    $membership->update([
                        'left_at' => now(),
                        'is_active' => false,
                    ]);
                }
            }

            $newMemberIds = $desiredMemberIds->diff($memberships->keys()->map(fn ($id) => (int) $id));
            foreach ($newMemberIds as $userId) {
                $lockedProject->projectMembers()->create([
                    'user_id' => $userId,
                    'joined_at' => now(),
                    'is_active' => true,
                ]);
            }

            $progress->recalculate($lockedProject->refresh());
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }
}
