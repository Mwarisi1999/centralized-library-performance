<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Library;
use App\Models\Position;
use App\Models\ProjectCategory;
use App\Services\JobDescriptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class OrganizationController extends Controller
{
    private const ENTITIES = [
        'campuses' => [Campus::class, 'manage campuses', 'Campuses'],
        'libraries' => [Library::class, 'manage libraries', 'Libraries'],
        'positions' => [Position::class, 'manage positions', 'Positions'],
        'project-categories' => [ProjectCategory::class, 'manage project categories', 'Project Categories'],
    ];

    public function index(Request $request, string $entity = 'campuses'): View
    {
        [$class, , $label] = $this->definition($request, $entity);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) data_get($validated, 'search', ''));
        $records = $class::query()->withTrashed()
            ->when($entity === 'libraries', fn ($query) => $query->with('campus'))
            ->when($entity === 'positions', fn ($query) => $query->with('jobDetail'))
            ->when($search, function ($query) use ($entity, $search) {
                $query->where(function ($nested) use ($entity, $search) {
                    foreach ($this->searchableColumns($entity) as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $nested->{$method}($column, 'like', "%{$search}%");
                    }
                });
            })
            ->withCount($this->usageRelation($entity))
            ->when(
                $entity === 'positions',
                fn ($query) => $query->inRankOrder(),
                fn ($query) => $query->orderBy('name')
            )
            ->paginate(20)
            ->withQueryString();

        return view('admin.organization.index', compact('entity', 'label', 'records'));
    }

    public function create(Request $request, string $entity): View
    {
        [, , $label] = $this->definition($request, $entity);

        return view('admin.organization.form', ['entity' => $entity, 'label' => $label, 'record' => null, 'campuses' => Campus::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, string $entity, JobDescriptionService $jobDescriptions): RedirectResponse
    {
        [$class] = $this->definition($request, $entity);
        $attributes = $this->validated($request, $entity);

        DB::transaction(function () use ($class, $entity, $attributes, $request, $jobDescriptions) {
            $record = $class::create($attributes);

            if ($entity === 'positions') {
                $this->syncJobDescription($record, $request, $jobDescriptions);
            }
        });

        return redirect()->route('admin.organization.index', $entity)->with('success', 'Organization record created successfully.');
    }

    public function edit(Request $request, string $entity, int $record): View
    {
        [$class, , $label] = $this->definition($request, $entity);
        $model = $class::withTrashed()->findOrFail($record);

        return view('admin.organization.form', ['entity' => $entity, 'label' => $label, 'record' => $model, 'campuses' => Campus::where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(Request $request, string $entity, int $record, JobDescriptionService $jobDescriptions): RedirectResponse
    {
        [$class] = $this->definition($request, $entity);
        $model = $class::withTrashed()->findOrFail($record);
        $attributes = $this->validated($request, $entity, $model);

        DB::transaction(function () use ($model, $entity, $attributes, $request, $jobDescriptions) {
            $model->update($attributes);

            if ($entity === 'positions') {
                $this->syncJobDescription($model, $request, $jobDescriptions);
            }
        });

        return redirect()->route('admin.organization.index', $entity)->with('success', 'Organization record updated successfully.');
    }

    public function toggle(Request $request, string $entity, int $record): RedirectResponse
    {
        [$class] = $this->definition($request, $entity);
        $model = $class::withTrashed()->findOrFail($record);
        abort_if($model->trashed(), 422);
        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('success', $model->is_active ? 'Record activated.' : 'Record deactivated. Existing history was preserved.');
    }

    /** Saves the position's job description and alerts the staff who hold that position. */
    private function syncJobDescription(Position $position, Request $request, JobDescriptionService $jobDescriptions): void
    {
        $fields = ['salary_scale', 'reports_to', 'responsible_for', 'job_purpose', 'duties'];

        // Requests that do not carry the job description section leave it untouched.
        if (! $request->hasAny($fields)) {
            return;
        }

        $detail = $jobDescriptions->sync($position, $request->only($fields));

        if ($detail && $position->is_active && ! $position->trashed()) {
            $jobDescriptions->notifyHolders($position, $detail);
        }
    }

    private function definition(Request $request, string $entity): array
    {
        abort_unless(isset(self::ENTITIES[$entity]), 404);
        $definition = self::ENTITIES[$entity];
        $hasEntityPermission = $request->user()->can($definition[1]);

        if ($entity === 'project-categories'
            && ! Permission::query()->where('name', $definition[1])->where('guard_name', 'web')->exists()) {
            $hasEntityPermission = $request->user()->can('manage roles and permissions');
        }

        abort_unless($request->user()->hasRole('Administrator') && $hasEntityPermission, 403);

        return $definition;
    }

    private function validated(Request $request, string $entity, ?Model $record = null): array
    {
        $table = match ($entity) {
            'project-categories' => 'project_categories', default => $entity
        };
        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique($table, 'name')->ignore($record)],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', 'boolean'],
        ];
        if (in_array($entity, ['campuses', 'libraries', 'positions'], true)) {
            $rules['code'] = [$entity === 'campuses' ? 'required' : 'nullable', 'string', 'max:50', Rule::unique($table, 'code')->ignore($record)];
        }
        if ($entity === 'libraries') {
            $rules['campus_id'] = ['required', 'integer', 'exists:campuses,id'];
            $rules['email'] = ['nullable', 'email', 'max:255'];
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }
        if ($entity === 'positions') {
            $rules['sort_order'] = ['nullable', 'integer', 'min:1', 'max:1000'];
            $rules['salary_scale'] = ['nullable', 'string', 'max:50'];
            $rules['reports_to'] = ['nullable', 'string', 'max:255'];
            $rules['responsible_for'] = ['nullable', 'string', 'max:255'];
            $rules['job_purpose'] = ['nullable', 'required_with:duties', 'string', 'max:5000'];
            $rules['duties'] = ['nullable', 'required_with:job_purpose', 'string', 'max:20000'];
        }
        if ($entity === 'campuses') {
            $rules['location'] = ['nullable', 'string', 'max:255'];
            $rules['email'] = ['nullable', 'email', 'max:255'];
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        $validated = $request->validate($rules, [
            'job_purpose.required_with' => 'Add the job purpose so staff understand the role behind these duties.',
            'duties.required_with' => 'List at least one duty, one per line, for this job description.',
        ]);

        // Job description fields are stored on position_job_details, not on the position itself.
        unset($validated['salary_scale'], $validated['reports_to'], $validated['responsible_for'], $validated['job_purpose'], $validated['duties']);

        return array_replace($validated, [
            'is_active' => $request->boolean('is_active'),
        ]);
    }

    private function usageRelation(string $entity): string
    {
        return match ($entity) {
            'project-categories' => 'projects', default => 'staffProfiles'
        };
    }

    /** @return array<int, string> */
    private function searchableColumns(string $entity): array
    {
        return $entity === 'project-categories'
            ? ['name', 'description']
            : ['name', 'code'];
    }
}
