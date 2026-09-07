import { Link } from '@inertiajs/react';
import { MessageSquarePlus } from 'lucide-react';

import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import { ProjectExplorer } from '@/components/project-explorer';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { index as chatIndex } from '@/routes/chat';

export function AppSidebar() {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <ProjectExplorer />
            </SidebarContent>

            <SidebarFooter>
                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentOrParentUrl(chatIndex())}
                                tooltip={{ children: 'Assistant' }}
                            >
                                <Link href={chatIndex()} prefetch>
                                    <MessageSquarePlus />
                                    <span>Assistant</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>

                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
