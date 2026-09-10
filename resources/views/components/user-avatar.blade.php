@props(['user', 'size' => 'md', 'preview' => false])

@php
    $nameParts = collect(preg_split('/\s+/', trim($user->name)))->filter()->values();
    $initials = $nameParts->take(3)
        ->map(fn ($part) => str($part)->substr(0, 1))
        ->join('') ?: '?';
    $pictureUrl = $user->profilePictureUrl();
    $dimensions = $size === 'xl' ? 'h-28 w-28 text-2xl' : 'h-10 w-10 text-sm';
@endphp

<span
    {{ $attributes->class("relative flex {$dimensions} shrink-0 items-center justify-center overflow-hidden rounded-full bg-emerald-100 font-bold uppercase text-emerald-800 ring-1 ring-inset ring-emerald-700/10") }}
    @if($preview) data-profile-picture-preview-container data-profile-picture-alt="{{ $user->name }} profile picture" @endif
>
    @if($pictureUrl)
        <img src="{{ $pictureUrl }}" alt="{{ $user->name }} profile picture" class="h-full w-full object-cover">
    @endif
    <span @if($preview) data-profile-picture-fallback @endif class="{{ $pictureUrl ? 'hidden' : '' }}">{{ $initials }}</span>
</span>
