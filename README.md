# HeadMaster

A personal tracker for everything you are working through — a study plan, a
training block, a research project — so you can see where each one stands, what
is left, and what you have already done.

## The structure

Everything is one tree.

- You start on a **kanban board** with six columns: Backlog, Todo, In Progress,
  Blocked, Canceled, Done. Rename, reorder, add and delete them freely.
- Every **card** on a board is either
    - a **note** — a title plus a markdown body, for briefings, materials, or
      anything you want to drop somewhere; or
    - a **sub-project** — a card that opens into a board or a log of its own.
- A **sub-project** nests without limit: a board inside a board inside a board.
- An **item log** is the other kind of sub-project: a dated list of entries, for
  things you record as you go rather than move through stages — a workout log, a
  reading log, session notes.

Cards move anywhere, in any order. Nothing is enforced, nothing completes
itself; the board only records where you say things are.

## The assistant

`/chat` maps a plan you already have onto that structure, so you do not have to
create cards by hand. Paste the plan, pick the board it should live in, and it
comes back as a draft: a project name, its columns, its cards, and any nested
boards or logs. Edit anything in the draft — rename cards, move them between
columns, flip a note into a sub-project, drop what you don't want — then press
Create. Nothing is written to the database until you do.

Drafting happens on the queue, not during the web request — a plan regularly
takes longer than PHP's `max_execution_time`. The page shows the pending reply
and polls until it is ready.

A long paste is condensed to its headings and list items before it is sent
(`PlanInput`): background, rationale and scope notes never become cards, but
they are slow to read and slow to echo back. Your message is stored and shown
in full; only the copy handed to the model is trimmed. A 19,000-character
curriculum comes down to about 8,000 and drafts in around 40 seconds instead of
timing out.

It runs on [OpenRouter](https://openrouter.ai). Set `OPENROUTER_API_KEY` in
`.env`; `OPENROUTER_MODEL` defaults to `deepseek/deepseek-chat-v3.1`,
`OPENROUTER_TIMEOUT` to 300 seconds. Without a key the rest of the app works
normally and the assistant says what is missing.

## Stack

Laravel 13, Inertia 3 + React 19 in TypeScript, Tailwind 4, MySQL. Auth,
two-factor and passkeys come from the Laravel React starter kit.

## Running it

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate

# a MySQL database named "headmaster"
php artisan migrate

composer run dev     # server, vite and queue together
```

Registering an account creates its root board automatically.

## Checks

```bash
php artisan test                                    # feature tests (MySQL: headmaster_testing)
./vendor/bin/pint                                   # PHP formatting
./vendor/bin/phpstan analyse --memory-limit=1G      # level 7
npm run check                                       # lint + formatting
npm run types:check                                 # tsc
```

## How it fits together

| Where                                          | What                                                                                                                                                   |
| ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `app/Models/Project.php`                       | A board or a log. `parent_item_id` is the card it hangs off; null means a root board.                                                                  |
| `app/Services/ProjectTree.php`                 | Creating, deleting and reading the tree. Deletion walks the subtree by hand — projects → items → columns → projects is a cycle MySQL will not cascade. |
| `app/Services/PlanAssistant.php`               | Asks OpenRouter for a draft plan as JSON.                                                                                                              |
| `app/Services/PlanApplier.php`                 | Normalises a draft and materialises it into real projects, columns, cards and entries.                                                                 |
| `resources/js/components/board/`               | The kanban board, its columns, cards and the card editor.                                                                                              |
| `resources/js/components/chat/plan-editor.tsx` | The editable draft, nested to match the plan.                                                                                                          |
