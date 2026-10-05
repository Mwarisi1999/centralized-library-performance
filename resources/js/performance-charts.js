import { area, bar, donut, draw, readJson } from './charts/apex-theme';

const data = readJson('#performance-chart-data');

if (data) {
    draw('#performance-daily-chart', data.daily_hours, area(), 'No hours recorded in this period.');
    draw('#performance-task-chart', data.task_status, donut({ totalLabel: 'Tasks' }), 'No assigned tasks in this period.');
    draw('#performance-project-chart', data.projects, bar({ name: 'Hours', suffix: 'h' }), 'No project hours recorded in this period.');
    draw('#performance-location-chart', data.locations, donut({ totalLabel: 'Entries' }), 'No work entries in this period.');
}
