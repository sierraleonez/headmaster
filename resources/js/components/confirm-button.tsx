import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';

/**
 * Click once to arm, once more to confirm. Keeps destructive actions
 * deliberate without a modal on top of a modal.
 */
export function ConfirmButton({
    onConfirm,
    label = 'Delete',
    confirmLabel = 'Click again',
    size = 'sm',
    variant = 'ghost',
    className,
    disabled,
}: {
    onConfirm: () => void;
    label?: string;
    confirmLabel?: string;
    size?: 'sm' | 'default' | 'lg' | 'icon';
    variant?: 'ghost' | 'outline' | 'destructive' | 'secondary';
    className?: string;
    disabled?: boolean;
}) {
    const [armed, setArmed] = useState(false);

    useEffect(() => {
        if (!armed) {
            return;
        }

        const timer = setTimeout(() => setArmed(false), 4000);

        return () => clearTimeout(timer);
    }, [armed]);

    return (
        <Button
            type="button"
            size={size}
            variant={armed ? 'destructive' : variant}
            className={className}
            disabled={disabled}
            onClick={() => {
                if (armed) {
                    setArmed(false);
                    onConfirm();

                    return;
                }

                setArmed(true);
            }}
        >
            {armed ? confirmLabel : label}
        </Button>
    );
}
