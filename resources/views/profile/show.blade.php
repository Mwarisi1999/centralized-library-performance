@extends('layouts.app')

@section('title', 'My Profile')
@section('section-label', 'Account')
@section('page-title', 'My Profile')

@section('content')
@php
    $fieldClass = 'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-busitema-blue focus:ring-2 focus:ring-busitema-blue/20';
    $readOnlyClass = 'mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600';
@endphp

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-950">My Profile</h2>
    <p class="mt-1 text-sm text-slate-600">Manage your personal information, emergency contact, photo, and account security.</p>
</div>

<div class="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
    <aside class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
            <x-user-avatar :user="$user" size="xl" :preview="true" class="mx-auto ring-4 ring-white shadow" />
            <h3 class="mt-4 text-xl font-bold text-slate-950">{{ $user->name }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ $profile?->position?->name ?? $user->getRoleNames()->join(', ') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $profile?->library?->name ?? $profile?->campus?->name ?? 'Library assignment pending' }}</p>
            <span class="mt-4 inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $profile?->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                {{ str($profile?->status ?? $user->account_status ?? 'active')->replace('_', ' ')->title() }}
            </span>

            <form method="POST" action="{{ route('profile.picture.update') }}" enctype="multipart/form-data" class="mt-5 border-t border-slate-100 pt-5 text-left">
                @csrf
                @method('PATCH')
                <label class="block">
                    <span class="text-sm font-semibold text-slate-700">Profile picture</span>
                    <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" data-profile-picture-input class="{{ $fieldClass }} file:mr-4 file:rounded-lg file:border-0 file:bg-busitema-blue/10 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-busitema-blue">
                    <span class="mt-1 block text-xs text-slate-500">JPG, PNG, or WebP, up to 2 MB.</span>
                    @error('profile_picture')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="submit" class="rounded-xl bg-busitema-blue px-4 py-2 text-sm font-bold text-white">{{ $user->profile_picture ? 'Replace picture' : 'Upload picture' }}</button>
                </div>
            </form>

            @if($user->profile_picture)
                <form method="POST" action="{{ route('profile.picture.destroy') }}" class="mt-2 text-left" onsubmit="return confirm('Remove your current profile picture?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-xl px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Remove picture</button>
                </form>
            @endif

            <dl class="mt-6 space-y-3 border-t border-slate-100 pt-5 text-left text-sm">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Email</dt><dd class="mt-1 break-words text-slate-700">{{ $user->email }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Phone</dt><dd class="mt-1 text-slate-700">{{ $profile?->phone ?: 'Not provided' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Campus</dt><dd class="mt-1 text-slate-700">{{ $profile?->campus?->name ?: 'Not assigned' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4"><h3 class="font-bold text-slate-950">Profile completion</h3><span class="font-bold text-busitema-blue">{{ $completion }}%</span></div>
            <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Profile completion" aria-valuenow="{{ $completion }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-busitema-gold" style="width: {{ $completion }}%"></div>
            </div>
            <p class="mt-3 text-xs leading-5 text-slate-500">Add your personal and emergency contact details to complete your profile.</p>
        </section>
    </aside>

    <div class="space-y-6">
        <form method="POST" action="{{ route('profile.update') }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @csrf
            @method('PATCH')

            <div class="border-b border-slate-200 px-5 py-4 sm:px-6"><h3 class="text-lg font-bold text-slate-950">Personal information</h3><p class="mt-1 text-sm text-slate-500">These details appear on your staff profile.</p></div>
            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <label class="block sm:col-span-2"><span class="text-sm font-semibold text-slate-700">Full name</span><input name="name" value="{{ old('name', $user->name) }}" required class="{{ $fieldClass }}">@error('name')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Email address</span><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="{{ $fieldClass }}">@error('email')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Phone number</span><input name="phone" value="{{ old('phone', $profile?->phone) }}" class="{{ $fieldClass }}">@error('phone')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Gender</span><select name="gender" class="{{ $fieldClass }}"><option value="">Select gender</option>@foreach(['female' => 'Female', 'male' => 'Male', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'] as $value => $label)<option value="{{ $value }}" @selected(old('gender', $profile?->gender) === $value)>{{ $label }}</option>@endforeach</select>@error('gender')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Date of birth</span><input type="date" name="date_of_birth" value="{{ old('date_of_birth', $profile?->date_of_birth?->format('Y-m-d')) }}" max="{{ now()->subDay()->format('Y-m-d') }}" class="{{ $fieldClass }}">@error('date_of_birth')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block sm:col-span-2"><span class="text-sm font-semibold text-slate-700">Address</span><textarea name="address" rows="2" class="{{ $fieldClass }}">{{ old('address', $profile?->address) }}</textarea>@error('address')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
            </div>

            <div class="border-y border-slate-200 bg-slate-50/60 px-5 py-4 sm:px-6"><h3 class="text-lg font-bold text-slate-950">Employment information</h3><p class="mt-1 text-sm text-slate-500">Assignment details are maintained by an administrator.</p></div>
            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <label class="block"><span class="text-sm font-semibold text-slate-700">Employee number</span><input value="{{ $profile?->staff_number ?: 'Not assigned' }}" readonly class="{{ $readOnlyClass }}"></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Job title</span><input value="{{ $profile?->position?->name ?: 'Not assigned' }}" readonly class="{{ $readOnlyClass }}"></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Campus</span><input value="{{ $profile?->campus?->name ?: 'Not assigned' }}" readonly class="{{ $readOnlyClass }}"></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Library</span><input value="{{ $profile?->library?->name ?: 'Not assigned' }}" readonly class="{{ $readOnlyClass }}"></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Employment type</span><input value="{{ $profile?->employment_type ? str($profile->employment_type)->replace('_', ' ')->title() : 'Not assigned' }}" readonly class="{{ $readOnlyClass }}"></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Joined date</span><input value="{{ $profile?->start_date?->format('d M Y') ?: 'Not recorded' }}" readonly class="{{ $readOnlyClass }}"></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Supervisor</span><input value="{{ $profile?->supervisor?->name ?: 'Not assigned' }}" readonly class="{{ $readOnlyClass }}"></label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Supervisor email</span><input value="{{ $profile?->supervisor?->email ?: 'Not available' }}" readonly class="{{ $readOnlyClass }}"></label>
            </div>

            <div class="border-y border-slate-200 bg-slate-50/60 px-5 py-4 sm:px-6"><h3 class="text-lg font-bold text-slate-950">Emergency contact</h3><p class="mt-1 text-sm text-slate-500">Someone the university can contact in an emergency.</p></div>
            <div class="grid gap-5 p-5 sm:grid-cols-3 sm:p-6">
                <label class="block"><span class="text-sm font-semibold text-slate-700">Contact name</span><input name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile?->emergency_contact_name) }}" class="{{ $fieldClass }}">@error('emergency_contact_name')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Phone number</span><input name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $profile?->emergency_contact_phone) }}" class="{{ $fieldClass }}">@error('emergency_contact_phone')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Relationship</span><input name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $profile?->emergency_contact_relationship) }}" class="{{ $fieldClass }}">@error('emergency_contact_relationship')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
            </div>

            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6"><button type="submit" class="rounded-xl bg-busitema-gold px-5 py-2.5 text-sm font-bold text-slate-950 shadow-sm transition hover:bg-busitema-yellow focus:outline-none focus:ring-2 focus:ring-busitema-gold focus:ring-offset-2">Save profile</button></div>
        </form>

        <form method="POST" action="{{ route('profile.password.update') }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @csrf
            @method('PATCH')
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6"><h3 class="text-lg font-bold text-slate-950">Account security</h3><p class="mt-1 text-sm text-slate-500">Use a strong password that you do not reuse elsewhere.</p></div>
            <div class="grid gap-5 p-5 sm:grid-cols-3 sm:p-6">
                <label class="block"><span class="text-sm font-semibold text-slate-700">Current password</span><input type="password" name="current_password" autocomplete="current-password" required class="{{ $fieldClass }}">@error('current_password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">New password</span><input type="password" name="password" autocomplete="new-password" required class="{{ $fieldClass }}">@error('password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror</label>
                <label class="block"><span class="text-sm font-semibold text-slate-700">Confirm new password</span><input type="password" name="password_confirmation" autocomplete="new-password" required class="{{ $fieldClass }}"></label>
            </div>
            <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6"><button type="submit" class="rounded-xl bg-busitema-blue px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-busitema-deep-blue focus:outline-none focus:ring-2 focus:ring-busitema-blue focus:ring-offset-2">Change password</button></div>
        </form>
    </div>
</div>
@endsection
