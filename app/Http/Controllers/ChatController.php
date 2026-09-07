<?php

namespace App\Http\Controllers;

use App\Jobs\DraftPlan;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Project;
use App\Services\PlanApplier;
use App\Services\ProjectTree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        private readonly ProjectTree $tree,
        private readonly PlanApplier $applier,
    ) {}

    public function index(Request $request): Response
    {
        // ?new=1 opens a blank thread instead of resuming the last one.
        $conversation = $request->boolean('new')
            ? null
            : $request->user()->conversations()->latest('updated_at')->first();

        return $this->render($request, $conversation);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        return $this->render($request, $conversation);
    }

    /**
     * Send a message. The reply is drafted by a queued job, so the request
     * returns straight away and the page polls for the answer.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'content' => ['required', 'string', 'max:20000'],
            'target_project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $conversationId = $request->integer('conversation_id');

        $conversation = $conversationId > 0
            ? Conversation::findOrFail($conversationId)
            : null;

        if ($conversation) {
            $this->authorize('update', $conversation);
        } else {
            $conversation = $request->user()->conversations()->create([
                'title' => Str::limit($validated['content'], 48),
            ]);
        }

        $conversation->messages()->create([
            'role' => 'user',
            'status' => ChatMessage::STATUS_READY,
            'content' => $validated['content'],
        ]);

        $targetId = $request->integer('target_project_id');
        $target = $targetId > 0 ? Project::find($targetId) : null;

        if ($target && $target->user_id !== $request->user()->id) {
            $target = null;
        }

        $reply = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => ChatMessage::STATUS_PENDING,
        ]);

        $conversation->touch();

        DraftPlan::dispatch($reply->id, $target?->name);

        return redirect()->route('chat.show', $conversation);
    }

    /**
     * Create the reviewed draft for real. The plan posted here is the one the
     * user edited on screen, not necessarily what the model first proposed.
     */
    public function apply(Request $request, ChatMessage $message): RedirectResponse
    {
        $this->authorize('update', $message->conversation);

        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'column_id' => ['nullable', 'integer', 'exists:board_columns,id'],
            'plan' => ['required', 'array'],
        ]);

        $target = Project::findOrFail($request->integer('project_id'));

        $project = $this->applier->apply(
            $request->user(),
            $target,
            $validated['plan'],
            $validated['column_id'] ?? null,
        );

        $message->update([
            'plan' => $this->applier->normalise($validated['plan']),
            'applied_project_id' => $project->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Created "'.$project->name.'".',
        ]);

        return redirect()->route('projects.show', $project);
    }

    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return redirect()->route('chat.index');
    }

    private function render(Request $request, ?Conversation $conversation): Response
    {
        $conversation?->load('messages');

        return Inertia::render('chat', [
            'conversation' => $conversation ? [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $conversation->messages->map(fn (ChatMessage $message) => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'status' => $message->status,
                    'content' => $message->content,
                    'plan' => $message->plan,
                    'applied_project_id' => $message->applied_project_id,
                ])->values(),
            ] : null,
            'conversations' => $request->user()->conversations()
                ->latest('updated_at')
                ->get(['id', 'title'])
                ->map(fn (Conversation $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                ])->values(),
            'boards' => $this->tree->boardOptions($request->user()),
            'assistantReady' => filled(config('services.openrouter.key')),
        ]);
    }
}
