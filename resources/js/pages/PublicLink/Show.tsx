import { Head, WhenVisible } from '@inertiajs/react';

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
    title: string;
    links: CursorPaginator<LinkItem>;
    shareId: string;
    searchString: string;
}

export default function PublicLinkShow({ title, links: paginator }: Props) {
    return (
        <div className="min-h-screen bg-background">
            <Head title={title} />
            <div className="mx-auto max-w-2xl px-4 py-10">
                <h1 className="mb-6 text-2xl font-bold">{title}</h1>

                <div className="flex flex-col gap-2">
                    {paginator.data.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No links in this collection.
                        </p>
                    )}
                    {paginator.data.map((link) => (
                        <a
                            key={link.id}
                            href={link.link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex flex-col rounded-lg border border-border p-3 hover:bg-accent"
                        >
                            <span className="font-medium">
                                {link.title || link.link}
                            </span>
                            <span className="truncate text-sm text-muted-foreground">
                                {link.link}
                            </span>
                        </a>
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
        </div>
    );
}
