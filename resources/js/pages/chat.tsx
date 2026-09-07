import { Head, Link, router } from '@inertiajs/react';
import { CornerDownLeft, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { PlanEditor } from '@/components/chat/plan-editor';
import { Markdown } from '@/components/markdown';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    apply,
    destroy as destroyConversation,
    index as chatIndex,
    show as showConversation,
    store as sendMessage,
} from '@/routes/chat';
import { show as showProject } from '@/routes/projects';
import type { BoardOption, ChatConversation, ChatMessage, Plan } from '@/types';

/**
 * The assistant. Describe a plan you already have; it maps it onto boards,
 * cards and logs. Nothing is created until the draft is accepted.
 */
export default function ChatPage({
    conversation,
    conversations,
    boards,
    assistantReady,
}: {
    conversation: ChatConversation | null;
    conversations: { id: number; title: string }[];
    boards: BoardOption[];
    assistantReady: boolean;
}) {
    const [message, setMessage] = useState('');
    const [target, setTarget] = useState<string>(
        boards[0] ? String(boards[0].id) : '',
    );
    const [sending, setSending] = useState(false);
    const thread = useRef<HTMLDivElement>(null);

    useEffect(() => {
        thread.current?.scrollTo({ top: thread.current.scrollHeight });
    }, [conversation]);

    const waiting =
        conversation?.messages.some((entry) => entry.status === 'pending') ??
        false;

    // The reply is written by a queued job; ask for it until it turns up.
    useEffect(() => {
        if (!waiting) {
            return;
        }

        const poll = setInterval(
            () => router.reload({ only: ['conversation'] }),
            2000,
        );

        return () => clearInterval(poll);
    }, [waiting]);

    const send = () => {
        const content = message.trim();

        if (!content || sending || waiting) {
            return;
        }

        setSending(true);

        router.post(
            sendMessage.url(),
            {
                conversation_id: conversation?.id ?? null,
                content,
                target_project_id: target ? Number(target) : null,
            },
            {
                onSuccess: () => setMessage(''),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title="Assistant" />

            <div className="flex h-full min-h-0 flex-col">
                <div className="flex items-center justify-between gap-3 px-4 pt-4 pb-3">
                    <div className="min-w-0">
                        <h1 className="truncate text-xl font-semibold tracking-tight">
                            Assistant
                        </h1>
                        <p className="text-muted-foreground mt-0.5 text-xs">
                            Describe a plan you already have. Review the draft,
                            then create it.
                        </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        {conversations.length > 0 && (
                            <Select
                                value={
                                    conversation ? String(conversation.id) : ''
                                }
                                onValueChange={(id) =>
                                    router.get(showConversation.url(Number(id)))
                                }
                            >
                                <SelectTrigger size="sm" className="w-56">
                                    <SelectValue placeholder="History" />
                                </SelectTrigger>
                                <SelectContent>
                                    {conversations.map((entry) => (
                                        <SelectItem
                                            key={entry.id}
                                            value={String(entry.id)}
                                        >
                                            {entry.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        <Button size="sm" variant="outline" asChild>
                            <Link href={chatIndex.url({ query: { new: 1 } })}>
                                <Plus />
                                New
                            </Link>
                        </Button>

                        {conversation && (
                            <Button
                                size="sm"
                                variant="ghost"
                                aria-label="Delete conversation"
                                onClick={() =>
                                    router.delete(
                                        destroyConversation.url(
                                            conversation.id,
                                        ),
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        )}
                    </div>
                </div>

                {!assistantReady && (
                    <p className="text-muted-foreground mx-4 mb-3 rounded-md border border-dashed px-3 py-2 text-xs">
                        No OpenRouter key configured. Add{' '}
                        <code className="font-mono">OPENROUTER_API_KEY</code> to
                        your <code className="font-mono">.env</code> to use the
                        assistant.
                    </p>
                )}

                <div
                    ref={thread}
                    className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-4 pb-4"
                >
                    {!conversation || conversation.messages.length === 0 ? (
                        <EmptyState />
                    ) : (
                        conversation.messages.map((entry) => (
                            <MessageBubble
                                key={entry.id}
                                message={entry}
                                boards={boards}
                                defaultTarget={target}
                            />
                        ))
                    )}
                </div>

                <div className="border-t px-4 py-3">
                    <div className="mx-auto flex max-w-3xl flex-col gap-2">
                        <div className="flex items-center gap-2">
                            <span className="text-muted-foreground text-xs">
                                Create inside
                            </span>
                            <Select value={target} onValueChange={setTarget}>
                                <SelectTrigger size="sm" className="w-64">
                                    <SelectValue placeholder="Pick a board" />
                                </SelectTrigger>
                                <SelectContent>
                                    {boards.map((board) => (
                                        <SelectItem
                                            key={board.id}
                                            value={String(board.id)}
                                        >
                                            {board.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            {message.length > 3000 && (
                                <span className="text-muted-foreground text-xs">
                                    · long plans are sent as their headings and
                                    lists, without the prose
                                </span>
                            )}
                        </div>

                        <div className="relative">
                            <Textarea
                                value={message}
                                onChange={(event) =>
                                    setMessage(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' &&
                                        (event.metaKey || event.ctrlKey)
                                    ) {
                                        event.preventDefault();
                                        send();
                                    }
                                }}
                                placeholder="Paste a study plan, a training block, a research outline..."
                                className="min-h-24 pr-28 text-sm"
                            />

                            <Button
                                size="sm"
                                className="absolute right-2 bottom-2"
                                disabled={sending || waiting || !message.trim()}
                                onClick={send}
                            >
                                {sending || waiting ? 'Working' : 'Send'}
                                <CornerDownLeft />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function EmptyState() {
    return (
        <div className="text-muted-foreground mx-auto flex max-w-md flex-1 flex-col justify-center gap-2 text-center text-sm">
            <p>Nothing here yet.</p>
            <p className="text-xs">
                Try: "Here is my 12-week powerlifting block, split it into a
                board per mesocycle with a workout log inside each."
            </p>
        </div>
    );
}

function MessageBubble({
    message,
    boards,
    defaultTarget,
}: {
    message: ChatMessage;
    boards: BoardOption[];
    defaultTarget: string;
}) {
    if (message.role === 'user') {
        return (
            <div className="ml-auto max-w-2xl rounded-lg border px-3 py-2">
                <p className="text-sm whitespace-pre-wrap">{message.content}</p>
            </div>
        );
    }

    if (message.status === 'pending') {
        return (
            <p className="text-muted-foreground mr-auto animate-pulse text-sm">
                Mapping your plan...
            </p>
        );
    }

    if (message.status === 'failed') {
        return (
            <p className="text-muted-foreground mr-auto max-w-3xl rounded-md border border-dashed px-3 py-2 text-sm">
                {message.content ?? 'The assistant did not reply.'}
            </p>
        );
    }

    return (
        <div className="mr-auto flex w-full max-w-3xl flex-col gap-3">
            {message.content && <Markdown>{message.content}</Markdown>}

            {message.plan && (
                <PlanReview
                    message={message}
                    plan={message.plan}
                    boards={boards}
                    defaultTarget={defaultTarget}
                />
            )}
        </div>
    );
}

function PlanReview({
    message,
    plan: initial,
    boards,
    defaultTarget,
}: {
    message: ChatMessage;
    plan: Plan;
    boards: BoardOption[];
    defaultTarget: string;
}) {
    const [plan, setPlan] = useState<Plan>(initial);
    const [target, setTarget] = useState(
        defaultTarget || (boards[0] ? String(boards[0].id) : ''),
    );
    const [creating, setCreating] = useState(false);

    useEffect(() => setPlan(initial), [initial]);

    const applied = message.applied_project_id !== null;

    return (
        <div
            className={cn(
                'bg-card rounded-lg border p-3',
                applied && 'opacity-70',
            )}
        >
            <div className="mb-3 flex items-center justify-between gap-2">
                <span className="text-muted-foreground text-xs">
                    {applied ? 'Created' : 'Draft — edit before creating'}
                </span>

                {applied && message.applied_project_id && (
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={showProject.url(message.applied_project_id)}
                        >
                            Open
                        </Link>
                    </Button>
                )}
            </div>

            <fieldset disabled={applied} className="contents">
                <PlanEditor plan={plan} onChange={setPlan} />
            </fieldset>

            {!applied && (
                <div className="mt-4 flex flex-wrap items-center justify-end gap-2 border-t pt-3">
                    <span className="text-muted-foreground mr-auto text-xs">
                        Lands in the first column of the board you pick.
                    </span>

                    <Select value={target} onValueChange={setTarget}>
                        <SelectTrigger size="sm" className="w-56">
                            <SelectValue placeholder="Pick a board" />
                        </SelectTrigger>
                        <SelectContent>
                            {boards.map((board) => (
                                <SelectItem
                                    key={board.id}
                                    value={String(board.id)}
                                >
                                    {board.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button
                        size="sm"
                        disabled={creating || !target || !plan.name.trim()}
                        onClick={() => {
                            setCreating(true);

                            router.post(
                                apply.url(message.id),
                                {
                                    project_id: Number(target),
                                    column_id: null,
                                    plan,
                                },
                                { onFinish: () => setCreating(false) },
                            );
                        }}
                    >
                        {creating ? 'Creating' : 'Create'}
                    </Button>
                </div>
            )}
        </div>
    );
}
