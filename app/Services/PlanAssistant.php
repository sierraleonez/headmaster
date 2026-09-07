<?php

namespace App\Services;

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
    - Prefer a handful of meaningful cards over dozens of trivial ones.
    - Set "plan" to null when the user is only asking a question or the request is too vague to map.
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

        $response = Http::withToken($key)
            ->withHeaders([
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])
            ->timeout(120)
            ->post(rtrim((string) config('services.openrouter.base_url'), '/').'/chat/completions', [
                'model' => config('services.openrouter.model'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ...$messages,
                ],
            ]);

        if ($response->failed()) {
            $detail = $response->json('error.message') ?? $response->body();

            throw new RuntimeException('The assistant could not be reached: '.mb_substr((string) $detail, 0, 300));
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
