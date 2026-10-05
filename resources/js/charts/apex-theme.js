// Shared ApexCharts theme for every dashboard. ApexCharts is loaded lazily so
// pages without charts never download it.

export const brand = {
    ink: '#1C1A17',
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
    Overdue: brand.red,
    Active: brand.blue,
    Pending: brand.gold,
    Suspended: brand.red,
    Inactive: brand.body,
    Finalized: brand.navy,
    'Not Finalized': brand.gold,
};

const fallbackPalette = [brand.blue, brand.navy, brand.gold, brand.deepBlue, brand.body, brand.muted];

const colorsFor = (labels) => labels.map((label, index) => statusColors[label] ?? fallbackPalette[index % fallbackPalette.length]);

let apexPromise;

export const loadApex = () => {
    apexPromise ??= import('apexcharts').then((module) => module.default);

    return apexPromise;
};

export const normalize = (series) => ({
    labels: Array.from(series?.labels ?? [], String),
    values: Array.from(series?.values ?? [], Number),
});

export const hasValues = (series) => normalize(series).values.some((value) => value > 0);

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

const withBase = (height, type, options) => {
    const base = baseOptions(height);

    return { ...base, ...options, chart: { ...base.chart, type, ...(options.chart ?? {}) } };
};

export const showEmpty = (element, message) => {
    element.innerHTML = `<div class="flex h-full min-h-64 items-center justify-center rounded-xl bg-slate-50 px-6 text-center text-sm text-slate-500">${message}</div>`;
};

const render = async (element, options) => {
    const ApexCharts = await loadApex();
    element.innerHTML = '';
    new ApexCharts(element, options).render();
};

/**
 * Renders the chart when the series has data, otherwise an empty-state message.
 * Accepts an element or selector so callers can stay declarative.
 */
export const draw = (target, series, builder, emptyMessage = 'No data recorded for this period.') => {
    const element = typeof target === 'string' ? document.querySelector(target) : target;
    if (!element) return;

    const data = normalize(series);
    if (!data.values.some((value) => value > 0)) {
        showEmpty(element, emptyMessage);
        return;
    }

    render(element, builder(data, element.dataset.height ?? element.clientHeight ?? 288));
};

export const donut = ({ totalLabel = 'Total', formatter } = {}) => (series, height) => withBase(height || 288, 'donut', {
    series: series.values,
    labels: series.labels,
    colors: colorsFor(series.labels),
    stroke: { width: 3, colors: ['#FFFFFF'] },
    legend: { ...baseOptions().legend, position: 'bottom' },
    tooltip: { theme: 'light', y: formatter ? { formatter } : {} },
    plotOptions: {
        pie: {
            donut: {
                size: '68%',
                labels: {
                    show: true,
                    value: { fontSize: '26px', fontWeight: 800, color: brand.ink, formatter: formatter ?? ((value) => value) },
                    total: {
                        show: true,
                        label: totalLabel,
                        fontSize: '13px',
                        color: brand.body,
                        formatter: (chart) => {
                            const total = chart.globals.seriesTotals.reduce((sum, value) => sum + value, 0);

                            return formatter ? formatter(total) : Math.round(total * 100) / 100;
                        },
                    },
                },
            },
        },
    },
});

export const bar = ({ name = 'Value', horizontal = true, distributed = false, suffix = '', max } = {}) => (series, height) => withBase(height || 288, 'bar', {
    series: [{ name, data: series.values }],
    colors: distributed ? colorsFor(series.labels) : [horizontal ? brand.blue : brand.navy],
    plotOptions: {
        bar: {
            horizontal,
            distributed,
            borderRadius: 6,
            borderRadiusApplication: 'end',
            barHeight: '58%',
            columnWidth: '46%',
        },
    },
    dataLabels: {
        enabled: horizontal,
        style: { fontWeight: 700 },
        offsetX: 4,
        formatter: (value) => `${value}${suffix}`,
    },
    legend: { show: false },
    xaxis: {
        categories: series.labels,
        max: horizontal ? max : undefined,
        labels: horizontal
            ? { formatter: (value) => `${Math.round(Number(value) * 10) / 10}${suffix}` }
            : { style: { colors: brand.deepBlue, fontWeight: 600 }, trim: true, hideOverlappingLabels: true },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: horizontal
        ? { labels: { style: { colors: brand.deepBlue, fontWeight: 600 }, maxWidth: 200 } }
        : { min: 0, max, forceNiceScale: true, labels: { formatter: (value) => `${Math.round(value * 10) / 10}${suffix}` } },
    grid: { ...baseOptions().grid, [horizontal ? 'yaxis' : 'xaxis']: { lines: { show: false } } },
    tooltip: { theme: 'light', y: { formatter: (value) => `${value}${suffix}` } },
});

export const area = ({ name = 'Hours', suffix = 'h' } = {}) => (series, height) => withBase(height || 288, 'area', {
    series: [{ name, data: series.values }],
    colors: [brand.blue],
    stroke: { curve: 'smooth', width: 3 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95] } },
    markers: { size: series.values.length > 14 ? 0 : 5, colors: ['#FFFFFF'], strokeColors: brand.blue, strokeWidth: 2, hover: { size: 6 } },
    xaxis: {
        categories: series.labels,
        labels: { style: { colors: brand.deepBlue, fontWeight: 600 }, hideOverlappingLabels: true, rotate: 0 },
        axisBorder: { show: false },
        axisTicks: { show: false },
        tickAmount: series.values.length > 14 ? 10 : undefined,
    },
    yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (value) => `${Number(value).toFixed(1)}${suffix}` } },
    tooltip: { theme: 'light', y: { formatter: (value) => `${value}${suffix}` } },
});

export const radial = ({ label = 'Completed' } = {}) => (series, height) => withBase(height || 260, 'radialBar', {
    chart: { sparkline: { enabled: true } },
    series: series.values,
    colors: [brand.gold],
    fill: { type: 'gradient', gradient: { shade: 'light', type: 'horizontal', gradientToColors: [brand.blue], stops: [0, 100] } },
    stroke: { lineCap: 'round' },
    plotOptions: {
        radialBar: {
            hollow: { size: '64%' },
            track: { background: brand.border, strokeWidth: '100%' },
            dataLabels: {
                name: { show: true, offsetY: 24, color: brand.body, fontSize: '13px' },
                value: { offsetY: -12, fontSize: '34px', fontWeight: 800, color: brand.ink, formatter: (value) => `${Number(value).toFixed(1)}%` },
            },
        },
    },
    labels: [label],
});

/** Draws a radial gauge even at 0%, since an empty ring is still meaningful. */
export const drawRadial = async (target, value, options) => {
    const element = typeof target === 'string' ? document.querySelector(target) : target;
    if (!element) return;

    render(element, radial(options)({ labels: [], values: [Number(value) || 0] }, 260));
};

export const readJson = (selector) => {
    const element = document.querySelector(selector);

    return element ? JSON.parse(element.textContent) : null;
};
