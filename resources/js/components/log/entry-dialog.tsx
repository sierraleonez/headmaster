import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/entries';
import type { LogEntry } from '@/types';

/** Add or edit one dated entry: a session, a reading, a measurement. */
export function EntryDialog({
    projectId,
    entry,
    open,
    onClose,
}: {
    projectId: number;
    entry: LogEntry | null;
    open: boolean;
    onClose: () => void;
}) {
    const [loggedOn, setLoggedOn] = useState(today());
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        setLoggedOn(entry?.logged_on ?? today());
        setTitle(entry?.title ?? '');
        setBody(entry?.body ?? '');
    }, [open, entry]);

    const save = () => {
        if (!title.trim()) {
            return;
        }

        const payload = {
            logged_on: loggedOn,
            title: title.trim(),
            body: body.trim() === '' ? null : body,
        };

        setSaving(true);

        const options = {
            preserveScroll: true,
            onFinish: () => setSaving(false),
            onSuccess: () => onClose(),
        };

        if (entry) {
            router.patch(update.url(entry.id), payload, options);

            return;
        }

        router.post(store.url(projectId), payload, options);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent className="flex max-h-[85vh] flex-col gap-4 sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {entry ? 'Edit entry' : 'New entry'}
                    </DialogTitle>
                    <DialogDescription>
                        What you did, when you did it.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3 sm:grid-cols-[10rem_1fr]">
                    <div className="grid gap-1.5">
                        <Label htmlFor="logged_on">Date</Label>
                        <Input
                            id="logged_on"
                            type="date"
                            value={loggedOn}
                            onChange={(event) =>
                                setLoggedOn(event.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="entry_title">Title</Label>
                        <Input
                            id="entry_title"
                            value={title}
                            autoFocus
                            placeholder="Push day / Chapter 3 / Interview notes"
                            onChange={(event) => setTitle(event.target.value)}
                        />
                    </div>
                </div>

                <MarkdownEditor
                    value={body}
                    onChange={setBody}
                    className="min-h-0 flex-1"
                    placeholder="Sets, reps, notes, links. Markdown."
                />

                <DialogFooter>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={save} disabled={saving || !title.trim()}>
                        {entry ? 'Save' : 'Add entry'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function today(): string {
    return new Date().toISOString().slice(0, 10);
}
