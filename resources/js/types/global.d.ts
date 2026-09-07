import type { Auth } from '@/types/auth';
import type { TreeNode } from '@/types/headmaster';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            tree: TreeNode[];
            [key: string]: unknown;
        };
    }
}
