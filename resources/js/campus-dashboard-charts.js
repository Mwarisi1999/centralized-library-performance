import Chart from 'chart.js/auto';

const source = document.querySelector('#campus-dashboard-chart-data');
if (source) {
    const data = JSON.parse(source.textContent);
    const options = { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#334155', usePointStyle: true } } } };
    const draw = (id, type, labels, values, label) => {
        const canvas = document.querySelector(id);
        if (!canvas || !values.some(Number)) return;
        new Chart(canvas, { type, data: { labels, datasets: [{ label, data: values, backgroundColor: type === 'doughnut' ? ['#64748b','#059669','#d97706','#0f766e','#7c3aed'] : '#047857', borderRadius: 5 }] }, options: type === 'bar' ? { ...options, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } } : options });
    };
    draw('#campus-task-status-chart', 'doughnut', data.task_status.labels, data.task_status.values, 'Tasks');
    draw('#campus-hours-staff-chart', 'bar', data.hours_by_staff.labels, data.hours_by_staff.values, 'Hours');
    draw('#campus-hours-project-chart', 'bar', data.hours_by_project.labels, data.hours_by_project.values, 'Hours');
}
