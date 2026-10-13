@php
    $selectedDuties = collect(old('job_description_duties', $selectedJobDescriptionDuties))->all();
    $otherSelected = filter_var(old('job_description_other', $selectedJobDescriptionOther), FILTER_VALIDATE_BOOLEAN);
    $effectivePositionId = old('position_id', $selectedPositionId);
    $selectedPosition = collect($positions)->first(fn ($position) => (string) $position->id === (string) $effectivePositionId);
    $currentDuties = collect($selectedPosition?->jobDetail?->duties ?? []);
    $legacyDuties = collect($selectedDuties)->diff($currentDuties);
    $positionDutyOptions = collect($positions)->mapWithKeys(fn ($position) => [
        (string) $position->id => $position->jobDetail?->duties ?? [],
    ]);
@endphp

<section data-job-description-alignment class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <h3 class="text-lg font-bold text-slate-900">Job description alignment</h3>
    <p class="mt-2 text-sm leading-6 text-slate-600">Select one or more duties that this project supports. Choose <strong>Other</strong> when the project falls outside the selected position's job description.</p>
    <p data-selected-position-label class="mt-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ $selectedPosition?->name ?? 'Select a position above' }}</p>

    <div data-duty-list class="mt-5 space-y-3">
        @forelse($currentDuties as $duty)
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 hover:border-emerald-400 hover:bg-emerald-50/40">
                <input type="checkbox" name="job_description_duties[]" value="{{ $duty }}" @checked(in_array($duty, $selectedDuties, true)) class="mt-1 rounded text-emerald-700 focus:ring-emerald-600">
                <span class="text-sm leading-6 text-slate-700">{{ $duty }}</span>
            </label>
        @empty
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">{{ $selectedPosition ? 'No duties are currently recorded for this position. Select Other to continue.' : 'Select a position to load its job-description duties.' }}</div>
        @endforelse

        @foreach($legacyDuties as $duty)
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <input type="checkbox" name="job_description_duties[]" value="{{ $duty }}" checked class="mt-1 rounded text-emerald-700 focus:ring-emerald-600">
                <span><span class="block text-sm leading-6 text-slate-700">{{ $duty }}</span><span class="mt-1 block text-xs text-amber-700">Previously selected duty; it is no longer in this position's current job description.</span></span>
            </label>
        @endforeach

        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-dashed border-slate-300 px-4 py-3 hover:border-emerald-400 hover:bg-emerald-50/40">
            <input type="hidden" name="job_description_other" value="0">
            <input type="checkbox" name="job_description_other" value="1" @checked($otherSelected) class="mt-1 rounded text-emerald-700 focus:ring-emerald-600">
            <span><span class="block text-sm font-semibold text-slate-800">Other</span><span class="mt-1 block text-xs leading-5 text-slate-500">Use this when the project does not fall under any listed duty.</span></span>
        </label>
    </div>
    @error('job_description_duties')<p class="mt-3 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
</section>

<script>
    (() => {
        const positionSelect = document.querySelector('[data-project-position]');
        const section = document.querySelector('[data-job-description-alignment]');
        const dutyList = section?.querySelector('[data-duty-list]');
        const positionLabel = section?.querySelector('[data-selected-position-label]');
        const dutyOptions = @json($positionDutyOptions);
        if (!positionSelect || !dutyList || !positionLabel) return;

        const dutyLabel = (duty) => {
            const label = document.createElement('label');
            label.className = 'flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 hover:border-emerald-400 hover:bg-emerald-50/40';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'job_description_duties[]';
            checkbox.value = duty;
            checkbox.className = 'mt-1 rounded text-emerald-700 focus:ring-emerald-600';
            const text = document.createElement('span');
            text.className = 'text-sm leading-6 text-slate-700';
            text.textContent = duty;
            label.append(checkbox, text);
            return label;
        };

        positionSelect.addEventListener('change', () => {
            dutyList.replaceChildren();
            positionLabel.textContent = positionSelect.selectedOptions[0]?.textContent.trim() || 'Select a position above';
            const duties = dutyOptions[positionSelect.value] || [];
            if (duties.length) {
                duties.forEach((duty) => dutyList.appendChild(dutyLabel(duty)));
            } else {
                const notice = document.createElement('div');
                notice.className = 'rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800';
                notice.textContent = positionSelect.value ? 'No duties are currently recorded for this position. Select Other to continue.' : 'Select a position to load its job-description duties.';
                dutyList.appendChild(notice);
            }

            const otherLabel = document.createElement('label');
            otherLabel.className = 'flex cursor-pointer items-start gap-3 rounded-xl border border-dashed border-slate-300 px-4 py-3 hover:border-emerald-400 hover:bg-emerald-50/40';
            otherLabel.innerHTML = '<input type="hidden" name="job_description_other" value="0"><input type="checkbox" name="job_description_other" value="1" class="mt-1 rounded text-emerald-700 focus:ring-emerald-600"><span><span class="block text-sm font-semibold text-slate-800">Other</span><span class="mt-1 block text-xs leading-5 text-slate-500">Use this when the project does not fall under any listed duty.</span></span>';
            dutyList.appendChild(otherLabel);
        });
    })();
</script>
