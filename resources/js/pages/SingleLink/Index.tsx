import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import * as linksRoute from '@/routes/links';
import type { BreadcrumbItem } from '@/types';

interface Tag {
    id: number;
    name: string;
}

interface Group {
    id: number;
    title: string;
}

interface LinkDetail {
    id: number;
    title: string;
    description: string | null;
    link: string;
    is_favorite: boolean;
    rating: number | null;
    tags: Tag[];
    linkGroups: Group[];
    groups: number[];
    created_at: string;
    updated_at: string;
    created_at_with_time: string;
    updated_at_with_time: string;
}

interface Props {
    link: LinkDetail;
}

export default function SingleLinkIndex({ link }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Links', href: linksRoute.index().url },
        { title: link.title || link.link, href: linksRoute.show(link.id).url },
    ];

    function handleDelete() {
        if (confirm('Permanently delete this link?')) {
            router.delete(linksRoute.destroy(link.id).url);
        }
    }

    function handleArchive() {
        router.patch(linksRoute.archive(link.id).url);
    }

    function handleToggleFavorite() {
        router.patch(linksRoute.toggleFavorite(link.id).url, {}, { preserveScroll: true });
    }

    function handleRate(value: number) {
        const newRating = link.rating === value ? null : value;
        router.patch(linksRoute.rate(link.id).url, { rating: newRating }, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={link.title || link.link} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-start justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-xl font-semibold">{link.title || '(no title)'}</h1>
                        <a href={link.link} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-500 hover:underline">
                            {link.link}
                        </a>
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={handleToggleFavorite}
                            className={`rounded-md border px-3 py-1.5 text-sm ${link.is_favorite ? 'border-yellow-400 bg-yellow-50 text-yellow-700' : 'border-border'}`}
                            title={link.is_favorite ? 'Remove from favorites' : 'Add to favorites'}
                        >
                            {link.is_favorite ? '★ Favorited' : '☆ Favorite'}
                        </button>
                        <Link href={linksRoute.edit(link.id).url} className="rounded-md border border-border px-3 py-1.5 text-sm">
                            Edit
                        </Link>
                        <button onClick={handleArchive} className="rounded-md border border-border px-3 py-1.5 text-sm">
                            Archive
                        </button>
                        <button onClick={handleDelete} className="rounded-md bg-destructive px-3 py-1.5 text-sm text-destructive-foreground">
                            Delete
                        </button>
                    </div>
                </div>

                {link.description && (
                    <p className="text-sm text-muted-foreground">{link.description}</p>
                )}

                <div className="flex items-center gap-1">
                    {[1, 2, 3, 4, 5].map((star) => (
                        <button
                            key={star}
                            onClick={() => handleRate(star)}
                            className={`text-xl ${link.rating !== null && link.rating >= star ? 'text-yellow-400' : 'text-muted-foreground'}`}
                            title={`Rate ${star} star${star > 1 ? 's' : ''}`}
                        >
                            ★
                        </button>
                    ))}
                    {link.rating !== null && (
                        <span className="ml-1 text-xs text-muted-foreground">{link.rating}/5</span>
                    )}
                </div>

                {link.tags.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {link.tags.map((tag) => (
                            <span key={tag.id} className="rounded-full bg-accent px-2 py-0.5 text-xs">
                                {tag.name}
                            </span>
                        ))}
                    </div>
                )}

                {link.linkGroups.length > 0 && (
                    <div className="flex flex-col gap-1">
                        <span className="text-sm font-medium">Collections</span>
                        <div className="flex flex-wrap gap-1">
                            {link.linkGroups.map((group) => (
                                <span key={group.id} className="rounded-md border border-border px-2 py-0.5 text-xs">
                                    {group.title}
                                </span>
                            ))}
                        </div>
                    </div>
                )}

                <p className="text-xs text-muted-foreground" title={link.created_at_with_time}>
                    Added {link.created_at}
                </p>
            </div>
        </AppLayout>
    );
}
