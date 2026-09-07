import { Link, router } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy, show, update } from '@/routes/projects';
import type { ProjectPayload, TrailStep } from '@/types';

/**
 * Breadcrumb trail down the project tree, plus renaming and deletion.
 */
export function ProjectHeader({
    project,
    trail,
}: {
    project: ProjectPayload;
    trail: TrailStep[];
}) {
    const [name, setName] = useState(project.name);
    const [renaming, setRenaming] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    useEffect(() => {
        setName(project.name);
        setRenaming(false);
    }, [project]);

    useEffect(() => {
        if (renaming) {
            input.current?.select();
        }
    }, [renaming]);

    const rename = () => {
        setRenaming(false);

        if (name.trim() && name.trim() !== project.name) {
            router.patch(
                update.url(project.id),
                { name: name.trim() },
                { preserveScroll: true },
            );

            return;
        }

        setName(project.name);
    };

    const ancestors = trail.slice(0, -1);

    return (
        <div className="flex items-start justify-between gap-4 px-4 pt-4 pb-3">
            <div className="min-w-0">
                {ancestors.length > 0 && (
                    <nav className="text-muted-foreground flex flex-wrap items-center gap-0.5 text-xs">
                        {ancestors.map((step) => (
                            <span
                                key={step.id}
                                className="flex items-center gap-0.5"
                            >
                                <Link
                                    href={show.url(step.id)}
                                    className="hover:text-foreground max-w-40 truncate transition-colors"
                                >
                                    {step.name}
                                </Link>
                                <ChevronRight className="size-3" />
                            </span>
                        ))}
                    </nav>
                )}

                {renaming ? (
                    <input
                        ref={input}
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        onBlur={rename}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                rename();
                            }

                            if (event.key === 'Escape') {
                                setName(project.name);
                                setRenaming(false);
                            }
                        }}
                        className="focus-visible:ring-ring w-full max-w-md rounded-sm bg-transparent text-xl font-semibold tracking-tight outline-none focus-visible:ring-1"
                    />
                ) : (
                    <h1
                        onDoubleClick={() => setRenaming(true)}
                        title="Double-click to rename"
                        className="truncate text-xl font-semibold tracking-tight"
                    >
                        {project.name}
                    </h1>
                )}

                <p className="text-muted-foreground mt-0.5 text-xs">
                    {project.kind === 'log' ? 'Item log' : 'Kanban board'}
                    {project.is_root && ' · your top level'}
                </p>
            </div>

            <div className="flex shrink-0 items-center gap-2">
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => setRenaming(true)}
                >
                    Rename
                </Button>

                {!project.is_root && (
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setConfirming(true)}
                    >
                        Delete
                    </Button>
                )}
            </div>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete "{project.name}"?</DialogTitle>
                        <DialogDescription>
                            This removes the{' '}
                            {project.kind === 'log' ? 'log' : 'board'}, its card
                            in the parent board, and every sub-project
                            underneath it.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirming(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(destroy.url(project.id))
                            }
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
