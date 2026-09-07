import {
    closestCorners,
    DndContext,
    DragOverlay,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragOverEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import {
    arrayMove,
    horizontalListSortingStrategy,
    SortableContext,
} from '@dnd-kit/sortable';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { BoardCard } from '@/components/board/board-card';
import { BoardColumn } from '@/components/board/board-column';
import { ItemDialog } from '@/components/board/item-dialog';
import { Input } from '@/components/ui/input';
import { reorder, store as storeColumn } from '@/routes/columns';
import { move } from '@/routes/items';
import type { BoardColumn as Column, BoardItem, ProjectPayload } from '@/types';

/**
 * The kanban board. Cards may be dragged anywhere, in any order: HeadMaster
 * tracks state, it does not police it.
 */
export function Board({ project }: { project: ProjectPayload }) {
    const [columns, setColumns] = useState<Column[]>(project.columns ?? []);
    const [activeCard, setActiveCard] = useState<BoardItem | null>(null);
    const [openItem, setOpenItem] = useState<BoardItem | null>(null);
    const [addingColumn, setAddingColumn] = useState(false);
    const [columnName, setColumnName] = useState('');

    // Re-sync whenever the server sends a new version of the board.
    useEffect(() => setColumns(project.columns ?? []), [project]);

    // The dialog holds a card by id, so it follows server updates.
    const dialogItem = useMemo(() => {
        if (!openItem) {
            return null;
        }

        for (const column of columns) {
            const found = column.items.find((item) => item.id === openItem.id);

            if (found) {
                return found;
            }
        }

        return null;
    }, [openItem, columns]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    );

    const findColumnIndex = (cardId: number) =>
        columns.findIndex((column) =>
            column.items.some((item) => item.id === cardId),
        );

    const handleDragStart = ({ active }: DragStartEvent) => {
        if (active.data.current?.type === 'card') {
            setActiveCard(active.data.current.item as BoardItem);
        }
    };

    /** Live preview: pull the card into whichever column it is hovering over. */
    const handleDragOver = ({ active, over }: DragOverEvent) => {
        if (!over || active.data.current?.type !== 'card') {
            return;
        }

        const cardId = (active.data.current.item as BoardItem).id;
        const target = resolveColumnIndex(columns, over.id.toString());

        if (target === -1) {
            return;
        }

        const source = findColumnIndex(cardId);

        if (source === -1 || source === target) {
            return;
        }

        setColumns((current) => {
            const next = current.map((column) => ({
                ...column,
                items: [...column.items],
            }));

            const moving = next[source].items.find(
                (item) => item.id === cardId,
            );

            if (!moving) {
                return current;
            }

            next[source].items = next[source].items.filter(
                (item) => item.id !== cardId,
            );

            const at = indexWithin(next[target], over.id.toString());
            next[target].items.splice(
                at === -1 ? next[target].items.length : at,
                0,
                { ...moving, board_column_id: next[target].id },
            );

            return next;
        });
    };

    const handleDragEnd = ({ active, over }: DragEndEvent) => {
        setActiveCard(null);

        if (!over) {
            return;
        }

        if (active.data.current?.type === 'column') {
            const from = columns.findIndex(
                (column) => `column-${column.id}` === active.id,
            );
            const to = columns.findIndex(
                (column) => `column-${column.id}` === over.id,
            );

            if (from === -1 || to === -1 || from === to) {
                return;
            }

            const ordered = arrayMove(columns, from, to);
            setColumns(ordered);

            router.post(
                reorder.url(project.id),
                { ids: ordered.map((column) => column.id) },
                { preserveScroll: true, preserveState: true },
            );

            return;
        }

        const cardId = (active.data.current?.item as BoardItem | undefined)?.id;

        if (!cardId) {
            return;
        }

        const columnIndex = findColumnIndex(cardId);

        if (columnIndex === -1) {
            return;
        }

        const column = columns[columnIndex];
        const from = column.items.findIndex((item) => item.id === cardId);
        const overIndex = indexWithin(column, over.id.toString());
        const to = overIndex === -1 ? column.items.length - 1 : overIndex;

        const items = arrayMove(column.items, from, to);

        setColumns((current) =>
            current.map((entry, index) =>
                index === columnIndex ? { ...entry, items } : entry,
            ),
        );

        router.post(
            move.url(cardId),
            {
                board_column_id: column.id,
                position: items.findIndex((item) => item.id === cardId),
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const addColumn = () => {
        const name = columnName.trim();

        if (!name) {
            setAddingColumn(false);

            return;
        }

        router.post(
            storeColumn.url(project.id),
            { name },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setColumnName('');
                    setAddingColumn(false);
                },
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ?? 'Could not add the column.',
                    ),
            },
        );
    };

    return (
        <>
            <DndContext
                sensors={sensors}
                collisionDetection={closestCorners}
                onDragStart={handleDragStart}
                onDragOver={handleDragOver}
                onDragEnd={handleDragEnd}
                onDragCancel={() => setActiveCard(null)}
            >
                <div className="flex h-full min-h-0 items-stretch gap-3 overflow-x-auto px-4 pb-4">
                    <SortableContext
                        items={columns.map((column) => `column-${column.id}`)}
                        strategy={horizontalListSortingStrategy}
                    >
                        {columns.map((column) => (
                            <BoardColumn
                                key={column.id}
                                column={column}
                                onOpenItem={setOpenItem}
                            />
                        ))}
                    </SortableContext>

                    <div className="w-64 shrink-0 pt-2">
                        {addingColumn ? (
                            <Input
                                autoFocus
                                value={columnName}
                                placeholder="Column name"
                                onChange={(event) =>
                                    setColumnName(event.target.value)
                                }
                                onBlur={addColumn}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        addColumn();
                                    }

                                    if (event.key === 'Escape') {
                                        setColumnName('');
                                        setAddingColumn(false);
                                    }
                                }}
                                className="h-8"
                            />
                        ) : (
                            <button
                                type="button"
                                onClick={() => setAddingColumn(true)}
                                className="text-muted-foreground hover:border-ring hover:text-foreground flex w-full items-center gap-1.5 rounded-md border border-dashed px-2.5 py-2 text-xs transition-colors"
                            >
                                <Plus className="size-3.5" />
                                Add column
                            </button>
                        )}
                    </div>
                </div>

                <DragOverlay dropAnimation={null}>
                    {activeCard ? (
                        <BoardCard item={activeCard} dragging />
                    ) : null}
                </DragOverlay>
            </DndContext>

            <ItemDialog item={dialogItem} onClose={() => setOpenItem(null)} />
        </>
    );
}

/** Which column a drop target belongs to, whether it is a column or a card. */
function resolveColumnIndex(columns: Column[], overId: string): number {
    if (overId.startsWith('column-')) {
        return columns.findIndex(
            (column) => column.id === Number(overId.slice(7)),
        );
    }

    const cardId = Number(overId.slice(5));

    return columns.findIndex((column) =>
        column.items.some((item) => item.id === cardId),
    );
}

/** Position of a drop target inside one column, or -1 for the column itself. */
function indexWithin(column: Column, overId: string): number {
    if (!overId.startsWith('card-')) {
        return -1;
    }

    return column.items.findIndex(
        (item) => item.id === Number(overId.slice(5)),
    );
}
