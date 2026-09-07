import { Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    LayoutGrid,
    NotebookText,
    SquareKanban,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { show as showProject } from '@/routes/projects';
import type { TreeNode } from '@/types';

type Row = {
    node: TreeNode;
    depth: number;
    expandable: boolean;
    expanded: boolean;
};

/**
 * The Obsidian-style file tree: every board and log the user owns, nested
 * exactly as their projects are. Rendered flat so rows stay valid list items.
 */
export function ProjectExplorer() {
    const tree = usePage().props.tree;
    const { currentUrl } = useCurrentUrl();

    // Branches on the way to the open project start expanded; anything the
    // user clicks open or shut overrides that for the rest of the visit.
    const [overrides, setOverrides] = useState<Record<number, boolean>>({});

    const rows = useMemo(() => {
        const flat: Row[] = [];

        const walk = (nodes: TreeNode[], depth: number, visible: boolean) => {
            for (const node of nodes) {
                const expanded =
                    overrides[node.id] ??
                    (depth === 0 || leadsTo(node, currentUrl));

                if (visible) {
                    flat.push({
                        node,
                        depth,
                        expandable: node.children.length > 0,
                        expanded,
                    });
                }

                walk(node.children, depth + 1, visible && expanded);
            }
        };

        walk(tree ?? [], 0, true);

        return flat;
    }, [tree, overrides, currentUrl]);

    const toggle = (id: number, expanded: boolean) =>
        setOverrides((current) => ({ ...current, [id]: !expanded }));

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Projects</SidebarGroupLabel>
            <SidebarMenu>
                {rows.map(({ node, depth, expandable, expanded }) => {
                    const href = showProject.url(node.id);
                    const Icon =
                        depth === 0
                            ? LayoutGrid
                            : node.kind === 'log'
                              ? NotebookText
                              : SquareKanban;

                    return (
                        <SidebarMenuItem
                            key={node.id}
                            className="flex items-center"
                        >
                            <button
                                type="button"
                                aria-label={expanded ? 'Collapse' : 'Expand'}
                                onClick={() => toggle(node.id, expanded)}
                                style={{ marginLeft: depth * 10 }}
                                className={cn(
                                    'text-sidebar-foreground/50 hover:text-sidebar-foreground flex size-5 shrink-0 items-center justify-center rounded transition-transform duration-150',
                                    !expandable && 'invisible',
                                    expanded && 'rotate-90',
                                )}
                            >
                                <ChevronRight className="size-3.5" />
                            </button>

                            <SidebarMenuButton
                                asChild
                                isActive={currentUrl === href}
                                tooltip={{ children: node.name }}
                                className="h-7 min-w-0 flex-1"
                            >
                                <Link href={href} prefetch>
                                    <Icon className="size-3.5 opacity-70" />
                                    <span className="truncate">
                                        {node.name}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function leadsTo(node: TreeNode, path: string): boolean {
    return (
        showProject.url(node.id) === path ||
        node.children.some((child) => leadsTo(child, path))
    );
}
