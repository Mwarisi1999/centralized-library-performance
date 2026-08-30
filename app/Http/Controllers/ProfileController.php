<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOwnPasswordRequest;
use App\Http\Requests\UpdateOwnProfileRequest;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load([
            'staffProfile.campus',
            'staffProfile.library',
            'staffProfile.position',
            'staffProfile.supervisor',
        ]);

        return view('profile.show', [
            'user' => $user,
            'profile' => $user->staffProfile,
            'completion' => $this->completion($user->staffProfile, $user->name, $user->email),
        ]);
    }

    public function update(UpdateOwnProfileRequest $request)
    {
        $user = $request->user();
        $profile = $user->staffProfile;
        $validated = $request->safe()->except('profile_photo');
        $oldPhoto = $profile?->profile_photo_path;
        $newPhoto = $request->file('profile_photo')?->store("profile-photos/{$user->id}", 'local');

        DB::transaction(function () use ($user, $profile, $validated, $newPhoto) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
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
                    ...($newPhoto ? ['profile_photo_path' => $newPhoto] : []),
                ]);
            }
        });

        if ($newPhoto && $oldPhoto) {
            Storage::disk('local')->delete($oldPhoto);
        }

        return redirect()->route('profile.show')->with('success', 'Your profile has been updated successfully.');
    }

    public function updatePassword(UpdateOwnPasswordRequest $request)
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return redirect()->route('profile.show')->with('success', 'Your password has been changed successfully.');
    }

    public function photo(Request $request)
    {
        $path = $request->user()->staffProfile?->profile_photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function completion(?StaffProfile $profile, string $name, string $email): int
    {
        $values = [
            $name, $email, $profile?->staff_number, $profile?->phone,
            $profile?->gender, $profile?->date_of_birth, $profile?->address,
            $profile?->position_id, $profile?->campus_id, $profile?->library_id,
            $profile?->employment_type, $profile?->start_date, $profile?->supervisor_id,
            $profile?->emergency_contact_name, $profile?->emergency_contact_phone,
            $profile?->emergency_contact_relationship, $profile?->profile_photo_path,
        ];

        $filled = collect($values)->filter(fn ($value) => filled($value))->count();

        return (int) round(($filled / count($values)) * 100);
    }
}
