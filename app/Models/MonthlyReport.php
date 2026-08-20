<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyReport extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETURNED_FOR_CORRECTION = 'returned_for_correction';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_RETURNED_FOR_CORRECTION,
    ];

    protected $fillable = [
        'report_code',
        'user_id',
        'reporting_month',
        'reporting_year',
        'status',
        'key_achievements',
        'challenges',
        'corrective_actions',
        'support_required',
        'planned_activities_next_month',
        'reviewer_id',
        'submitted_by',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'returned_at',
        'approval_remark',
        'correction_reason',
        'submitted_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'reporting_month' => 'integer',
            'reporting_year' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
            'submitted_snapshot' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function activities()
    {
        return $this->hasMany(MonthlyReportActivity::class);
    }

    public static function label(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }
}
