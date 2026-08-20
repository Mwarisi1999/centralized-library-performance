<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkEntryActivity extends Model
{
    public const EVENTS = ['work_entry_created', 'work_entry_updated', 'evidence_added', 'evidence_removed'];

    protected $fillable = ['work_entry_id', 'user_id', 'event', 'description', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function workEntry()
    {
        return $this->belongsTo(WorkEntry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getEventLabelAttribute(): string
    {
        return match ($this->event) {
            'work_entry_created' => 'Daily Work Entry Created',
            'work_entry_updated' => 'Daily Work Entry Updated',
            'evidence_added' => 'Evidence Added',
            'evidence_removed' => 'Evidence Removed',
            default => str($this->event)->replace('_', ' ')->title()->toString(),
        };
    }
}
