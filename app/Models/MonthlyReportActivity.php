<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyReportActivity extends Model
{
    public const EVENTS = ['report_submitted', 'report_returned', 'report_resubmitted', 'report_approved', 'report_override_returned', 'report_override_approved'];

    protected $fillable = ['monthly_report_id', 'user_id', 'event', 'description', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function monthlyReport()
    {
        return $this->belongsTo(MonthlyReport::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getEventLabelAttribute(): string
    {
        return match ($this->event) {
            'report_submitted' => 'Submitted Report for Review',
            'report_returned' => 'Returned Report for Correction',
            'report_resubmitted' => 'Resubmitted Report',
            'report_approved' => 'Approved Report',
            'report_override_returned' => 'Returned by University Librarian Override',
            'report_override_approved' => 'Approved by University Librarian Override',
            default => str($this->event)->replace('_', ' ')->title()->toString(),
        };
    }
}
