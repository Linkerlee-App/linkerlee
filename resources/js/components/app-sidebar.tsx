import { Link } from '@inertiajs/react';
import {
    FolderOpen,
    Inbox,
    LayoutGrid,
    Link2,
    Share2,
    Star,
    Tag,
    Trash2,
} from 'lucide-react';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import * as groupsRoute from '@/routes/groups';
import * as inboxRoute from '@/routes/inbox';
import * as linksRoute from '@/routes/links';
import * as publicLinksRoute from '@/routes/publicLinks';
import * as tagsRoute from '@/routes/tags';
import type { NavItem } from '@/types';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Links',
        href: linksRoute.index(),
        icon: Link2,
    },
    {
        title: 'Tags',
        href: tagsRoute.index(),
        icon: Tag,
    },
    {
        title: 'Collections',
        href: groupsRoute.index(),
        icon: FolderOpen,
    },
    {
        title: 'Shared',
        href: publicLinksRoute.index(),
        icon: Share2,
    },
    {
        title: 'Inbox',
        href: inboxRoute.index(),
        icon: Inbox,
    },
    {
        title: 'Favorites',
        href: linksRoute.index({ query: { favorite: '1' } }),
        icon: Star,
    },
    {
        title: 'Trash',
        href: linksRoute.trashed(),
        icon: Trash2,
    },
];

export function AppSidebar() {
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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
