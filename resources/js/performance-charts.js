import Chart from 'chart.js/auto';

const source = document.querySelector('#performance-chart-data');
if (source) {
    const draw = (id, type, series, label, colors = '#047857') => {
        const canvas = document.querySelector(id);
        if (!canvas || !series.values.some(Number)) return;
        new Chart(canvas, { type, data: { labels: series.labels, datasets: [{ label, data: series.values, backgroundColor: colors, borderColor: '#047857', borderWidth: type === 'line' ? 2 : 0, tension: .25, fill: false, borderRadius: 5 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: type === 'doughnut' } }, scales: type === 'doughnut' ? {} : { y: { beginAtZero: true } } } });
    };
    const data = JSON.parse(source.textContent);
    draw('#performance-daily-chart', 'line', data.daily_hours, 'Hours');
    draw('#performance-task-chart', 'doughnut', data.task_status, 'Tasks', ['#64748b','#059669','#d97706','#0f766e','#7c3aed']);
    draw('#performance-project-chart', 'bar', data.projects, 'Hours');
    draw('#performance-location-chart', 'doughnut', data.locations, 'Entries', ['#047857','#0f766e','#64748b','#d97706']);
}
