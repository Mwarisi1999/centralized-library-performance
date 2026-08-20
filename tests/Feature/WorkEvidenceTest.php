<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\StaffProfile;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkEntry;
use App\Models\WorkEvidence;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProjectCategorySeeder::class]);
        Storage::fake('local');
        $this->travelTo('2026-08-18 12:00:00');
    }

    public function test_owner_can_open_form_with_read_only_work_context(): void
    {
        [$entry, $user] = $this->workEntry();

        $this->actingAs($user)->get(route('work-entries.evidence.create', $entry))
            ->assertOk()
            ->assertSee($entry->entry_code)
            ->assertSee($entry->project->project_code)
            ->assertSee($entry->task->task_code)
            ->assertSee($entry->subtask->subtask_code)
            ->assertDontSee('name="work_entry_id"', false)
            ->assertDontSee('name="project_id"', false);
    }

    public function test_another_staff_member_cannot_open_or_submit_to_an_owned_entry(): void
    {
        [$entry] = $this->workEntry();
        $other = $this->user();

        $this->actingAs($other)->get(route('work-entries.evidence.create', $entry))->assertForbidden();
        $this->actingAs($other)->post(route('work-entries.evidence.store', $entry), $this->filePayload())->assertForbidden();
        $this->assertDatabaseCount('work_evidences', 0);
    }

    public function test_valid_file_is_privately_stored_with_safe_metadata_and_authenticated_owner(): void
    {
        [$entry, $user] = $this->workEntry();

        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->filePayload())
            ->assertRedirect(route('work-entries.show', $entry));

        $evidence = WorkEvidence::firstOrFail();
        $this->assertSame('EVD-2026-0001', $evidence->evidence_code);
        $this->assertSame($entry->id, $evidence->work_entry_id);
        $this->assertSame($user->id, $evidence->user_id);
        $this->assertSame('report.pdf', $evidence->original_filename);
        $this->assertNotSame('report.pdf', $evidence->stored_filename);
        $this->assertNull($evidence->url);
        Storage::disk('local')->assertExists($evidence->file_path);
        $this->assertSame($entry->project_id, $evidence->workEntry->project_id);
        $this->assertSame($entry->task_id, $evidence->workEntry->task_id);
        $this->assertSame($entry->subtask_id, $evidence->workEntry->subtask_id);
        $this->assertDatabaseHas('work_entry_activities', [
            'work_entry_id' => $entry->id,
            'user_id' => $user->id,
            'event' => 'evidence_added',
            'description' => "{$evidence->evidence_code} — {$evidence->title}",
        ]);
    }

    public function test_valid_http_url_is_stored_without_file_metadata(): void
    {
        [$entry, $user] = $this->workEntry();

        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->linkPayload())
            ->assertRedirect(route('work-entries.show', $entry));

        $this->assertDatabaseHas('work_evidences', [
            'work_entry_id' => $entry->id,
            'user_id' => $user->id,
            'type' => 'link',
            'url' => 'https://library.example.ac.ug/page',
            'file_path' => null,
            'original_filename' => null,
        ]);
        $this->assertDatabaseHas('work_entry_activities', [
            'work_entry_id' => $entry->id,
            'user_id' => $user->id,
            'event' => 'evidence_added',
            'description' => 'EVD-2026-0001 — Updated Library Website',
        ]);
    }

    public function test_malformed_and_dangerous_urls_are_rejected(): void
    {
        [$entry, $user] = $this->workEntry();

        foreach (['not a url', 'javascript:alert(1)', 'data:text/html,test', 'file:///etc/passwd', 'ftp://example.com/file'] as $url) {
            $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->linkPayload(['url' => $url]))
                ->assertSessionHasErrors('url');
        }

        $this->assertDatabaseCount('work_evidences', 0);
    }

    public function test_conditional_fields_title_file_type_and_size_are_validated(): void
    {
        [$entry, $user] = $this->workEntry();

        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), ['type' => 'file', 'title' => 'Missing'])->assertSessionHasErrors('evidence_file');
        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), ['type' => 'link', 'title' => 'Missing'])->assertSessionHasErrors('url');
        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->filePayload(['title' => '']))->assertSessionHasErrors('title');
        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->filePayload(['evidence_file' => UploadedFile::fake()->create('script.exe', 20, 'application/x-msdownload')]))->assertSessionHasErrors('evidence_file');
        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->filePayload(['evidence_file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')]))->assertSessionHasErrors('evidence_file');

        $this->assertDatabaseCount('work_evidences', 0);
    }

    public function test_one_entry_supports_mixed_and_multiple_evidence(): void
    {
        [$entry, $user] = $this->workEntry();

        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->filePayload());
        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->linkPayload());
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->filePayload([
            'title' => 'Screenshot',
            'evidence_file' => UploadedFile::fake()->createWithContent('screen.png', $png),
        ]));

        $this->assertSame(3, $entry->evidences()->count());
        $this->assertSame(['file', 'link'], $entry->evidences()->distinct()->orderBy('type')->pluck('type')->all());
    }

    public function test_owner_can_view_download_and_remove_file_but_another_staff_cannot(): void
    {
        [$entry, $user] = $this->workEntry();
        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->filePayload());
        $evidence = WorkEvidence::firstOrFail();
        $other = $this->user();

        $this->actingAs($user)->get(route('work-evidence.show', $evidence))->assertOk();
        $this->actingAs($user)->get(route('work-evidence.download', $evidence))->assertDownload('report.pdf');
        $this->actingAs($other)->get(route('work-evidence.show', $evidence))->assertForbidden();
        $this->actingAs($other)->delete(route('work-evidence.destroy', $evidence))->assertForbidden();

        $path = $evidence->file_path;
        $this->actingAs($user)->delete(route('work-evidence.destroy', $evidence))->assertRedirect(route('work-entries.show', $entry));
        $this->assertDatabaseMissing('work_evidences', ['id' => $evidence->id]);
        $this->assertDatabaseHas('work_entry_activities', [
            'work_entry_id' => $entry->id,
            'user_id' => $user->id,
            'event' => 'evidence_removed',
            'description' => "{$evidence->evidence_code} — {$evidence->title}",
        ]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_missing_file_is_handled_without_crashing(): void
    {
        [$entry, $user] = $this->workEntry();
        $evidence = $this->evidence($entry, $user, ['file_path' => 'evidence/missing.pdf']);

        $this->actingAs($user)->from(route('work-entries.show', $entry))->get(route('work-evidence.show', $evidence))
            ->assertRedirect(route('work-entries.show', $entry))
            ->assertSessionHasErrors(['evidence' => 'Evidence file is unavailable.']);
    }

    public function test_direct_task_evidence_and_all_progress_values_remain_independent(): void
    {
        [$entry, $user] = $this->workEntry(false);
        $before = [$entry->duration_minutes, $entry->task->progress_percentage, $entry->project->progress_percentage];

        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->linkPayload())->assertRedirect();

        $this->assertSame($before, [$entry->fresh()->duration_minutes, $entry->task->fresh()->progress_percentage, $entry->project->fresh()->progress_percentage]);
        $this->assertNull(WorkEvidence::firstOrFail()->workEntry->subtask_id);
    }

    public function test_operational_role_names_do_not_control_owned_evidence_access(): void
    {
        foreach (['Staff', 'Campus Librarian', 'Intern', 'M&E Officer', 'University Librarian'] as $role) {
            [$entry, $user] = $this->workEntry(false, $role);
            $user->givePermissionTo('upload evidence');

            $this->actingAs($user)->get(route('work-entries.show', $entry))
                ->assertOk()
                ->assertSee('+ Add Evidence');
            $this->actingAs($user)->get(route('work-entries.evidence.create', $entry))
                ->assertOk()
                ->assertSee('File Upload')
                ->assertSee('Link / URL');
        }
    }

    public function test_owner_without_upload_permission_cannot_add_evidence(): void
    {
        [$entry, $user] = $this->workEntry(false, 'M&E Officer');

        $this->assertFalse($user->can('upload evidence'));
        $this->actingAs($user)->get(route('work-entries.show', $entry))->assertOk()->assertDontSee('+ Add Evidence');
        $this->actingAs($user)->get(route('work-entries.evidence.create', $entry))->assertForbidden();
        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->linkPayload())->assertForbidden();
    }

    public function test_pending_review_and_completed_work_lock_evidence_changes(): void
    {
        foreach (['pending_review', 'completed'] as $status) {
            [$entry, $user] = $this->workEntry(false);
            $entry->task->update(['status' => $status]);
            $evidence = $this->evidence($entry, $user, ['evidence_code' => 'EVD-LOCK-'.strtoupper($status)]);

            $this->actingAs($user)->get(route('work-entries.show', $entry))
                ->assertOk()
                ->assertSee($evidence->evidence_code)
                ->assertDontSee('+ Add Evidence')
                ->assertDontSee('Remove this evidence?');
            $this->actingAs($user)->get(route('work-entries.evidence.create', $entry))->assertForbidden();
            $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->linkPayload())->assertForbidden();
            $this->actingAs($user)->delete(route('work-evidence.destroy', $evidence))->assertForbidden();
        }
    }

    public function test_returned_for_correction_work_unlocks_evidence_changes(): void
    {
        [$entry, $user] = $this->workEntry(false);
        $entry->task->update(['status' => 'in_progress', 'returned_at' => now()]);

        $this->actingAs($user)->get(route('work-entries.show', $entry))->assertOk()->assertSee('+ Add Evidence');
        $this->actingAs($user)->get(route('work-entries.evidence.create', $entry))->assertOk();
        $this->actingAs($user)->post(route('work-entries.evidence.store', $entry), $this->linkPayload())
            ->assertRedirect(route('work-entries.show', $entry));
    }

    public function test_work_entry_creation_records_history_without_changing_deliverable_or_progress(): void
    {
        [$entry] = $this->workEntry();
        $entry->update(['output_deliverable' => 'Updated training presentation']);
        $before = [$entry->task->progress_percentage, $entry->subtask->progress_percentage, $entry->project->progress_percentage];

        $activity = $entry->activities()->where('event', 'work_entry_created')->firstOrFail();

        $this->assertSame($entry->user_id, $activity->user_id);
        $this->assertSame('Updated training presentation', $entry->fresh()->output_deliverable);
        $this->assertSame($before, [$entry->task->fresh()->progress_percentage, $entry->subtask->fresh()->progress_percentage, $entry->project->fresh()->progress_percentage]);

        [$directEntry] = $this->workEntry(false);
        $this->assertNull($directEntry->subtask_id);
        $this->assertTrue($directEntry->activities()->where('event', 'work_entry_created')->exists());
    }

    public function test_history_visibility_follows_existing_work_entry_policy(): void
    {
        [$entry, $owner] = $this->workEntry(false);
        $supervisor = $this->user('Campus Librarian');
        StaffProfile::create([
            'user_id' => $owner->id,
            'staff_number' => 'HISTORY-'.$owner->id,
            'supervisor_id' => $supervisor->id,
            'status' => 'active',
        ]);
        $unrelated = $this->user();

        $this->actingAs($owner)->get(route('work-entries.show', $entry))
            ->assertOk()->assertSee('Work Entry History')->assertSee('Daily Work Entry Created');
        $this->actingAs($supervisor)->get(route('work-entries.show', $entry))
            ->assertOk()->assertSee('Work Entry History')->assertSee('Daily Work Entry Created');
        $this->actingAs($unrelated)->get(route('work-entries.show', $entry))->assertForbidden();

        $entry->task->update(['status' => 'completed']);
        $this->actingAs($owner)->get(route('work-entries.show', $entry))
            ->assertOk()->assertSee('Daily Work Entry Created')->assertDontSee('+ Add Evidence');
    }

    private function workEntry(bool $withSubtask = true, string $role = 'Staff'): array
    {
        static $sequence = 0;
        $sequence++;
        $user = $this->user($role);
        $owner = User::factory()->create(['account_status' => 'active']);
        $owner->assignRole('Administrator');
        $project = Project::create(['project_code' => 'PRJ-EVD-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'title' => 'Evidence Project', 'project_category_id' => ProjectCategory::firstOrFail()->id, 'owner_id' => $owner->id, 'created_by' => $owner->id, 'start_date' => '2026-01-01', 'due_date' => '2026-12-31', 'scope' => 'university_wide', 'priority_level' => 'medium', 'progress_method' => 'manual', 'progress_percentage' => 30, 'status' => 'in_progress', 'is_active' => true]);
        $project->members()->attach($user, ['joined_at' => now(), 'is_active' => true]);
        $task = Task::create(['task_code' => 'TSK-EVD-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'project_id' => $project->id, 'title' => 'Evidence Task', 'created_by' => $owner->id, 'assigned_by' => $owner->id, 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => 40, 'is_active' => true]);
        $task->assignees()->attach($user, ['assigned_at' => now(), 'is_active' => true]);
        $subtask = $withSubtask ? Subtask::create(['subtask_code' => 'SUB-EVD-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'task_id' => $task->id, 'title' => 'Evidence Subtask', 'created_by' => $owner->id, 'assigned_to' => $user->id, 'priority' => 'medium', 'status' => 'in_progress', 'progress_percentage' => 25, 'is_active' => true]) : null;
        $entry = WorkEntry::create(['entry_code' => 'WEN-EVD-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT), 'user_id' => $user->id, 'project_id' => $project->id, 'task_id' => $task->id, 'subtask_id' => $subtask?->id, 'work_date' => '2026-08-16', 'start_time' => '09:00', 'end_time' => '12:30', 'duration_minutes' => 210, 'work_description' => 'Prepared evidence for the recorded work session.']);

        return [$entry, $user];
    }

    private function filePayload(array $overrides = []): array
    {
        return array_merge(['type' => 'file', 'title' => 'Training report', 'description' => 'Supporting document.', 'evidence_file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf')], $overrides);
    }

    private function linkPayload(array $overrides = []): array
    {
        return array_merge(['type' => 'link', 'title' => 'Updated Library Website', 'description' => 'Deployed work output.', 'url' => 'https://library.example.ac.ug/page'], $overrides);
    }

    private function evidence(WorkEntry $entry, User $user, array $overrides = []): WorkEvidence
    {
        return WorkEvidence::create(array_merge(['evidence_code' => 'EVD-2026-9999', 'work_entry_id' => $entry->id, 'user_id' => $user->id, 'type' => 'file', 'title' => 'Missing evidence', 'file_path' => 'evidence/file.pdf', 'original_filename' => 'file.pdf', 'stored_filename' => 'file.pdf', 'mime_type' => 'application/pdf', 'file_extension' => 'pdf', 'file_size' => 100], $overrides));
    }

    private function user(string $role = 'Staff'): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole($role);

        return $user;
    }
}
