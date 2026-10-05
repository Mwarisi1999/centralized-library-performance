import { bar, donut, draw, readJson } from './charts/apex-theme';

const data = readJson('#campus-dashboard-chart-data');

if (data) {
    draw('#campus-task-status-chart', data.task_status, donut({ totalLabel: 'Tasks' }), 'No tasks assigned in this period.');
    draw('#campus-hours-staff-chart', data.hours_by_staff, bar({ name: 'Hours', suffix: 'h' }), 'No hours recorded by staff in this period.');
    draw('#campus-hours-project-chart', data.hours_by_project, bar({ name: 'Hours', suffix: 'h' }), 'No project hours recorded in this period.');
}
