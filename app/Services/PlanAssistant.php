<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Turns a plan written in prose into a draft project tree, via OpenRouter.
 *
 * The assistant never writes to the database: it returns a draft that the user
 * edits and explicitly applies.
 */
class PlanAssistant
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
    You are the planning assistant inside HeadMaster, a personal tracker built on nested kanban boards.

    The user describes a plan (a study plan, a workout plan, a research plan, anything).
    Your job is to map it onto HeadMaster's structure so they do not have to create cards by hand.

    Structure:
    - A "board" is a kanban board with named columns; each column holds cards.
    - A card is either a "note" (a title plus optional markdown body) or a "subproject"
      (a card that opens into a nested board or log). Subprojects nest without limit.
    - A "log" is a dated list of entries, for things you record as you do them (a workout log).

    Reply with a single JSON object and nothing else:

    {
      "message": "one or two short sentences to the user about what you drafted",
      "plan": {
        "name": "project name",
        "kind": "board" | "log",
        "description": "optional short description",
        "columns": ["Backlog", "Todo", "In Progress", "Blocked", "Canceled", "Done"],
        "items": [
          {
            "title": "card title",
            "body": "optional markdown body",
            "column": "Backlog",
            "type": "note" | "subproject",
            "project": { ...another plan object, required when type is "subproject"... }
          }
        ],
        "entries": [
          { "logged_on": "2026-01-31", "title": "entry title", "body": "optional markdown" }
        ]
      }
    }

    Rules:
    - "columns" and "items" apply to kind "board"; "entries" applies to kind "log". Omit what does not apply.
    - Keep the default columns (Backlog, Todo, In Progress, Blocked, Canceled, Done) unless the user asks otherwise.
    - Put every card in "Backlog" unless the user says work is already underway or finished.
    - Use subprojects for parts of the plan large enough to need their own board (a module, a phase, a mesocycle).
    - Use a log for anything the user records repeatedly over time rather than moves through stages.
    - Set "plan" to null when the user is only asking a question or the request is too vague to map.

    A long plan may reach you condensed to its headings and list items - its structure without
    the prose around it. Map that structure:
    - One card per phase, module or block, in the order given.
    - Nest at most two levels deep. Summarise what a phase holds in its "body", briefly; do not
      copy the outline back verbatim.
    - It is always fine to leave a phase as one card. The user expands the detail later by
      opening that card's sub-project and asking you there.
    PROMPT;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{message: string, plan: array<string, mixed>|null}
     */
    public function draft(array $messages, ?string $targetName = null): array
    {
        $key = config('services.openrouter.key');

        if (blank($key)) {
            throw new RuntimeException(
                'No OpenRouter API key configured. Add OPENROUTER_API_KEY to your .env file to use the assistant.'
            );
        }

        $system = self::SYSTEM_PROMPT;

        if ($targetName) {
            $system .= "\n\nThe draft will be created inside the board \"{$targetName}\".";
        }

        $timeout = max(30, (int) config('services.openrouter.timeout'));

        try {
            $response = Http::withToken($key)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ])
                ->connectTimeout(15)
                ->timeout($timeout)
                ->post(rtrim((string) config('services.openrouter.base_url'), '/').'/chat/completions', [
                    'model' => config('services.openrouter.model'),
                    'response_format' => ['type' => 'json_object'],
                    // Thinking tokens double the wait for no gain here: the
                    // answer is a structured plan, not a hard question.
                    'reasoning' => ['enabled' => false],
                    // Drafting is bounded by how fast tokens come back, so
                    // take the quickest provider carrying this model.
                    'provider' => ['sort' => 'throughput'],
                    'max_tokens' => max(1000, (int) config('services.openrouter.max_tokens')),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ...$messages,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                str_contains($exception->getMessage(), 'imed out')
                    ? "The assistant was still writing after {$timeout} seconds. Very large plans are "
                        .'slow to draft in one go - try one phase at a time, and expand each part from inside it.'
                    : 'Could not reach OpenRouter: '.mb_substr($exception->getMessage(), 0, 200),
                previous: $exception,
            );
        }

        if ($response->failed()) {
            $detail = $response->json('error.message') ?? $response->body();

            throw new RuntimeException('The assistant could not be reached: '.mb_substr((string) $detail, 0, 300));
        }

        if ($response->json('choices.0.finish_reason') === 'length') {
            throw new RuntimeException(
                'The draft outgrew the reply limit before it was finished. Ask for one phase at '
                .'a time, or for a shorter outline, then expand each part from inside it.'
            );
        }

        $content = (string) $response->json('choices.0.message.content', '');
        $decoded = $this->decode($content);

        return [
            'message' => is_string($decoded['message'] ?? null) && $decoded['message'] !== ''
                ? $decoded['message']
                : 'Here is a draft.',
            'plan' => is_array($decoded['plan'] ?? null) ? $decoded['plan'] : null,
        ];
    }

    /**
     * Models occasionally wrap JSON in prose or a fenced block.
     *
     * @return array<string, mixed>
     */
    private function decode(string $content): array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['message' => trim($content) ?: 'The assistant returned an unreadable reply.', 'plan' => null];
    }
}
