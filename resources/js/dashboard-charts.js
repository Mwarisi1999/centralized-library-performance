import Chart from 'chart.js/auto';

const dataElement = document.querySelector('#dashboard-chart-data');

if (dataElement) {
    const chartData = JSON.parse(dataElement.textContent);
    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 350 },
        plugins: {
            legend: { labels: { color: '#6B6375', usePointStyle: true, boxWidth: 10 } },
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
                    backgroundColor: ['#6B6375', '#1E73BE', '#F9D028', '#13294B', '#001F3F'],
                    borderColor: '#FFFFFF',
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
                datasets: [{ label: 'Hours', data: chartData.hours_by_project.values, backgroundColor: '#1E73BE', borderRadius: 6 }],
            },
            options: {
                ...baseOptions,
                indexAxis: 'y',
                plugins: { ...baseOptions.plugins, legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { color: '#6B6375' }, grid: { color: '#E5E4E7' } },
                    y: { ticks: { color: '#13294B' }, grid: { display: false } },
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
                    borderColor: '#1E73BE',
                    backgroundColor: 'rgba(30, 115, 190, 0.14)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#001F3F',
                    pointRadius: 4,
                }],
            },
            options: {
                ...baseOptions,
                plugins: { ...baseOptions.plugins, legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { color: '#6B6375' }, grid: { color: '#E5E4E7' } },
                    x: { ticks: { color: '#13294B' }, grid: { display: false } },
                },
            },
        });
    }
}
