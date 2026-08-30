const weeklyRoot = document.querySelector('[data-weekly-activities]');

if (weeklyRoot) {
    const filter = weeklyRoot.querySelector('[data-weekly-filter]');
    const monthSelect = weeklyRoot.querySelector('[data-weekly-month]');
    const yearSelect = weeklyRoot.querySelector('[data-weekly-year]');
    const weekSelect = weeklyRoot.querySelector('[data-weekly-week]');

    const startOfWeek = (date) => {
        const result = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
        result.setUTCDate(result.getUTCDate() - ((result.getUTCDay() + 6) % 7));

        return result;
    };

    const formatDate = (date, includeYear = false) => new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
        ...(includeYear ? { year: 'numeric' } : {}),
        timeZone: 'UTC',
    }).format(date);

    const rebuildWeeks = () => {
        if (!monthSelect || !yearSelect || !weekSelect) return;

        const month = Number(monthSelect.value);
        const year = Number(yearSelect.value);
        const first = startOfWeek(new Date(Date.UTC(year, month - 1, 1)));
        const last = startOfWeek(new Date(Date.UTC(year, month, 0)));
        weekSelect.replaceChildren();

        for (let start = first, value = 1; start <= last; start = new Date(start.getTime() + 604800000), value += 1) {
            const end = new Date(start.getTime() + (6 * 86400000));
            const option = document.createElement('option');
            option.value = String(value);
            option.textContent = `${formatDate(start)} – ${formatDate(end, true)}`;
            weekSelect.append(option);
        }
    };

    [monthSelect, yearSelect].forEach((select) => select?.addEventListener('change', () => {
        rebuildWeeks();
        weekSelect.value = '1';
        filter?.requestSubmit();
    }));
    weekSelect?.addEventListener('change', () => filter?.requestSubmit());

    const modal = weeklyRoot.querySelector('[data-weekly-modal]');
    const modalTitle = weeklyRoot.querySelector('[data-weekly-modal-title]');
    const summaries = [...weeklyRoot.querySelectorAll('[data-day-summary]')];
    let trigger = null;

    const closeModal = () => {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        trigger?.focus();
        trigger = null;
    };

    weeklyRoot.querySelectorAll('[data-weekly-day-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!modal) return;
            trigger = button;
            const target = summaries.find((summary) => summary.dataset.daySummary === button.dataset.dayTarget);
            summaries.forEach((summary) => summary.classList.toggle('hidden', summary !== target));
            if (modalTitle && target) modalTitle.textContent = target.dataset.dayLabel;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            modal.querySelector('[data-weekly-modal-close]')?.focus();
        });
    });

    weeklyRoot.querySelectorAll('[data-weekly-modal-close], [data-weekly-modal-backdrop]')
        .forEach((button) => button.addEventListener('click', closeModal));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal?.classList.contains('flex')) closeModal();
    });
}
