import Chart from 'chart.js/auto';

const source = document.querySelector('#university-dashboard-chart-data');
if (source) {
    const data = JSON.parse(source.textContent);
    const base = { responsive: true, maintainAspectRatio: false };
    const draw = (id, type, series, label, colors = '#1E73BE') => {
        const canvas = document.querySelector(id);
        if (!canvas || !series.values.some(Number)) return;
        new Chart(canvas, { type, data: { labels: series.labels, datasets: [{ label, data: series.values, backgroundColor: colors, borderRadius: 5 }] }, options: type === 'bar' ? { ...base, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } : base });
    };
    draw('#university-hours-campus-chart', 'bar', data.hours_by_campus, 'Hours');
    draw('#university-task-status-chart', 'doughnut', data.task_status, 'Tasks', ['#6B6375','#1E73BE','#F9D028','#13294B','#001F3F']);
    draw('#university-completion-chart', 'bar', data.completion_rates, 'Completion rate');
    draw('#university-report-chart', 'doughnut', data.report_status, 'Campuses', ['#1E73BE','#F9D028']);
    draw('#university-project-chart', 'bar', data.project_progress, 'Progress');
}
