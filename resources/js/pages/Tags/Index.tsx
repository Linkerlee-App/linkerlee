import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import * as tagsRoute from '@/routes/tags';
import type { BreadcrumbItem } from '@/types';

interface Tag {
    id: number;
    name: string;
    links_count: number;
}

interface Props {
    tags: Tag[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tags', href: tagsRoute.index().url },
];

export default function TagsIndex({ tags }: Props) {
    function handleDelete(tag: Tag) {
        if (confirm(`Delete tag "${tag.name}"?`)) {
            router.delete(tagsRoute.destroy(tag.id).url);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tags" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Tags</h1>

                {tags.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No tags yet.
                    </p>
                )}

                <div className="flex flex-wrap gap-2">
                    {tags.map((tag) => (
                        <div
                            key={tag.id}
                            className="flex items-center gap-1 rounded-full border border-border px-3 py-1"
                        >
                            <span className="text-sm">{tag.name}</span>
                            <span
                                className="rounded-full bg-accent px-1.5 text-xs text-muted-foreground"
                                title={`${tag.links_count} link${tag.links_count !== 1 ? 's' : ''}`}
                            >
                                {tag.links_count}
                            </span>
                            <button
                                onClick={() => handleDelete(tag)}
                                className="ml-1 text-xs text-muted-foreground hover:text-destructive"
                            >
                                ×
                            </button>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
