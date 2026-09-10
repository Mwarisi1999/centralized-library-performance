<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOwnPasswordRequest;
use App\Http\Requests\UpdateOwnProfileRequest;
use App\Models\StaffProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load([
            'roles',
            'staffProfile.campus',
            'staffProfile.library',
            'staffProfile.position',
            'staffProfile.supervisor',
        ]);

        return view('profile.show', [
            'user' => $user,
            'profile' => $user->staffProfile,
            'completion' => $this->completion($user->staffProfile, $user->name, $user->email, $user->profile_picture),
        ]);
    }

    public function update(UpdateOwnProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->staffProfile;
        $validated = $request->validated();

        DB::transaction(function () use ($user, $profile, $validated) {
            $user->update([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
            ]);

            if ($profile) {
                $profile->update([
                    'phone' => $validated['phone'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                    'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                    'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
                ]);
            }
        });

        return redirect()->route('profile.show')->with('success', 'Your profile has been updated successfully.');
    }

    public function updatePassword(UpdateOwnPasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return redirect()->route('profile.show')->with('success', 'Your password has been changed successfully.');
    }

    public function updatePicture(Request $request): RedirectResponse
    {
        $request->validate([
            'profile_picture' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $disk = Storage::disk('public');
        $newPath = $request->file('profile_picture')->store('profile-pictures', 'public');

        if (! is_string($newPath)) {
            throw ValidationException::withMessages([
                'profile_picture' => 'The profile picture could not be stored. Please try again.',
            ]);
        }

        $user = $request->user();
        $oldPath = $user->managedProfilePicturePath();

        try {
            $user->update(['profile_picture' => $newPath]);
        } catch (Throwable $exception) {
            $disk->delete($newPath);

            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            $disk->delete($oldPath);
        }

        return redirect()->route('profile.show')->with('success', 'Your profile picture has been updated.');
    }

    public function destroyPicture(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldPath = $user->managedProfilePicturePath();

        $user->update(['profile_picture' => null]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('profile.show')->with('success', 'Your profile picture has been removed.');
    }

    private function completion(?StaffProfile $profile, string $name, string $email, ?string $profilePicture): int
    {
        $values = [
            $name, $email, $profile?->staff_number, $profile?->phone,
            $profile?->gender, $profile?->date_of_birth, $profile?->address,
            $profile?->position_id, $profile?->campus_id, $profile?->library_id,
            $profile?->employment_type, $profile?->start_date, $profile?->supervisor_id,
            $profile?->emergency_contact_name, $profile?->emergency_contact_phone,
            $profile?->emergency_contact_relationship, $profilePicture,
        ];

        $filled = collect($values)->filter(fn ($value) => filled($value))->count();

        return (int) round(($filled / count($values)) * 100);
    }
}
