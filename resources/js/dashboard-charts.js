import { area, bar, donut, draw, readJson } from './charts/apex-theme';

const admin = readJson('#admin-dashboard-chart-data');

if (admin) {
    draw('#admin-account-status-chart', admin.account_status, donut({ totalLabel: 'Accounts' }), 'No user accounts yet.');
    draw('#admin-users-by-role-chart', admin.users_by_role, bar({ name: 'Users' }), 'No roles have been assigned yet.');
    draw('#admin-task-pipeline-chart', admin.task_pipeline, bar({ name: 'Tasks', distributed: true }), 'No tasks have been created yet.');
}

const personal = readJson('#dashboard-chart-data');

if (personal) {
    draw('#task-status-chart', personal.task_status, donut({ totalLabel: 'Tasks' }), 'No assigned task data available.');
    draw('#hours-by-project-chart', personal.hours_by_project, bar({ name: 'Hours', suffix: 'h' }), 'No work hours recorded this month.');

    const weekly = document.querySelector('#weekly-hours-chart');
    if (weekly) draw(weekly, { ...personal.weekly_hours, values: personal.weekly_hours.values.map(Number) }, area(), 'No hours recorded this week.');
}
