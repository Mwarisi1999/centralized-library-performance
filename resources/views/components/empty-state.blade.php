@props(['title', 'message' => null])
<div {{ $attributes->class('px-6 py-14 text-center') }}>
    <p class="font-semibold text-slate-700">{{ $title }}</p>
    @if($message)<p class="mt-1 text-sm text-slate-500">{{ $message }}</p>@endif
    {{ $slot }}
</div>
