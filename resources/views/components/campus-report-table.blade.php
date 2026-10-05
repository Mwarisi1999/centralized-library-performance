@props(['title','headers','subtitle' => null])
<section {{ $attributes->class('overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm') }}>
    <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
        <h3 class="text-lg font-bold">{{ $title }}</h3>
        @if($subtitle)<p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500"><tr>@foreach($headers as $header)<th class="whitespace-nowrap px-4 py-3">{{ $header }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100 text-slate-700 [&>tr:hover]:bg-slate-50/70">{{ $slot }}</tbody>
        </table>
    </div>
</section>
