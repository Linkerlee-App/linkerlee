import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import * as linksRoute from '@/routes/links';
import type { BreadcrumbItem } from '@/types';

interface TrashedLink {
    id: number;
    title: string;
    link: string;
    deleted_at: string;
}

interface Props {
    links: TrashedLink[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Links', href: linksRoute.index().url },
    { title: 'Trash', href: linksRoute.trashed().url },
];

export default function LinksTrashed({ links }: Props) {
    function handleRestore(id: number) {
        router.patch(linksRoute.restore(id).url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trash" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Trash</h1>

                {links.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No archived links.</p>
                ) : (
                    <div className="flex flex-col gap-2">
                        {links.map((link) => (
                            <div key={link.id} className="flex items-center justify-between rounded-lg border border-border p-3">
                                <div className="flex flex-col">
                                    <span className="font-medium">{link.title || link.link}</span>
                                    <span className="truncate text-sm text-muted-foreground">{link.link}</span>
                                    <span className="text-xs text-muted-foreground">Archived {link.deleted_at}</span>
                                </div>
                                <button
                                    onClick={() => handleRestore(link.id)}
                                    className="ml-4 rounded-md border border-border px-3 py-1.5 text-sm"
                                >
                                    Restore
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
