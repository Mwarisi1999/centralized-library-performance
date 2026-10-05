@props(['period'])

{{-- Month/year filter styled for the dark dashboard hero. --}}
<form method="GET" {{ $attributes->class('flex flex-wrap items-center gap-2 rounded-xl bg-white/10 p-1.5 ring-1 ring-white/20') }}>
    <label class="sr-only" for="period-month">Month</label>
    <select id="period-month" name="month" class="rounded-lg border-0 bg-white py-2 pl-3 pr-8 text-sm font-semibold text-heading focus:ring-2 focus:ring-busitema-gold">
        @foreach(range(1, 12) as $month)
            <option value="{{ $month }}" @selected($period->month === $month)>{{ now()->startOfYear()->month($month)->format('F') }}</option>
        @endforeach
    </select>
    <label class="sr-only" for="period-year">Year</label>
    <select id="period-year" name="year" class="rounded-lg border-0 bg-white py-2 pl-3 pr-8 text-sm font-semibold text-heading focus:ring-2 focus:ring-busitema-gold">
        @foreach(range(now()->year + 1, 2000) as $year)
            <option value="{{ $year }}" @selected($period->year === $year)>{{ $year }}</option>
        @endforeach
    </select>
    <button class="rounded-lg bg-busitema-gold px-4 py-2 text-sm font-bold text-busitema-navy transition hover:bg-busitema-yellow">Apply</button>
</form>
