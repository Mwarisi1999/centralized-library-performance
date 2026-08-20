import Chart from 'chart.js/auto';

const dataElement = document.querySelector('#dashboard-chart-data');

if (dataElement) {
    const chartData = JSON.parse(dataElement.textContent);
    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 350 },
        plugins: {
            legend: { labels: { color: '#334155', usePointStyle: true, boxWidth: 10 } },
        },
    };

    const taskCanvas = document.querySelector('#task-status-chart');
    if (taskCanvas && chartData.task_status.total > 0) {
        new Chart(taskCanvas, {
            type: 'doughnut',
            data: {
                labels: chartData.task_status.labels,
                datasets: [{
                    data: chartData.task_status.values,
                    backgroundColor: ['#64748b', '#059669', '#d97706', '#0f766e', '#7c3aed'],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                }],
            },
            options: { ...baseOptions, cutout: '65%' },
        });
    }

    const projectCanvas = document.querySelector('#hours-by-project-chart');
    if (projectCanvas && chartData.hours_by_project.values.length > 0) {
        new Chart(projectCanvas, {
            type: 'bar',
            data: {
                labels: chartData.hours_by_project.labels,
                datasets: [{ label: 'Hours', data: chartData.hours_by_project.values, backgroundColor: '#047857', borderRadius: 6 }],
            },
            options: {
                ...baseOptions,
                indexAxis: 'y',
                plugins: { ...baseOptions.plugins, legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { color: '#64748b' }, grid: { color: '#e2e8f0' } },
                    y: { ticks: { color: '#334155' }, grid: { display: false } },
                },
            },
        });
    }

    const weeklyCanvas = document.querySelector('#weekly-hours-chart');
    if (weeklyCanvas) {
        new Chart(weeklyCanvas, {
            type: 'line',
            data: {
                labels: chartData.weekly_hours.labels,
                datasets: [{
                    label: 'Hours',
                    data: chartData.weekly_hours.values,
                    borderColor: '#0f766e',
                    backgroundColor: 'rgba(16, 185, 129, 0.14)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#0f172a',
                    pointRadius: 4,
                }],
            },
            options: {
                ...baseOptions,
                plugins: { ...baseOptions.plugins, legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { color: '#64748b' }, grid: { color: '#e2e8f0' } },
                    x: { ticks: { color: '#334155' }, grid: { display: false } },
                },
            },
        });
    }
}
