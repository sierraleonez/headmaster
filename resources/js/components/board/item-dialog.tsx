import { Link, router } from '@inertiajs/react';
import {
    NotebookText,
    SquareArrowOutUpRight,
    SquareKanban,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { ConfirmButton } from '@/components/confirm-button';
import { MarkdownEditor } from '@/components/markdown-editor';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { destroy, update } from '@/routes/items';
import { show as showProject, store as storeProject } from '@/routes/projects';
import type { BoardItem } from '@/types';

/**
 * The card editor. A card is a note until it is turned into a sub-project,
 * at which point it opens into a board or a log of its own.
 */
export function ItemDialog({
    item,
    onClose,
}: {
    item: BoardItem | null;
    onClose: () => void;
}) {
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setTitle(item?.title ?? '');
        setBody(item?.body ?? '');
    }, [item]);

    if (!item) {
        return null;
    }

    const dirty = title !== item.title || body !== (item.body ?? '');

    const save = () => {
        if (!title.trim()) {
            return;
        }

        setSaving(true);

        router.patch(
            update.url(item.id),
            { title: title.trim(), body: body.trim() === '' ? null : body },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
                onSuccess: () => onClose(),
            },
        );
    };

    const makeSubproject = (kind: 'board' | 'log') => {
        router.post(storeProject.url(), {
            item_id: item.id,
            kind,
            name: title.trim() || item.title,
        });
    };

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="flex max-h-[85vh] flex-col gap-4 sm:max-w-2xl">
                <DialogHeader className="gap-1">
                    <DialogTitle className="sr-only">Edit card</DialogTitle>
                    <Input
                        value={title}
                        onChange={(event) => setTitle(event.target.value)}
                        placeholder="Untitled"
                        className="h-auto border-0 px-0 text-lg font-semibold shadow-none focus-visible:ring-0"
                    />
                    <DialogDescription className="text-xs">
                        {item.child
                            ? `Opens into a ${item.child.kind === 'log' ? 'log' : 'board'}.`
                            : 'A note. Turn it into a sub-project when it needs a board or a log of its own.'}
                    </DialogDescription>
                </DialogHeader>

                <MarkdownEditor
                    value={body}
                    onChange={setBody}
                    className="min-h-0 flex-1"
                />

                {item.child ? (
                    <Button variant="outline" asChild className="justify-start">
                        <Link href={showProject.url(item.child.id)}>
                            <SquareArrowOutUpRight />
                            Open {item.child.kind === 'log' ? 'log' : 'board'}
                        </Link>
                    </Button>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => makeSubproject('board')}
                        >
                            <SquareKanban />
                            Make it a board
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => makeSubproject('log')}
                        >
                            <NotebookText />
                            Make it a log
                        </Button>
                    </div>
                )}

                <DialogFooter className="sm:justify-between">
                    <ConfirmButton
                        label="Delete card"
                        confirmLabel={
                            item.child
                                ? 'Delete card and everything under it'
                                : 'Click again to delete'
                        }
                        onConfirm={() =>
                            router.delete(destroy.url(item.id), {
                                preserveScroll: true,
                                onSuccess: () => onClose(),
                            })
                        }
                    />

                    <div className="flex gap-2">
                        <Button variant="ghost" onClick={onClose}>
                            Close
                        </Button>
                        <Button
                            onClick={save}
                            disabled={saving || !dirty || !title.trim()}
                        >
                            Save
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
