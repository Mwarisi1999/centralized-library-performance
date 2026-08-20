<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkEvidence extends Model
{
    public const TYPES = ['file', 'link'];

    protected $table = 'work_evidences';

    protected $fillable = [
        'evidence_code', 'work_entry_id', 'user_id', 'type', 'title', 'description',
        'file_path', 'original_filename', 'stored_filename', 'mime_type',
        'file_extension', 'file_size', 'url',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function workEntry()
    {
        return $this->belongsTo(WorkEntry::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getFormattedFileSizeAttribute(): ?string
    {
        if ($this->file_size === null) {
            return null;
        }

        return $this->file_size >= 1048576
            ? rtrim(rtrim(number_format($this->file_size / 1048576, 2), '0'), '.').' MB'
            : rtrim(rtrim(number_format($this->file_size / 1024, 2), '0'), '.').' KB';
    }
}
