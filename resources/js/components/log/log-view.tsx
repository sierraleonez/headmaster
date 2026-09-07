import { router } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

import { ConfirmButton } from '@/components/confirm-button';
import { EntryDialog } from '@/components/log/entry-dialog';
import { Markdown } from '@/components/markdown';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/entries';
import type { LogEntry, ProjectPayload } from '@/types';

/**
 * An item log: a dated list of what actually happened, newest first.
 */
export function LogView({ project }: { project: ProjectPayload }) {
    const entries = project.entries ?? [];
    const [editing, setEditing] = useState<LogEntry | null>(null);
    const [open, setOpen] = useState(false);

    const compose = (entry: LogEntry | null) => {
        setEditing(entry);
        setOpen(true);
    };

    return (
        <div className="flex h-full w-full max-w-3xl flex-col gap-4 overflow-y-auto px-4 pb-8">
            <div>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => compose(null)}
                >
                    <Plus />
                    Add entry
                </Button>
            </div>

            {entries.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    Nothing logged yet.
                </p>
            ) : (
                <ol className="flex flex-col">
                    {entries.map((entry) => (
                        <li
                            key={entry.id}
                            className="group border-b py-3 last:border-b-0"
                        >
                            <div className="flex items-baseline gap-3">
                                <time
                                    dateTime={entry.logged_on}
                                    className="text-muted-foreground w-24 shrink-0 font-mono text-xs tabular-nums"
                                >
                                    {entry.logged_on}
                                </time>

                                <h3 className="min-w-0 flex-1 text-sm font-medium break-words">
                                    {entry.title}
                                </h3>

                                <div className="flex shrink-0 items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        className="size-7"
                                        aria-label="Edit entry"
                                        onClick={() => compose(entry)}
                                    >
                                        <Pencil className="size-3.5" />
                                    </Button>
                                    <ConfirmButton
                                        label="Delete"
                                        confirmLabel="Confirm"
                                        onConfirm={() =>
                                            router.delete(
                                                destroy.url(entry.id),
                                                { preserveScroll: true },
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            {entry.body && (
                                <div className="pt-1 pl-27">
                                    <Markdown>{entry.body}</Markdown>
                                </div>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            <EntryDialog
                projectId={project.id}
                entry={editing}
                open={open}
                onClose={() => setOpen(false)}
            />
        </div>
    );
}
