import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

import { cn } from '@/lib/utils';

/**
 * Note bodies, briefings and log entries are plain markdown. Anything the user
 * drops in a card is rendered here.
 */
export function Markdown({
    children,
    className,
}: {
    children: string | null | undefined;
    className?: string;
}) {
    if (!children?.trim()) {
        return null;
    }

    return (
        <div className={cn('md', className)}>
            <ReactMarkdown remarkPlugins={[remarkGfm]}>
                {children}
            </ReactMarkdown>
        </div>
    );
}
