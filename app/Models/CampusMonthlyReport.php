<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampusMonthlyReport extends Model
{
    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'report_code', 'campus_id', 'reporting_month', 'reporting_year', 'status',
        'finalized_by', 'finalized_at', 'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'reporting_month' => 'integer',
            'reporting_year' => 'integer',
            'finalized_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function activities()
    {
        return $this->hasMany(CampusMonthlyReportActivity::class);
    }
}
