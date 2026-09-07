import { useState } from 'react';

import { Markdown } from '@/components/markdown';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/**
 * A markdown box with a preview. Anything goes in here: briefings, notes,
 * reference material, a dumped outline.
 */
export function MarkdownEditor({
    value,
    onChange,
    placeholder = 'Markdown. Drop anything here.',
    className,
    autoFocus = false,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
    autoFocus?: boolean;
}) {
    const [preview, setPreview] = useState(false);

    return (
        <div className={cn('flex min-h-0 flex-col gap-1.5', className)}>
            <div className="text-muted-foreground flex items-center justify-between text-xs">
                <span>Notes</span>
                <button
                    type="button"
                    className="hover:text-foreground underline underline-offset-2"
                    onClick={() => setPreview((current) => !current)}
                >
                    {preview ? 'Edit' : 'Preview'}
                </button>
            </div>

            {preview ? (
                <div className="min-h-40 flex-1 overflow-auto rounded-md border px-3 py-2">
                    {value.trim() ? (
                        <Markdown>{value}</Markdown>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            Nothing written yet.
                        </p>
                    )}
                </div>
            ) : (
                <Textarea
                    value={value}
                    autoFocus={autoFocus}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={placeholder}
                    className="min-h-40 flex-1 resize-none font-mono text-[13px] leading-relaxed"
                />
            )}
        </div>
    );
}
