@props(['title' => 'Please correct the highlighted fields.'])
@if($errors->any())
    <div {{ $attributes->class('rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800') }} role="alert">
        <p class="font-bold">{{ $title }}</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
