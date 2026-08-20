import { Head, Link, WhenVisible } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import * as groupsRoute from '@/routes/groups';
import * as linksRoute from '@/routes/links';
import type { BreadcrumbItem } from '@/types';

interface Tag {
    id: number;
    name: string;
}

interface LinkItem {
    id: number;
    title: string;
    link: string;
}

interface GroupDetail {
    id: number;
    title: string;
    parentGroupId: number | null;
    orTags: Tag[];
    andTags: Tag[];
    notTags: Tag[];
}

interface PublicLinkInfo {
    id: number | null;
    link: string | null;
}

interface CursorPaginator<T> {
    data: T[];
    next_cursor: string | null;
    path: string;
}

interface Props {
    group: GroupDetail;
    links: CursorPaginator<LinkItem>;
    publicLink: PublicLinkInfo;
    searchString: string;
    filteredTags: Tag[];
    showUntaggedOnly: boolean;
}

export default function SingleGroupIndex({
    group,
    links: paginator,
    publicLink,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Links', href: linksRoute.index().url },
        { title: group.title, href: groupsRoute.show(group.id).url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={group.title} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{group.title}</h1>
                    {publicLink.link && (
                        <a
                            href={publicLink.link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-sm text-blue-500 hover:underline"
                        >
                            Public link
                        </a>
                    )}
                </div>

                <div className="flex flex-col gap-2">
                    {paginator.data.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No links in this collection.
                        </p>
                    )}
                    {paginator.data.map((link) => (
                        <Link
                            key={link.id}
                            href={linksRoute.show(link.id).url}
                            className="flex flex-col rounded-lg border border-border p-3 hover:bg-accent"
                        >
                            <span className="font-medium">
                                {link.title || link.link}
                            </span>
                            <span className="truncate text-sm text-muted-foreground">
                                {link.link}
                            </span>
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
                            <span className="text-sm text-muted-foreground">
                                Loading more...
                            </span>
                        </div>
                    </WhenVisible>
                )}
            </div>
        </AppLayout>
    );
}
