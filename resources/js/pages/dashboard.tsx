import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import * as linksRoute from '@/routes/links';
import { Link } from '@inertiajs/react';

interface Stats {
    totalLinks: number;
    totalGroups: number;
    favoritedLinks: number;
}

interface TagStat {
    name: string;
    count: number;
}

interface RecentLink {
    id: number;
    title: string;
    link: string;
    created_at: string;
}

interface DayCount {
    date: string;
    count: number;
}

interface Props {
    stats: Stats;
    popularTags: TagStat[];
    recentLinks: RecentLink[];
    linksPerDay: DayCount[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard({ stats, popularTags, recentLinks, linksPerDay }: Props) {
    const maxCount = linksPerDay.length > 0 ? Math.max(...linksPerDay.map((d) => d.count)) : 1;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Total Links</p>
                        <p className="mt-1 text-3xl font-semibold">{stats.totalLinks}</p>
                    </div>
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Collections</p>
                        <p className="mt-1 text-3xl font-semibold">{stats.totalGroups}</p>
                    </div>
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">Favorited</p>
                        <p className="mt-1 text-3xl font-semibold">{stats.favoritedLinks}</p>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="mb-3 text-sm font-medium">Popular Tags</h2>
                        {popularTags.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No tags yet.</p>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {popularTags.map((tag) => (
                                    <li key={tag.name} className="flex items-center justify-between">
                                        <span className="rounded-full bg-accent px-2 py-0.5 text-xs">{tag.name}</span>
                                        <span className="text-sm text-muted-foreground">{tag.count} link{tag.count !== 1 ? 's' : ''}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="mb-3 text-sm font-medium">Recent Links</h2>
                        {recentLinks.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No links yet.</p>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {recentLinks.map((link) => (
                                    <li key={link.id}>
                                        <Link href={linksRoute.show(link.id).url} className="flex flex-col hover:underline">
                                            <span className="text-sm font-medium">{link.title || link.link}</span>
                                            <span className="text-xs text-muted-foreground">{link.created_at}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                {linksPerDay.length > 0 && (
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="mb-3 text-sm font-medium">Links Added (Last 30 Days)</h2>
                        <div className="flex h-24 items-end gap-0.5">
                            {linksPerDay.map((day) => (
                                <div
                                    key={day.date}
                                    className="flex-1 rounded-sm bg-primary opacity-80"
                                    style={{ height: `${(day.count / maxCount) * 100}%` }}
                                    title={`${day.date}: ${day.count} link${day.count !== 1 ? 's' : ''}`}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
