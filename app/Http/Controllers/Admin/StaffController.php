<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Library;
use App\Models\Position;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AccountInvitationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'library_id' => ['nullable', 'integer', 'exists:libraries,id'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'employment_type' => ['nullable', Rule::in([
                'permanent', 'contract', 'graduate_fellow', 'intern', 'temporary', 'other',
            ])],
            'account_status' => ['nullable', Rule::in(['pending', 'active', 'suspended', 'inactive'])],
        ]);

        $visibleStaff = $this->visibleStaffQuery($request->user());
        $staff = (clone $visibleStaff)
            ->with([
                'staffProfile.campus',
                'staffProfile.library',
                'staffProfile.position',
                'staffProfile.supervisor.roles',
                'roles',
            ])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $search = trim($search);

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('staffProfile', function ($query) use ($search) {
                            $query->where('staff_number', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->when($validated['campus_id'] ?? null, fn ($query, $campusId) => $query->whereHas('staffProfile', fn ($query) => $query->where('campus_id', $campusId)))
            ->when($validated['library_id'] ?? null, fn ($query, $libraryId) => $query->whereHas('staffProfile', fn ($query) => $query->where('library_id', $libraryId)))
            ->when($validated['role'] ?? null, fn ($query, $role) => $query->role($role))
            ->when($validated['position_id'] ?? null, fn ($query, $positionId) => $query->whereHas('staffProfile', fn ($query) => $query->where('position_id', $positionId)))
            ->when($validated['employment_type'] ?? null, fn ($query, $employmentType) => $query->whereHas('staffProfile', fn ($query) => $query->where('employment_type', $employmentType)))
            ->when($validated['account_status'] ?? null, fn ($query, $status) => $query->where('account_status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $authorizedCampusId = $this->authorizedCampusId($request->user());
        $selectedCampus = $authorizedCampusId ?? ($validated['campus_id'] ?? null);

        return view('admin.staff.index', [
            'staff' => $staff,
            'campuses' => Campus::query()->where('is_active', true)
                ->when($authorizedCampusId, fn ($query, $campusId) => $query->whereKey($campusId))
                ->orderBy('name')->get(),
            'libraries' => Library::query()
                ->where('is_active', true)
                ->when($selectedCampus, fn ($query) => $query->where('campus_id', $selectedCampus))
                ->orderBy('name')
                ->get(),
            'roles' => Role::query()->orderBy('name')->get(),
            'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(),
            'summary' => [
                'total' => (clone $visibleStaff)->count(),
                'campus_librarians' => (clone $visibleStaff)->role('Campus Librarian')->count(),
                'staff' => (clone $visibleStaff)->role('Staff')->count(),
                'interns' => (clone $visibleStaff)->role('Intern')->count(),
                'active' => (clone $visibleStaff)->where('account_status', 'active')->count(),
                'pending' => (clone $visibleStaff)->where('account_status', 'pending')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.staff.create', [
            'campuses' => Campus::where('is_active', true)->orderBy('name')->get(),
            'libraries' => Library::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
            'supervisors' => User::role([
                'University Librarian',
                'Campus Librarian',
            ])->orderBy('name')->get(),
        ]);
    }

    public function show(User $user)
    {
        abort_unless($this->visibleStaffQuery(request()->user())->whereKey($user)->exists(), 403);
        $user->load([
            'roles',
            'staffProfile.campus',
            'staffProfile.library',
            'staffProfile.position',
            'staffProfile.supervisor.roles',
            'staffProfile.supervisor.staffProfile.campus',
            'staffProfile.supervisor.staffProfile.library',
            'supervisees.user.roles',
            'supervisees.campus',
            'supervisees.library',
            'supervisees.position',
        ]);

        return view('admin.staff.show', [
            'user' => $user,
            'directReports' => $user->supervisees
                ->when($campusId = $this->authorizedCampusId(request()->user()), fn ($profiles) => $profiles->where('campus_id', $campusId))
                ->sortBy(fn (StaffProfile $profile) => $profile->user?->name),
        ]);
    }

    public function edit(User $user)
    {
        $user->load(['roles', 'staffProfile']);

        $excludedSupervisorIds = array_merge([$user->id], $this->descendantUserIds($user));

        return view('admin.staff.edit', [
            'user' => $user,
            'campuses' => Campus::query()->where('is_active', true)->orderBy('name')->get(),
            'libraries' => Library::query()->where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::query()->orderBy('name')->get(),
            'supervisors' => User::query()
                ->role(['University Librarian', 'Campus Librarian', 'Staff'])
                ->whereNotIn('id', $excludedSupervisorIds)
                ->with(['roles', 'staffProfile.campus', 'staffProfile.library'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $campusBasedRoles = ['Staff', 'Intern', 'Campus Librarian'];

        if ($request->input('role') === 'Intern') {
            $request->merge(['employment_type' => 'intern']);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'exists:roles,name'],
            'campus_id' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), $campusBasedRoles, true)),
                'nullable',
                'exists:campuses,id',
            ],
            'library_id' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), $campusBasedRoles, true)),
                'nullable',
                'exists:libraries,id',
            ],
            'position_id' => ['nullable', 'exists:positions,id'],
            'supervisor_id' => ['nullable', 'exists:users,id', Rule::notIn([$user->id])],
            'employment_type' => ['nullable', Rule::in([
                'permanent', 'contract', 'graduate_fellow', 'intern', 'temporary', 'other',
            ])],
            'start_date' => ['nullable', 'date'],
            'account_status' => ['required', Rule::in(['pending', 'active', 'suspended', 'inactive'])],
        ], [
            'campus_id.required' => 'A campus is required for the selected role.',
            'library_id.required' => 'A library is required for the selected role.',
            'supervisor_id.not_in' => 'A staff member cannot supervise themselves.',
        ]);

        $validator->after(function ($validator) use ($request, $user) {
            if ($request->filled('library_id')) {
                $libraryIsValid = $request->filled('campus_id')
                    && Library::query()
                        ->whereKey($request->integer('library_id'))
                        ->where('campus_id', $request->integer('campus_id'))
                        ->exists();

                if (! $libraryIsValid) {
                    $validator->errors()->add('library_id', 'The selected library does not belong to the selected campus.');
                }
            }

            if (! $request->filled('supervisor_id')) {
                return;
            }

            $supervisorId = $request->integer('supervisor_id');

            if (in_array($supervisorId, $this->descendantUserIds($user), true)) {
                $validator->errors()->add('supervisor_id', 'This supervisor assignment would create a circular reporting relationship.');

                return;
            }

            if (! $this->supervisorIsValidFor(
                $supervisorId,
                (string) $request->input('role'),
                $request->integer('campus_id') ?: null,
                $request->integer('library_id') ?: null,
            )) {
                $validator->errors()->add('supervisor_id', 'The selected supervisor does not match the role and organizational assignment.');
            }
        });

        $validated = $validator->validate();

        DB::transaction(function () use ($user, $validated, $campusBasedRoles) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'account_status' => $validated['account_status'],
            ]);
            $user->syncRoles([$validated['role']]);

            $profileData = [
                'campus_id' => $validated['campus_id'] ?? null,
                'library_id' => $validated['library_id'] ?? null,
                'position_id' => $validated['position_id'] ?? null,
                'supervisor_id' => $validated['supervisor_id'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'employment_type' => $validated['employment_type'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
            ];

            if ($user->staffProfile) {
                $user->staffProfile->update($profileData);
            } elseif (in_array($validated['role'], $campusBasedRoles, true) || collect($profileData)->filter()->isNotEmpty()) {
                StaffProfile::create($profileData + [
                    'user_id' => $user->id,
                    'staff_number' => $this->generateStaffNumber(),
                    'status' => 'active',
                ]);
            }
        });

        return redirect()
            ->route('admin.staff.show', $user)
            ->with('success', 'Staff account updated successfully.');
    }

    public function store(Request $request, AccountInvitationService $invitations)
    {
        if ($request->input('role') === 'Intern') {
            $request->merge(['employment_type' => 'intern']);
        }

        $campusBasedRoles = ['Staff', 'Intern', 'Campus Librarian'];

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],

            'campus_id' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), $campusBasedRoles, true)),
                'nullable',
                'exists:campuses,id',
            ],
            'library_id' => [
                Rule::requiredIf(fn () => in_array($request->input('role'), $campusBasedRoles, true)),
                'nullable',
                'exists:libraries,id',
            ],
            'position_id' => ['nullable', 'exists:positions,id'],
            'supervisor_id' => ['nullable', 'exists:users,id'],

            'employment_type' => [
                'nullable',
                'in:permanent,contract,graduate_fellow,intern,temporary,other',
            ],

            'start_date' => ['nullable', 'date'],

            'role' => ['required', 'exists:roles,name'],
        ], [
            'campus_id.required' => 'A campus is required for the selected role.',
            'library_id.required' => 'A library is required for the selected role.',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (! $request->filled('library_id') || ! $request->filled('campus_id')) {
                return;
            }

            $libraryBelongsToCampus = Library::query()
                ->whereKey($request->input('library_id'))
                ->where('campus_id', $request->input('campus_id'))
                ->exists();

            if (! $libraryBelongsToCampus) {
                $validator->errors()->add(
                    'library_id',
                    'The selected library does not belong to the selected campus.'
                );
            }
        });

        $validated = $validator->validate();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => null,
                'account_status' => 'pending',
            ]);

            if (isset($validated['supervisor_id']) && (int) $validated['supervisor_id'] === $user->id) {
                throw ValidationException::withMessages([
                    'supervisor_id' => 'A staff member cannot be their own supervisor.',
                ]);
            }

            $user->assignRole($validated['role']);

            StaffProfile::create([
                'user_id' => $user->id,
                'staff_number' => $this->generateStaffNumber(),

                'campus_id' => $validated['campus_id'] ?? null,
                'library_id' => $validated['library_id'] ?? null,
                'position_id' => $validated['position_id'] ?? null,
                'supervisor_id' => $validated['supervisor_id'] ?? null,

                'phone' => $validated['phone'] ?? null,
                'employment_type' => $validated['employment_type'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'status' => 'active',
            ]);

            return $user;
        });

        $invitations->send($user);

        return redirect()
            ->route('admin.staff.create')
            ->with('success', 'Staff account created successfully. An activation invitation has been sent.');
    }

    public function resendInvitation(User $user, AccountInvitationService $invitations)
    {
        if ($user->account_status !== 'pending') {
            return back()->withErrors([
                'invitation' => 'Only pending accounts can receive activation invitations.',
            ]);
        }

        $invitations->send($user);

        return back()->with('success', 'A new activation invitation has been sent.');
    }

    private function generateStaffNumber(): string
    {
        $lastProfile = StaffProfile::withTrashed()
            ->orderByDesc('staff_number')
            ->lockForUpdate()
            ->first();

        $nextNumber = $lastProfile === null
            ? 1
            : ((int) Str::after($lastProfile->staff_number, 'LIB-')) + 1;

        return 'LIB-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function visibleStaffQuery(User $viewer): Builder
    {
        $query = User::query();
        $campusId = $this->authorizedCampusId($viewer);

        if ($viewer->hasRole('Campus Librarian')) {
            abort_unless($campusId, 403);

            $query->whereHas('staffProfile', fn (Builder $query) => $query
                ->where('campus_id', $campusId)
                ->where('status', 'active'));
        }

        return $query;
    }

    private function authorizedCampusId(User $viewer): ?int
    {
        if (! $viewer->hasRole('Campus Librarian')) {
            return null;
        }

        $profile = $viewer->staffProfile;

        return $profile?->status === 'active' && $profile->campus?->is_active
            ? $profile->campus_id
            : null;
    }

    /** @return array<int, int> */
    private function descendantUserIds(User $user): array
    {
        $descendants = [];
        $supervisorIds = [$user->id];

        while ($supervisorIds !== []) {
            $directReportIds = StaffProfile::query()
                ->whereIn('supervisor_id', $supervisorIds)
                ->whereNotIn('user_id', $descendants)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $descendants = array_values(array_unique([...$descendants, ...$directReportIds]));
            $supervisorIds = $directReportIds;
        }

        return $descendants;
    }

    private function supervisorIsValidFor(int $supervisorId, string $role, ?int $campusId, ?int $libraryId): bool
    {
        $allowedRoles = match ($role) {
            'Campus Librarian' => ['University Librarian'],
            'Staff' => ['Campus Librarian'],
            'Intern' => ['Staff', 'Campus Librarian'],
            default => [],
        };

        if ($allowedRoles === []) {
            return false;
        }

        $supervisor = User::query()
            ->with('staffProfile')
            ->role($allowedRoles)
            ->find($supervisorId);

        if (! $supervisor) {
            return false;
        }

        if ($role === 'Campus Librarian') {
            return true;
        }

        $supervisorProfile = $supervisor->staffProfile;

        return $supervisorProfile
            && (int) $supervisorProfile->campus_id === $campusId
            && ($supervisorProfile->library_id === null || (int) $supervisorProfile->library_id === $libraryId);
    }
}
