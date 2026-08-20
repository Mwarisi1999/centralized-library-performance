<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampusMonthlyReportActivity extends Model
{
    protected $fillable = ['user_id', 'event', 'description'];

    public function report()
    {
        return $this->belongsTo(CampusMonthlyReport::class, 'campus_monthly_report_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
