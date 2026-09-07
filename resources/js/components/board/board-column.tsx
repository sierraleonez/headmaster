import {
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { GripVertical, MoreHorizontal, Plus } from 'lucide-react';
import { useRef, useState } from 'react';

import { BoardCard } from '@/components/board/board-card';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import {
    destroy as destroyColumn,
    update as updateColumn,
} from '@/routes/columns';
import { store as storeItem } from '@/routes/items';
import type { BoardColumn as Column, BoardItem } from '@/types';

export function BoardColumn({
    column,
    onOpenItem,
}: {
    column: Column;
    onOpenItem: (item: BoardItem) => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: `column-${column.id}`,
        data: { type: 'column', column },
    });

    const [renaming, setRenaming] = useState(false);
    const [name, setName] = useState(column.name);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [adding, setAdding] = useState(false);
    const [draft, setDraft] = useState('');
    const draftRef = useRef<HTMLInputElement>(null);

    const rename = () => {
        setRenaming(false);

        if (name.trim() && name.trim() !== column.name) {
            router.patch(
                updateColumn.url(column.id),
                { name: name.trim() },
                { preserveScroll: true },
            );

            return;
        }

        setName(column.name);
    };

    const addCard = () => {
        const title = draft.trim();

        if (!title) {
            setAdding(false);

            return;
        }

        router.post(
            storeItem.url(column.id),
            { title },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDraft('');
                    // Keep the composer open: plans arrive in batches.
                    requestAnimationFrame(() => draftRef.current?.focus());
                },
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ?? 'Could not add the card.',
                    ),
            },
        );
    };

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Translate.toString(transform), transition }}
            className={cn(
                'bg-sidebar/60 flex h-full w-72 shrink-0 flex-col rounded-lg border',
                isDragging && 'opacity-50',
            )}
        >
            <div className="flex items-center gap-1 px-2 py-2">
                <button
                    type="button"
                    aria-label="Reorder column"
                    className="text-muted-foreground hover:text-foreground cursor-grab active:cursor-grabbing"
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="size-4" />
                </button>

                {renaming ? (
                    <Input
                        autoFocus
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        onBlur={rename}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                rename();
                            }

                            if (event.key === 'Escape') {
                                setName(column.name);
                                setRenaming(false);
                            }
                        }}
                        className="h-7 flex-1 text-sm"
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => setRenaming(true)}
                        className="min-w-0 flex-1 truncate text-left text-sm font-medium"
                        title="Rename column"
                    >
                        {column.name}
                    </button>
                )}

                <span className="text-muted-foreground text-xs tabular-nums">
                    {column.items.length}
                </span>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-6"
                            aria-label="Column actions"
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-44">
                        <DropdownMenuItem onSelect={() => setRenaming(true)}>
                            Rename
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => setConfirmingDelete(true)}
                        >
                            Delete column
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div className="flex min-h-0 flex-1 flex-col gap-1.5 overflow-y-auto px-2 pb-2">
                <SortableContext
                    items={column.items.map((item) => `card-${item.id}`)}
                    strategy={verticalListSortingStrategy}
                >
                    {column.items.map((item) => (
                        <BoardCard
                            key={item.id}
                            item={item}
                            onOpen={onOpenItem}
                        />
                    ))}
                </SortableContext>

                {adding ? (
                    <Input
                        ref={draftRef}
                        autoFocus
                        value={draft}
                        placeholder="Card title"
                        onChange={(event) => setDraft(event.target.value)}
                        onBlur={() => {
                            if (!draft.trim()) {
                                setAdding(false);
                            }
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                addCard();
                            }

                            if (event.key === 'Escape') {
                                setDraft('');
                                setAdding(false);
                            }
                        }}
                        className="h-8 text-sm"
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => setAdding(true)}
                        className="text-muted-foreground hover:bg-accent hover:text-foreground flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs transition-colors"
                    >
                        <Plus className="size-3.5" />
                        Add card
                    </button>
                )}
            </div>

            <Dialog open={confirmingDelete} onOpenChange={setConfirmingDelete}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete "{column.name}"?</DialogTitle>
                        <DialogDescription>
                            {column.items.length === 0
                                ? 'The column is empty.'
                                : `Its ${column.items.length} card${column.items.length === 1 ? '' : 's'} go with it, along with anything nested under them.`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirmingDelete(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                setConfirmingDelete(false);
                                router.delete(destroyColumn.url(column.id), {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            Delete column
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
