<?php

namespace App\Observers;

use App\Models\WorkEntry;

class WorkEntryObserver
{
    public function created(WorkEntry $workEntry): void
    {
        $workEntry->activities()->create([
            'user_id' => $workEntry->user_id,
            'event' => 'work_entry_created',
            'description' => "Daily work entry {$workEntry->entry_code} was created.",
        ]);
    }
}
