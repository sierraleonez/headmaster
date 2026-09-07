<?php

namespace App\Http\Controllers;

use App\Models\LogEntry;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogEntryController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'logged_on' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        LogEntry::create([...$validated, 'project_id' => $project->id]);

        return back();
    }

    public function update(Request $request, LogEntry $entry): RedirectResponse
    {
        $this->authorize('update', $entry);

        $validated = $request->validate([
            'logged_on' => ['sometimes', 'date'],
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        $entry->update($validated);

        return back();
    }

    public function destroy(LogEntry $entry): RedirectResponse
    {
        $this->authorize('delete', $entry);

        $entry->delete();

        return back();
    }
}
