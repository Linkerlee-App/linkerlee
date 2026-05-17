import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import * as publicLinksRoute from '@/routes/publicLinks';
import type { BreadcrumbItem } from '@/types';

interface PublicLinkItem {
    id: number;
    shareId: string;
    link: string;
    group: {
        id: number;
        title: string;
    };
}

interface Props {
    publicLinks: PublicLinkItem[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Shared Links', href: publicLinksRoute.index().url },
];

export default function PublicLinkIndex({ publicLinks }: Props) {
    function handleDelete(id: number) {
        if (confirm('Remove this shared link?')) {
            router.delete(publicLinksRoute.destroy(id).url);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Shared Links" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Shared Links</h1>

                {publicLinks.length === 0 && (
                    <p className="text-sm text-muted-foreground">No shared links yet.</p>
                )}

                <div className="flex flex-col gap-2">
                    {publicLinks.map((pl) => (
                        <div key={pl.id} className="flex items-center justify-between rounded-lg border border-border p-3">
                            <div className="flex flex-col gap-0.5">
                                <span className="font-medium">{pl.group.title}</span>
                                <a href={pl.link} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-500 hover:underline">
                                    {pl.link}
                                </a>
                            </div>
                            <button onClick={() => handleDelete(pl.id)} className="text-xs text-muted-foreground hover:text-destructive">
                                Remove
                            </button>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
