<?php

$workflowEmailEvents = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('WORKFLOW_EMAIL_EVENTS', implode(',', [
        'project_assigned',
        'task_assigned',
        'task_submitted',
        'task_approved',
        'task_returned',
        'monthly_report_submitted',
        'monthly_report_approved',
        'monthly_report_returned',
        'monthly_report_override',
        'campus_report_finalized',
    ]))),
)));

$integerList = static fn (string $value): array => array_values(array_unique(array_map(
    'intval',
    array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''),
)));

return [
    'workflow_email' => [
        'enabled' => (bool) env('WORKFLOW_EMAIL_ENABLED', false),
        'events' => $workflowEmailEvents,
        'queue' => env('WORKFLOW_EMAIL_QUEUE', 'emails'),
    ],

    'deadline_reminders' => [
        'before_due_days' => $integerList((string) env('DEADLINE_REMINDER_BEFORE_DAYS', '3,1,0')),
        'overdue_days' => $integerList((string) env('DEADLINE_REMINDER_OVERDUE_DAYS', '1,7,14,30')),
        'email_enabled' => (bool) env('DEADLINE_REMINDER_EMAIL_ENABLED', false),
        'email_events' => ['task_due_soon', 'task_overdue'],
        'schedule' => env('DEADLINE_REMINDER_SCHEDULE', '07:00'),
    ],
];
