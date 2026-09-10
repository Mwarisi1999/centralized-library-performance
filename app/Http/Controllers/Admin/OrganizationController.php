<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Library;
use App\Models\Position;
use App\Models\ProjectCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->when($search, function ($query) use ($entity, $search) {
                $query->where(function ($nested) use ($entity, $search) {
                    foreach ($this->searchableColumns($entity) as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $nested->{$method}($column, 'like', "%{$search}%");
                    }
                });
            })
            ->withCount($this->usageRelation($entity))->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.organization.index', compact('entity', 'label', 'records'));
    }

    public function create(Request $request, string $entity): View
    {
        [, , $label] = $this->definition($request, $entity);

        return view('admin.organization.form', ['entity' => $entity, 'label' => $label, 'record' => null, 'campuses' => Campus::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, string $entity): RedirectResponse
    {
        [$class] = $this->definition($request, $entity);
        $class::create($this->validated($request, $entity));

        return redirect()->route('admin.organization.index', $entity)->with('success', 'Organization record created successfully.');
    }

    public function edit(Request $request, string $entity, int $record): View
    {
        [$class, , $label] = $this->definition($request, $entity);
        $model = $class::withTrashed()->findOrFail($record);

        return view('admin.organization.form', ['entity' => $entity, 'label' => $label, 'record' => $model, 'campuses' => Campus::where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(Request $request, string $entity, int $record): RedirectResponse
    {
        [$class] = $this->definition($request, $entity);
        $model = $class::withTrashed()->findOrFail($record);
        $model->update($this->validated($request, $entity, $model));

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
        if ($entity === 'campuses') {
            $rules['location'] = ['nullable', 'string', 'max:255'];
            $rules['email'] = ['nullable', 'email', 'max:255'];
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        return array_replace($request->validate($rules), [
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
