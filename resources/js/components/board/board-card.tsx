import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { NotebookText, SquareKanban } from 'lucide-react';

import { cn } from '@/lib/utils';
import type { BoardItem } from '@/types';

/**
 * A card on the board. Cards move anywhere the user drags them: no column
 * enforces rules about what may enter it.
 */
export function BoardCard({
    item,
    onOpen,
    dragging = false,
}: {
    item: BoardItem;
    onOpen?: (item: BoardItem) => void;
    dragging?: boolean;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: `card-${item.id}`,
        data: { type: 'card', item },
    });

    const Icon = item.child?.kind === 'log' ? NotebookText : SquareKanban;
    const preview = summarise(item.body);

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Translate.toString(transform), transition }}
            {...attributes}
            {...listeners}
            onClick={() => onOpen?.(item)}
            className={cn(
                'bg-card hover:border-ring/60 group cursor-grab rounded-md border px-2.5 py-2 text-left shadow-xs transition-colors active:cursor-grabbing',
                isDragging && 'opacity-40',
                dragging && 'rotate-1 cursor-grabbing shadow-lg',
            )}
        >
            <div className="flex items-start gap-1.5">
                {item.type === 'subproject' && (
                    <Icon className="text-muted-foreground mt-0.5 size-3.5 shrink-0" />
                )}
                <p className="min-w-0 flex-1 text-sm leading-snug break-words">
                    {item.title}
                </p>
            </div>

            {preview && (
                <p className="text-muted-foreground mt-1 line-clamp-2 text-xs leading-snug">
                    {preview}
                </p>
            )}
        </div>
    );
}

/** First readable line of the markdown body, for the card preview. */
function summarise(body: string | null): string {
    if (!body) {
        return '';
    }

    const line = body
        .split('\n')
        .map((entry) => entry.replace(/^[#>\-*\s]+/, '').trim())
        .find((entry) => entry.length > 0);

    return line ? line.slice(0, 140) : '';
}
