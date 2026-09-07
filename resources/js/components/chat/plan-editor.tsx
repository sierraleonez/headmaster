import { ChevronDown, ChevronRight, Plus, X } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { Plan, PlanItem } from '@/types';

const DEFAULT_COLUMNS = [
    'Backlog',
    'Todo',
    'In Progress',
    'Blocked',
    'Canceled',
    'Done',
];

/**
 * The draft, before it becomes real. Everything the assistant proposed can be
 * renamed, moved, removed or nested differently first.
 */
export function PlanEditor({
    plan,
    onChange,
    depth = 0,
}: {
    plan: Plan;
    onChange: (plan: Plan) => void;
    depth?: number;
}) {
    const patch = (changes: Partial<Plan>) => onChange({ ...plan, ...changes });

    const columns = plan.columns.length > 0 ? plan.columns : DEFAULT_COLUMNS;

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    value={plan.name}
                    onChange={(event) => patch({ name: event.target.value })}
                    placeholder="Project name"
                    className="h-8 min-w-0 flex-1 text-sm font-medium"
                />

                <Select
                    value={plan.kind}
                    onValueChange={(kind) =>
                        patch({ kind: kind as Plan['kind'] })
                    }
                >
                    <SelectTrigger size="sm" className="w-28">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="board">Board</SelectItem>
                        <SelectItem value="log">Log</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            {plan.kind === 'board' ? (
                <>
                    <label className="flex flex-col gap-1">
                        <span className="text-muted-foreground text-xs">
                            Columns
                        </span>
                        <Input
                            value={columns.join(', ')}
                            onChange={(event) =>
                                patch({
                                    columns: event.target.value
                                        .split(',')
                                        .map((name) => name.trim())
                                        .filter((name) => name !== ''),
                                })
                            }
                            className="h-8 text-sm"
                        />
                    </label>

                    <div className="flex flex-col gap-1.5">
                        {plan.items.map((item, index) => (
                            <ItemRow
                                key={index}
                                item={item}
                                columns={columns}
                                depth={depth}
                                onChange={(next) =>
                                    patch({
                                        items: plan.items.map((entry, at) =>
                                            at === index ? next : entry,
                                        ),
                                    })
                                }
                                onRemove={() =>
                                    patch({
                                        items: plan.items.filter(
                                            (_, at) => at !== index,
                                        ),
                                    })
                                }
                            />
                        ))}

                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="self-start"
                            onClick={() =>
                                patch({
                                    items: [
                                        ...plan.items,
                                        {
                                            title: '',
                                            body: null,
                                            column: columns[0],
                                            type: 'note',
                                            project: null,
                                        },
                                    ],
                                })
                            }
                        >
                            <Plus />
                            Add card
                        </Button>
                    </div>
                </>
            ) : (
                <div className="flex flex-col gap-1.5">
                    {plan.entries.map((entry, index) => (
                        <div
                            key={index}
                            className="flex flex-wrap items-center gap-2 rounded-md border px-2 py-1.5"
                        >
                            <Input
                                type="date"
                                value={entry.logged_on}
                                onChange={(event) =>
                                    patch({
                                        entries: plan.entries.map((row, at) =>
                                            at === index
                                                ? {
                                                      ...row,
                                                      logged_on:
                                                          event.target.value,
                                                  }
                                                : row,
                                        ),
                                    })
                                }
                                className="h-7 w-36 text-xs"
                            />
                            <Input
                                value={entry.title}
                                placeholder="Entry"
                                onChange={(event) =>
                                    patch({
                                        entries: plan.entries.map((row, at) =>
                                            at === index
                                                ? {
                                                      ...row,
                                                      title: event.target.value,
                                                  }
                                                : row,
                                        ),
                                    })
                                }
                                className="h-7 min-w-0 flex-1 text-sm"
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-7"
                                aria-label="Remove entry"
                                onClick={() =>
                                    patch({
                                        entries: plan.entries.filter(
                                            (_, at) => at !== index,
                                        ),
                                    })
                                }
                            >
                                <X className="size-3.5" />
                            </Button>
                        </div>
                    ))}

                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="self-start"
                        onClick={() =>
                            patch({
                                entries: [
                                    ...plan.entries,
                                    {
                                        logged_on: new Date()
                                            .toISOString()
                                            .slice(0, 10),
                                        title: '',
                                        body: null,
                                    },
                                ],
                            })
                        }
                    >
                        <Plus />
                        Add entry
                    </Button>
                </div>
            )}
        </div>
    );
}

function ItemRow({
    item,
    columns,
    depth,
    onChange,
    onRemove,
}: {
    item: PlanItem;
    columns: string[];
    depth: number;
    onChange: (item: PlanItem) => void;
    onRemove: () => void;
}) {
    const [expanded, setExpanded] = useState(false);

    const toggleType = () => {
        if (item.type === 'subproject') {
            onChange({ ...item, type: 'note', project: null });

            return;
        }

        onChange({
            ...item,
            type: 'subproject',
            project: item.project ?? {
                name: item.title || 'Sub-project',
                kind: 'board',
                description: null,
                columns: [...DEFAULT_COLUMNS],
                items: [],
                entries: [],
            },
        });
    };

    return (
        <div className="rounded-md border">
            <div className="flex flex-wrap items-center gap-2 px-2 py-1.5">
                <button
                    type="button"
                    aria-label={expanded ? 'Collapse' : 'Expand'}
                    onClick={() => setExpanded((value) => !value)}
                    className="text-muted-foreground hover:text-foreground"
                >
                    {expanded ? (
                        <ChevronDown className="size-3.5" />
                    ) : (
                        <ChevronRight className="size-3.5" />
                    )}
                </button>

                <Input
                    value={item.title}
                    placeholder="Card title"
                    onChange={(event) =>
                        onChange({ ...item, title: event.target.value })
                    }
                    className="h-7 min-w-0 flex-1 text-sm"
                />

                <Select
                    value={
                        columns.includes(item.column) ? item.column : columns[0]
                    }
                    onValueChange={(column) => onChange({ ...item, column })}
                >
                    <SelectTrigger size="sm" className="w-32">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {columns.map((column) => (
                            <SelectItem key={column} value={column}>
                                {column}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Button
                    type="button"
                    size="sm"
                    variant={item.type === 'subproject' ? 'secondary' : 'ghost'}
                    onClick={toggleType}
                    className="text-xs"
                >
                    {item.type === 'subproject' ? 'Sub-project' : 'Note'}
                </Button>

                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="size-7"
                    aria-label="Remove card"
                    onClick={onRemove}
                >
                    <X className="size-3.5" />
                </Button>
            </div>

            {expanded && (
                <div
                    className={cn(
                        'flex flex-col gap-3 border-t px-2 py-2',
                        depth > 2 && 'text-xs',
                    )}
                >
                    <Textarea
                        value={item.body ?? ''}
                        placeholder="Notes for this card (markdown)"
                        onChange={(event) =>
                            onChange({
                                ...item,
                                body:
                                    event.target.value.trim() === ''
                                        ? null
                                        : event.target.value,
                            })
                        }
                        className="min-h-20 font-mono text-xs"
                    />

                    {item.type === 'subproject' && item.project && (
                        <div className="bg-muted/40 rounded-md border p-2">
                            <PlanEditor
                                plan={item.project}
                                depth={depth + 1}
                                onChange={(project) =>
                                    onChange({ ...item, project })
                                }
                            />
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
