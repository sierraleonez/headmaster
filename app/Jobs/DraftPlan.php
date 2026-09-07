<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Services\PlanApplier;
use App\Services\PlanAssistant;
use App\Services\PlanInput;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Ask the model for a draft plan, off the web request.
 *
 * A round trip regularly runs past PHP's max_execution_time, so the reply is
 * written here instead: the controller saves an empty pending message, this
 * job fills it in, and the page polls until it is ready.
 */
class DraftPlan implements ShouldQueue
{
    use Queueable;

    /**
     * Outlives the HTTP call, which gives up first with a readable message.
     * Read at construction: the worker takes this from the queued payload.
     */
    public int $timeout;

    public int $tries = 1;

    public function __construct(
        private readonly int $messageId,
        private readonly ?string $targetName = null,
    ) {
        $this->timeout = max(60, (int) config('services.openrouter.timeout')) + 60;
    }

    public function handle(PlanAssistant $assistant, PlanApplier $applier): void
    {
        $message = ChatMessage::find($this->messageId);

        if (! $message || $message->status !== ChatMessage::STATUS_PENDING) {
            return;
        }

        try {
            $draft = $assistant->draft($this->history($message), $this->targetName);

            $message->update([
                'status' => ChatMessage::STATUS_READY,
                'content' => $draft['message'],
                'plan' => $draft['plan'] ? $applier->normalise($draft['plan']) : null,
            ]);
        } catch (Throwable $exception) {
            $this->fail($message, $exception->getMessage());
        }

        $message->conversation->touch();
    }

    public function failed(?Throwable $exception): void
    {
        $message = ChatMessage::find($this->messageId);

        if ($message && $message->status === ChatMessage::STATUS_PENDING) {
            $this->fail($message, $exception?->getMessage() ?? 'The assistant did not reply.');
        }
    }

    private function fail(ChatMessage $message, string $reason): void
    {
        $message->update([
            'status' => ChatMessage::STATUS_FAILED,
            'content' => $reason,
        ]);
    }

    /**
     * Everything said before this reply, with long pastes reduced to their
     * structure. A failed attempt carries no answer, so it is left out rather
     * than fed back to the model.
     *
     * @return list<array{role: string, content: string}>
     */
    private function history(ChatMessage $message): array
    {
        return array_values(
            ChatMessage::where('conversation_id', $message->conversation_id)
                ->where('id', '<', $message->id)
                ->where(fn ($query) => $query
                    ->where('role', 'user')
                    ->orWhere('status', ChatMessage::STATUS_READY))
                ->orderBy('id')
                ->get()
                ->map(fn (ChatMessage $entry) => [
                    'role' => $entry->role,
                    'content' => $entry->role === 'user'
                        ? PlanInput::condense((string) $entry->content)
                        : (string) $entry->content,
                ])
                ->all()
        );
    }
}
