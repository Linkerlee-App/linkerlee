import { Head, Link, WhenVisible } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import * as inboxRoute from '@/routes/inbox';
import * as linksRoute from '@/routes/links';
import type { BreadcrumbItem } from '@/types';

interface LinkItem {
    id: number;
    title: string;
    link: string;
}

interface CursorPaginator<T> {
    data: T[];
    next_cursor: string | null;
    path: string;
}

interface Props {
    links: CursorPaginator<LinkItem>;
    searchString: string;
    untagged: boolean;
    ungrouped: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inbox', href: inboxRoute.index().url },
];

export default function InboxIndex({ links: paginator }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inbox" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Inbox</h1>

                <div className="flex flex-col gap-2">
                    {paginator.data.length === 0 && (
                        <p className="text-sm text-muted-foreground">Inbox is empty.</p>
                    )}
                    {paginator.data.map((link) => (
                        <Link key={link.id} href={linksRoute.show(link.id).url} className="flex flex-col rounded-lg border border-border p-3 hover:bg-accent">
                            <span className="font-medium">{link.title || link.link}</span>
                            <span className="truncate text-sm text-muted-foreground">{link.link}</span>
                        </Link>
                    ))}
                </div>

                {paginator.next_cursor && (
                    <WhenVisible
                        always
                        params={{
                            data: { cursor: paginator.next_cursor },
                            only: ['links'],
                        }}
                        fallback={<div className="h-8" />}
                    >
                        <div className="flex justify-center py-4">
                            <span className="text-sm text-muted-foreground">Loading more...</span>
                        </div>
                    </WhenVisible>
                )}
            </div>
        </AppLayout>
    );
}
