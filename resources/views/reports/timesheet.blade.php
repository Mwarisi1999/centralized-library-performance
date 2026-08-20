@extends('layouts.report')
@section('title', 'Monthly Staff Timesheet - '.$period['label'])
@section('content')
<header class="masthead"><h1>Busitema University</h1><p class="system">Centralized Library Staff Performance System</p><h2>Monthly Staff Timesheet</h2></header>
<table class="meta"><tr>
    @foreach([['Staff Name',$staff['name']],['Position',$staff['position']],['Campus',$staff['campus']],['Library',$staff['library']]] as [$label,$value])<td><span class="label">{{ $label }}</span>{{ $value ?: '—' }}</td>@endforeach
</tr><tr><td colspan="2"><span class="label">Supervisor</span>{{ $staff['supervisor'] ?: '—' }}</td><td colspan="2"><span class="label">Reporting Period</span>{{ $period['label'] }}</td></tr></table>
<table class="data" style="font-size:7.3px; table-layout:fixed"><colgroup><col style="width:11%"><col style="width:14%"><col style="width:15%"><col style="width:13%"><col style="width:16%"><col style="width:19%"><col style="width:12%"></colgroup><thead><tr><th>Code / Date / Time</th><th>Project / Task / Subtask</th><th>Work Description</th><th>Output / Deliverable</th><th>Challenge / Corrective Action</th><th>Support / Follow-up / Remarks</th><th>Work Location</th></tr></thead><tbody>
@forelse($entries as $entry)<tr>
    <td><strong>{{ $entry->entry_code }}</strong><br>{{ $entry->work_date->format('d M Y') }}<br>{{ $entry->start_time }}–{{ $entry->end_time }}<br><strong>{{ $entry->formatted_duration }}</strong></td>
    <td><strong>{{ $entry->project?->title ?? '—' }}</strong><br>{{ $entry->task?->title ?? '—' }}<br>{{ $entry->subtask?->title ?? '—' }}</td>
    <td>{{ $entry->work_description }}</td><td>{{ $entry->output_deliverable ?: '—' }}</td>
    <td><span class="label">Challenge</span>{{ $entry->challenge_encountered ?: '—' }}<br><br><span class="label">Corrective Action</span>{{ $entry->corrective_action ?: '—' }}</td>
    <td><span class="label">Support Required</span>{{ $entry->support_required ?: '—' }}<br><br><span class="label">Follow-up / Planned Activity</span>{{ $entry->planned_next_activity ?: '—' }}<br><br><span class="label">Remarks</span>{{ $entry->remarks ?: '—' }}</td>
    <td>{{ $entry->work_location ?: '—' }}</td>
</tr>@empty<tr><td colspan="7" class="muted">No work entries were recorded for this reporting period.</td></tr>@endforelse
</tbody></table>
<table class="summary" style="margin-top:12px"><tr><td><span class="label">Total Monthly Hours</span><span class="value">{{ $totalHours }}</span></td><td><span class="label">Reporting Days</span><span class="value">{{ $reportingDays }}</span></td><td colspan="2"><span class="label">Generated</span>{{ now()->format('d F Y, h:i A') }}</td></tr></table>
@endsection
