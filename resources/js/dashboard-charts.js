let ApexCharts;

const brand = {
    navy: '#001F3F',
    blue: '#1E73BE',
    deepBlue: '#13294B',
    gold: '#F9D028',
    body: '#6B6375',
    border: '#E5E4E7',
    red: '#DC2626',
    muted: '#C9C6CF',
};

const statusColors = {
    'Not Started': brand.body,
    'In Progress': brand.blue,
    'Pending Review': brand.gold,
    Completed: brand.navy,
    Deferred: brand.deepBlue,
    Cancelled: brand.muted,
    Active: brand.blue,
    Pending: brand.gold,
    Suspended: brand.red,
    Inactive: brand.body,
};

const fallbackPalette = [brand.blue, brand.navy, brand.gold, brand.deepBlue, brand.body, brand.muted];

const colorsFor = (labels) => labels.map((label, index) => statusColors[label] ?? fallbackPalette[index % fallbackPalette.length]);

const baseOptions = (height) => ({
    chart: {
        height,
        fontFamily: "'Source Sans Variable', 'Source Sans 3', sans-serif",
        foreColor: brand.body,
        toolbar: { show: false },
        animations: { speed: 400 },
    },
    dataLabels: { enabled: false },
    grid: { borderColor: brand.border, strokeDashArray: 4 },
    legend: { fontSize: '13px', markers: { size: 6 }, itemMargin: { horizontal: 8, vertical: 4 } },
    tooltip: { theme: 'light' },
});

const showEmpty = (element, message) => {
    element.innerHTML = `<div class="flex h-full min-h-72 items-center justify-center rounded-xl bg-slate-50 px-6 text-center text-sm text-slate-500">${message}</div>`;
};

const render = (element, options) => {
    element.innerHTML = '';
    new ApexCharts(element, options).render();
};

const donut = (element, series, height, totalLabel) => render(element, {
    ...baseOptions(height),
    chart: { ...baseOptions(height).chart, type: 'donut' },
    series: series.values,
    labels: series.labels,
    colors: colorsFor(series.labels),
    stroke: { width: 3, colors: ['#FFFFFF'] },
    legend: { ...baseOptions(height).legend, position: 'bottom' },
    plotOptions: {
        pie: {
            donut: {
                size: '68%',
                labels: {
                    show: true,
                    value: { fontSize: '28px', fontWeight: 800, color: brand.navy },
                    total: { show: true, label: totalLabel, fontSize: '13px', color: brand.body },
                },
            },
        },
    },
});

const horizontalBar = (element, series, height, name, distributed = false) => render(element, {
    ...baseOptions(height),
    chart: { ...baseOptions(height).chart, type: 'bar' },
    series: [{ name, data: series.values }],
    colors: distributed ? colorsFor(series.labels) : [brand.blue],
    plotOptions: { bar: { horizontal: true, borderRadius: 6, borderRadiusApplication: 'end', barHeight: '58%', distributed } },
    dataLabels: { enabled: true, style: { fontWeight: 700 }, offsetX: 4 },
    legend: { show: false },
    xaxis: { categories: series.labels, labels: { formatter: (value) => Number.isInteger(Number(value)) ? value : '' } },
    yaxis: { labels: { style: { colors: brand.deepBlue, fontWeight: 600 }, maxWidth: 220 } },
    grid: { ...baseOptions(height).grid, yaxis: { lines: { show: false } } },
});

const hasValues = (series) => series && series.values.some((value) => value > 0);

const initDashboardCharts = async () => {
    const adminDataElement = document.querySelector('#admin-dashboard-chart-data');
    const dataElement = document.querySelector('#dashboard-chart-data');

    if (!adminDataElement && !dataElement) return;

    ({ default: ApexCharts } = await import('apexcharts'));

    if (adminDataElement) {
        const admin = JSON.parse(adminDataElement.textContent);
        const accountStatus = document.querySelector('#admin-account-status-chart');
        const usersByRole = document.querySelector('#admin-users-by-role-chart');
        const taskPipeline = document.querySelector('#admin-task-pipeline-chart');

        if (accountStatus) {
            hasValues(admin.account_status) ? donut(accountStatus, admin.account_status, 300, 'Accounts') : showEmpty(accountStatus, 'No user accounts yet.');
        }

        if (usersByRole) {
            hasValues(admin.users_by_role) ? horizontalBar(usersByRole, admin.users_by_role, 300, 'Users') : showEmpty(usersByRole, 'No roles have been assigned yet.');
        }

        if (taskPipeline) {
            hasValues(admin.task_pipeline) ? horizontalBar(taskPipeline, admin.task_pipeline, 300, 'Tasks', true) : showEmpty(taskPipeline, 'No tasks have been created yet.');
        }
    }

    if (dataElement) {
        const chartData = JSON.parse(dataElement.textContent);

        const completion = document.querySelector('#completion-rate-chart');
        if (completion) {
            const rate = Number(completion.dataset.rate ?? 0);

            render(completion, {
                ...baseOptions(260),
                chart: { ...baseOptions(260).chart, type: 'radialBar', sparkline: { enabled: true } },
                series: [rate],
                colors: [brand.blue],
                fill: { type: 'gradient', gradient: { shade: 'light', type: 'horizontal', gradientToColors: [brand.navy], stops: [0, 100] } },
                stroke: { lineCap: 'round' },
                plotOptions: {
                    radialBar: {
                        hollow: { size: '64%' },
                        track: { background: brand.border, strokeWidth: '100%' },
                        dataLabels: {
                            name: { show: true, offsetY: 24, color: brand.body, fontSize: '13px' },
                            value: { offsetY: -12, fontSize: '34px', fontWeight: 800, color: brand.navy, formatter: (value) => `${Number(value).toFixed(1)}%` },
                        },
                    },
                },
                labels: ['Completed'],
            });
        }

        const taskStatus = document.querySelector('#task-status-chart');
        if (taskStatus && chartData.task_status.total > 0) {
            donut(taskStatus, chartData.task_status, '100%', 'Tasks');
        }

        const hoursByProject = document.querySelector('#hours-by-project-chart');
        if (hoursByProject && chartData.hours_by_project.values.length > 0) {
            horizontalBar(hoursByProject, chartData.hours_by_project, '100%', 'Hours');
        }

        const weeklyHours = document.querySelector('#weekly-hours-chart');
        if (weeklyHours) {
            render(weeklyHours, {
                ...baseOptions('100%'),
                chart: { ...baseOptions('100%').chart, type: 'area' },
                series: [{ name: 'Hours', data: chartData.weekly_hours.values }],
                colors: [brand.blue],
                stroke: { curve: 'smooth', width: 3 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95] } },
                markers: { size: 5, colors: ['#FFFFFF'], strokeColors: brand.blue, strokeWidth: 2, hover: { size: 7 } },
                xaxis: {
                    categories: chartData.weekly_hours.labels,
                    labels: { style: { colors: brand.deepBlue, fontWeight: 600 } },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (value) => `${Number(value).toFixed(1)}h` } },
                tooltip: { theme: 'light', y: { formatter: (value) => `${value} hours` } },
            });
        }
    }
};

initDashboardCharts();
