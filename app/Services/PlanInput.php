<?php

namespace App\Services;

/**
 * Reduces a pasted plan to the part worth mapping.
 *
 * People paste whole documents - rationale, scope notes, who it is for, why it
 * is worth doing. None of that becomes a card, but all of it is read by the
 * model, which makes drafting slow enough to time out. The structure of a plan
 * lives in its headings and its lists, so that is what gets sent.
 *
 * The user's own message is stored and shown in full; only the copy handed to
 * the model is condensed.
 */
class PlanInput
{
    /**
     * Anything shorter than this is already focused enough to send as-is.
     */
    public const CONDENSE_ABOVE = 3000;

    /**
     * Hard ceiling on what is sent, after condensing.
     */
    public const MAX_CHARS = 8000;

    private const MAX_LINE = 220;

    public static function condense(string $text): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= self::CONDENSE_ABOVE) {
            return $text;
        }

        $kept = self::structureOf($text);

        // A document with no headings or lists is all prose; there is nothing
        // to pull out of it, so send the opening of it and let the model read.
        if (count($kept) < 3) {
            return self::clip($text, self::MAX_CHARS);
        }

        return self::clip(implode("\n", self::dropEmptySections($kept)), self::MAX_CHARS);
    }

    /**
     * Headings and list items, in order, with the prose between them dropped.
     *
     * @return list<array{level: int, text: string}>
     */
    private static function structureOf(string $text): array
    {
        $kept = [];
        $inFence = false;

        foreach (preg_split('/\R/', $text) ?: [] as $raw) {
            $line = rtrim($raw);
            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '```') || str_starts_with($trimmed, '~~~')) {
                $inFence = ! $inFence;

                continue;
            }

            if ($inFence || $trimmed === '') {
                continue;
            }

            // A heading: the spine of the plan.
            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $match)) {
                $heading = self::clean($match[2]);

                if ($heading !== '') {
                    $kept[] = ['level' => mb_strlen($match[1]), 'text' => str_repeat('#', mb_strlen($match[1])).' '.$heading];
                }

                continue;
            }

            // A list item, bulleted or numbered, at whatever indent.
            if (preg_match('/^([-*+]|\d+[.)])\s+(.*)$/', $trimmed, $match)) {
                $item = self::clean($match[2]);

                if ($item === '') {
                    continue;
                }

                $indent = mb_strlen($line) - mb_strlen($trimmed);
                $kept[] = ['level' => 7, 'text' => str_repeat(' ', min(4, intdiv($indent, 2) * 2)).'- '.$item];

                continue;
            }

            // A table row carries a schedule often enough to be worth keeping.
            if (str_starts_with($trimmed, '|') && ! preg_match('/^\|[\s|:-]+\|$/', $trimmed)) {
                $cells = array_values(array_filter(
                    array_map(fn (string $cell) => self::clean($cell), explode('|', trim($trimmed, '|'))),
                    fn (string $cell) => $cell !== '',
                ));

                if ($cells !== []) {
                    $kept[] = ['level' => 7, 'text' => '- '.implode(' - ', $cells)];
                }
            }

            // Everything else is prose: background, rationale, asides. Dropped.
        }

        return $kept;
    }

    /**
     * Remove headings that ended up with nothing under them.
     *
     * @param  list<array{level: int, text: string}>  $kept
     * @return list<string>
     */
    private static function dropEmptySections(array $kept): array
    {
        $lines = [];

        foreach ($kept as $index => $entry) {
            if ($entry['level'] < 7) {
                $next = $kept[$index + 1] ?? null;

                // A heading immediately followed by one of the same or higher
                // rank held only prose.
                if ($next !== null && $next['level'] <= $entry['level']) {
                    continue;
                }

                if ($next === null) {
                    continue;
                }
            }

            $lines[] = $entry['text'];
        }

        return $lines;
    }

    /**
     * Strip markdown emphasis and links down to their text, and cap the length.
     */
    private static function clean(string $line): string
    {
        $line = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $line) ?? $line;
        $line = preg_replace('/[*_`]+/', '', $line) ?? $line;
        $line = preg_replace('/\s+/', ' ', $line) ?? $line;

        return self::clip(trim($line), self::MAX_LINE);
    }

    private static function clip(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $boundary = mb_strrpos($cut, "\n");

        return rtrim($boundary !== false && $boundary > $limit * 0.5 ? mb_substr($cut, 0, $boundary) : $cut);
    }
}
