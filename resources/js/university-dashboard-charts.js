import { bar, donut, draw, readJson } from './charts/apex-theme';

const data = readJson('#university-dashboard-chart-data');

if (data) {
    draw('#university-hours-campus-chart', data.hours_by_campus, bar({ name: 'Hours', horizontal: false, suffix: 'h' }), 'No hours recorded in this period.');
    draw('#university-task-status-chart', data.task_status, donut({ totalLabel: 'Tasks' }), 'No tasks assigned in this period.');
    draw('#university-completion-chart', data.completion_rates, bar({ name: 'Completion rate', suffix: '%', max: 100 }), 'No completed assignments in this period.');
    draw('#university-report-chart', data.report_status, donut({ totalLabel: 'Campuses' }), 'No active campuses.');
    draw('#university-project-chart', data.project_progress, bar({ name: 'Progress', suffix: '%', max: 100 }), 'No project progress recorded yet.');
}
