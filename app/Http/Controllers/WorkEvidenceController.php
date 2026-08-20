<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkEvidenceRequest;
use App\Models\WorkEntry;
use App\Models\WorkEvidence;
use App\Services\WorkEvidenceCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class WorkEvidenceController extends Controller
{
    public function create(WorkEntry $workEntry): View
    {
        Gate::authorize('addEvidence', $workEntry);
        $workEntry->load(['project', 'task', 'subtask']);

        return view('work-evidences.create', compact('workEntry'));
    }

    public function store(StoreWorkEvidenceRequest $request, WorkEntry $workEntry, WorkEvidenceCodeService $codes): RedirectResponse
    {
        $validated = $request->validated();
        $file = $request->file('evidence_file');
        $path = null;

        if ($file) {
            $path = $file->store('evidence/'.now()->year, 'local');

            abort_if(! $path, 500, 'The evidence file could not be stored.');
        }

        try {
            $codes->withNextCode(fn (string $evidenceCode) => DB::transaction(function () use ($evidenceCode, $workEntry, $request, $validated, $path, $file) {
                $evidence = WorkEvidence::create([
                    'evidence_code' => $evidenceCode,
                    'work_entry_id' => $workEntry->id,
                    'user_id' => $request->user()->id,
                    'type' => $validated['type'],
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'file_path' => $path,
                    'original_filename' => $file?->getClientOriginalName(),
                    'stored_filename' => $path ? basename($path) : null,
                    'mime_type' => $file?->getMimeType(),
                    'file_extension' => $file?->extension(),
                    'file_size' => $file?->getSize(),
                    'url' => $validated['type'] === 'link' ? $validated['url'] : null,
                ]);

                $workEntry->activities()->create([
                    'user_id' => $request->user()->id,
                    'event' => 'evidence_added',
                    'description' => "{$evidence->evidence_code} — {$evidence->title}",
                    'metadata' => [
                        'evidence_code' => $evidence->evidence_code,
                        'title' => $evidence->title,
                        'type' => $evidence->type,
                    ],
                ]);
            }));
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return redirect()->route('work-entries.show', $workEntry)->with('success', 'Evidence added successfully.');
    }

    public function show(WorkEvidence $workEvidence)
    {
        Gate::authorize('view', $workEvidence);
        abort_unless($workEvidence->type === 'file', 404);

        if (! $workEvidence->file_path || ! Storage::disk('local')->exists($workEvidence->file_path)) {
            Log::warning('Evidence file is unavailable.', ['evidence_code' => $workEvidence->evidence_code]);

            return back()->withErrors(['evidence' => 'Evidence file is unavailable.']);
        }

        return Storage::disk('local')->response($workEvidence->file_path, $this->safeDownloadName($workEvidence), [
            'Content-Type' => $workEvidence->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(WorkEvidence $workEvidence)
    {
        Gate::authorize('view', $workEvidence);
        abort_unless($workEvidence->type === 'file', 404);

        if (! $workEvidence->file_path || ! Storage::disk('local')->exists($workEvidence->file_path)) {
            Log::warning('Evidence file is unavailable.', ['evidence_code' => $workEvidence->evidence_code]);

            return back()->withErrors(['evidence' => 'Evidence file is unavailable.']);
        }

        return Storage::disk('local')->download($workEvidence->file_path, $this->safeDownloadName($workEvidence), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(WorkEvidence $workEvidence): RedirectResponse
    {
        Gate::authorize('delete', $workEvidence);
        $workEntry = $workEvidence->workEntry;
        $path = $workEvidence->file_path;

        DB::transaction(function () use ($workEvidence, $workEntry) {
            $workEntry->activities()->create([
                'user_id' => auth()->id(),
                'event' => 'evidence_removed',
                'description' => "{$workEvidence->evidence_code} — {$workEvidence->title}",
                'metadata' => [
                    'evidence_code' => $workEvidence->evidence_code,
                    'title' => $workEvidence->title,
                    'type' => $workEvidence->type,
                ],
            ]);
            $workEvidence->delete();
        });

        if ($path && ! Storage::disk('local')->delete($path)) {
            Log::warning('Evidence record was deleted but its file could not be removed.', [
                'evidence_code' => $workEvidence->evidence_code,
                'file_path' => $path,
            ]);
        }

        return redirect()->route('work-entries.show', $workEntry)->with('success', 'Evidence removed successfully.');
    }

    private function safeDownloadName(WorkEvidence $workEvidence): string
    {
        $name = basename(str_replace('\\', '/', $workEvidence->original_filename ?? 'evidence-file'));

        return str_replace(["\r", "\n", '"'], '', $name) ?: 'evidence-file';
    }
}
