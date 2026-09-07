<?php

namespace Tests\Unit;

use App\Services\PlanInput;
use PHPUnit\Framework\TestCase;

class PlanInputTest extends TestCase
{
    /**
     * A document shaped like the ones people actually paste: a title, some
     * background, then the plan itself.
     */
    private function document(): string
    {
        $background = str_repeat(
            'This section explains at length why the plan is worth doing, who it is for, '
            ."and how many hours it is expected to take in total.\n\n",
            30,
        );

        return <<<MARKDOWN
        # Deep Systems Curriculum

        **For:** a working engineer who wants implementation-level understanding.

        {$background}

        ## Phase 0 - Sharpening

        Some prose about the phase and why it comes first.

        - Modern C: pointers, memory layout, [undefined behaviour](https://example.com)
        - Tooling: GDB, Valgrind, `perf`

        ## Rationale

        Only prose lives under this heading, so nothing here becomes a card.

        ## Phase 1 - Systems

        1. Data Lab - bit manipulation
        2. Bomb Lab - reverse-engineer x86-64
        MARKDOWN;
    }

    public function test_a_focused_message_is_sent_as_written(): void
    {
        $short = "Map my three week Postgres plan:\n- indexes\n- query planning\n- vacuum";

        $this->assertSame($short, PlanInput::condense($short));
    }

    public function test_headings_and_list_items_survive(): void
    {
        $condensed = PlanInput::condense($this->document());

        $this->assertStringContainsString('# Deep Systems Curriculum', $condensed);
        $this->assertStringContainsString('## Phase 0 - Sharpening', $condensed);
        $this->assertStringContainsString('## Phase 1 - Systems', $condensed);
        $this->assertStringContainsString('- Modern C: pointers', $condensed);
        $this->assertStringContainsString('- Data Lab - bit manipulation', $condensed);
    }

    public function test_background_prose_is_dropped(): void
    {
        $condensed = PlanInput::condense($this->document());

        $this->assertStringNotContainsString('why the plan is worth doing', $condensed);
        $this->assertStringNotContainsString('For:', $condensed);
        $this->assertStringNotContainsString('Some prose about the phase', $condensed);
        $this->assertLessThan(
            mb_strlen($this->document()) / 2,
            mb_strlen($condensed),
            'the condensed copy should be a fraction of the original',
        );
    }

    public function test_a_heading_with_only_prose_under_it_goes_too(): void
    {
        $this->assertStringNotContainsString('Rationale', PlanInput::condense($this->document()));
    }

    public function test_markdown_decoration_is_stripped_from_items(): void
    {
        $condensed = PlanInput::condense($this->document());

        $this->assertStringContainsString('undefined behaviour', $condensed);
        $this->assertStringNotContainsString('https://example.com', $condensed);
        $this->assertStringNotContainsString('`perf`', $condensed);
    }

    public function test_table_rows_are_kept_as_items(): void
    {
        $condensed = PlanInput::condense(
            "# Plan\n\n".str_repeat("Prose that is only here to make the document long.\n\n", 120)
            ."## Tracks\n\n| Track | Share |\n| --- | --- |\n| Systems | 60% |\n| Math | 25% |\n"
        );

        $this->assertStringContainsString('- Systems - 60%', $condensed);
        $this->assertStringNotContainsString('| --- |', $condensed);
    }

    public function test_the_result_is_capped(): void
    {
        $huge = "# Plan\n\n".str_repeat("## Phase\n\n- A task that is written out in full\n\n", 500);

        $this->assertLessThanOrEqual(
            PlanInput::MAX_CHARS,
            mb_strlen(PlanInput::condense($huge)),
        );
    }

    public function test_a_document_with_no_structure_is_clipped_not_emptied(): void
    {
        $prose = str_repeat('A long paragraph with no headings and no list items at all. ', 200);

        $condensed = PlanInput::condense($prose);

        $this->assertNotSame('', $condensed);
        $this->assertLessThanOrEqual(PlanInput::MAX_CHARS, mb_strlen($condensed));
        $this->assertStringStartsWith('A long paragraph', $condensed);
    }
}
