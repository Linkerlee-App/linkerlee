import { Head, Link, WhenVisible, useForm, router } from '@inertiajs/react';
import { useState, useRef } from 'react';
import axios from 'axios';
import AppLayout from '@/layouts/app-layout';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { BreadcrumbItem } from '@/types';
import * as linksRoute from '@/routes/links';

interface Tag {
    id: number;
    name: string;
}

interface Group {
    id: number;
    title: string;
}

interface LinkItem {
    id: number;
    title: string;
    description: string | null;
    link: string;
    is_favorite: boolean;
    rating: number | null;
    tags: Tag[];
    tag_ids: number[];
    linkGroups: Group[];
    group_ids: number[];
    created_at: string;
    created_at_with_time: string;
}

interface CursorPaginator<T> {
    data: T[];
    next_cursor: string | null;
    path: string;
}

interface Props {
    links: CursorPaginator<LinkItem>;
    searchString: string;
    filteredTags: Tag[];
    showUntaggedOnly: boolean;
    allTags: Tag[];
    allGroups: Group[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Links', href: linksRoute.index().url },
];

function CreateLinkForm({ allTags, allGroups, onSuccess }: { allTags: Tag[]; allGroups: Group[]; onSuccess: () => void }) {
    const form = useForm({
        link: '',
        title: '',
        description: '',
        tags: [] as number[],
        newTags: [] as string[],
        groups: [] as number[],
    });

    const [tagInput, setTagInput] = useState('');
    const tagInputRef = useRef<HTMLInputElement>(null);

    function toggleTag(id: number) {
        form.setData('tags', form.data.tags.includes(id)
            ? form.data.tags.filter((t) => t !== id)
            : [...form.data.tags, id]);
    }

    function toggleGroup(id: number) {
        form.setData('groups', form.data.groups.includes(id)
            ? form.data.groups.filter((g) => g !== id)
            : [...form.data.groups, id]);
    }

    function addNewTag(name: string) {
        const trimmed = name.trim();
        if (trimmed.length < 2) { return; }
        if (form.data.newTags.includes(trimmed)) { return; }
        if (allTags.some((t) => t.name.toLowerCase() === trimmed.toLowerCase())) { return; }
        form.setData('newTags', [...form.data.newTags, trimmed]);
        setTagInput('');
    }

    function removeNewTag(name: string) {
        form.setData('newTags', form.data.newTags.filter((t) => t !== name));
    }

    function handleTagKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addNewTag(tagInput);
        }
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(linksRoute.store().url, { onSuccess });
    }

    return (
        <form onSubmit={submit} className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-link">URL *</Label>
                <Input
                    id="create-link"
                    type="text"
                    value={form.data.link}
                    onChange={(e) => form.setData('link', e.target.value)}
                    placeholder="https://example.com"
                    autoFocus
                />
                <InputError message={form.errors.link} />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-title">
                    Title <span className="text-muted-foreground text-xs">(optional)</span>
                </Label>
                <Input
                    id="create-title"
                    type="text"
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    placeholder="My favourite article"
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-description">
                    Description <span className="text-muted-foreground text-xs">(optional)</span>
                </Label>
                <textarea
                    id="create-description"
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
                    placeholder="What's this link about?"
                    rows={3}
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label>Tags</Label>
                <div className="flex flex-wrap gap-1.5">
                    {allTags.map((tag) => (
                        <button
                            key={tag.id}
                            type="button"
                            onClick={() => toggleTag(tag.id)}
                            className={`rounded-full border px-2.5 py-0.5 text-xs transition-colors ${form.data.tags.includes(tag.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'}`}
                        >
                            {tag.name}
                        </button>
                    ))}
                    {form.data.newTags.map((name) => (
                        <span key={name} className="flex items-center gap-1 rounded-full border border-dashed border-primary px-2.5 py-0.5 text-xs text-primary">
                            {name}
                            <button type="button" onClick={() => removeNewTag(name)} className="leading-none hover:opacity-70">&times;</button>
                        </span>
                    ))}
                </div>
                <input
                    ref={tagInputRef}
                    type="text"
                    value={tagInput}
                    onChange={(e) => setTagInput(e.target.value)}
                    onKeyDown={handleTagKeyDown}
                    onBlur={() => addNewTag(tagInput)}
                    placeholder="Add a tag…"
                    className="mt-1 w-full rounded-md border border-border bg-transparent px-3 py-1.5 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                />
            </div>

            {allGroups.length > 0 && (
                <div className="flex flex-col gap-1.5">
                    <Label>Collections</Label>
                    <div className="flex flex-wrap gap-1.5">
                        {allGroups.map((group) => (
                            <button
                                key={group.id}
                                type="button"
                                onClick={() => toggleGroup(group.id)}
                                className={`rounded-md border px-2.5 py-0.5 text-xs transition-colors ${form.data.groups.includes(group.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'}`}
                            >
                                {group.title}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            <div className="mt-auto flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save link'}
                </Button>
            </div>
        </form>
    );
}

function LinkDetailView({ link, onClose }: { link: LinkItem; onClose: () => void }) {
    const [isFavorite, setIsFavorite] = useState(link.is_favorite);
    const [rating, setRating] = useState(link.rating);

    async function handleToggleFavorite() {
        const { data } = await axios.patch<{ is_favorite: boolean }>(linksRoute.toggleFavorite(link.id).url);
        setIsFavorite(data.is_favorite);
    }

    async function handleRate(value: number) {
        const newRating = rating === value ? null : value;
        const { data } = await axios.patch<{ rating: number | null }>(linksRoute.rate(link.id).url, { rating: newRating });
        setRating(data.rating);
    }

    function handleArchive() {
        router.patch(linksRoute.archive(link.id).url, {}, {
            onSuccess: onClose,
            only: ['links'],
            preserveScroll: true,
        });
    }

    function handleDelete() {
        if (! confirm('Permanently delete this link?')) { return; }
        router.delete(linksRoute.destroy(link.id).url, {
            onSuccess: onClose,
            only: ['links'],
            preserveScroll: true,
        });
    }

    return (
        <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
            <div className="flex flex-col gap-1">
                <h2 className="text-lg font-semibold">{link.title || '(no title)'}</h2>
                <a href={link.link} target="_blank" rel="noopener noreferrer" className="break-all text-sm text-blue-500 hover:underline">
                    {link.link}
                </a>
            </div>

            {link.description && (
                <p className="text-sm text-muted-foreground">{link.description}</p>
            )}

            <div className="flex items-center gap-0.5">
                {[1, 2, 3, 4, 5].map((star) => (
                    <button
                        key={star}
                        onClick={() => handleRate(star)}
                        className={`text-xl ${rating !== null && rating >= star ? 'text-yellow-400' : 'text-muted-foreground'}`}
                        title={`Rate ${star} star${star > 1 ? 's' : ''}`}
                    >
                        ★
                    </button>
                ))}
                {rating !== null && (
                    <span className="ml-1 text-xs text-muted-foreground">{rating}/5</span>
                )}
            </div>

            {link.tags.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {link.tags.map((tag) => (
                        <span key={tag.id} className="rounded-full bg-accent px-2 py-0.5 text-xs">{tag.name}</span>
                    ))}
                </div>
            )}

            {link.linkGroups.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {link.linkGroups.map((group) => (
                        <span key={group.id} className="rounded-md border border-border px-2 py-0.5 text-xs">{group.title}</span>
                    ))}
                </div>
            )}

            <p className="text-xs text-muted-foreground" title={link.created_at_with_time}>
                Added {link.created_at}
            </p>

            <div className="mt-auto flex flex-wrap gap-2">
                <button
                    onClick={handleToggleFavorite}
                    className={`rounded-md border px-3 py-1.5 text-sm ${isFavorite ? 'border-yellow-400 bg-yellow-50 text-yellow-700' : 'border-border'}`}
                >
                    {isFavorite ? '★ Favorited' : '☆ Favorite'}
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
    );
}

export default function LinksIndex({ links: paginator, searchString, filteredTags, showUntaggedOnly, allTags, allGroups }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [selectedLink, setSelectedLink] = useState<LinkItem | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Links" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Links</h1>
                    <Button onClick={() => setCreateOpen(true)}>Add Link</Button>
                </div>

                <div className="flex flex-col gap-2">
                    {paginator.data.length === 0 && (
                        <p className="text-sm text-muted-foreground">No links yet.</p>
                    )}
                    {paginator.data.map((link) => (
                        <button
                            key={link.id}
                            onClick={() => setSelectedLink(link)}
                            className="flex flex-col gap-1.5 rounded-lg border border-border p-3 text-left hover:bg-accent"
                        >
                            <span className="font-medium">{link.title || link.link}</span>
                            <span className="truncate text-sm text-muted-foreground">{link.link}</span>
                            {link.tags.length > 0 && (
                                <div className="flex flex-wrap gap-1">
                                    {link.tags.map((tag) => (
                                        <span key={tag.id} className="rounded-full bg-accent px-2 py-0.5 text-xs">
                                            {tag.name}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </button>
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

            <Sheet open={createOpen} onOpenChange={setCreateOpen}>
                <SheetContent side="right" className="flex flex-col sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Add link</SheetTitle>
                    </SheetHeader>
                    <CreateLinkForm
                        allTags={allTags}
                        allGroups={allGroups}
                        onSuccess={() => setCreateOpen(false)}
                    />
                </SheetContent>
            </Sheet>

            <Sheet open={selectedLink !== null} onOpenChange={(open) => { if (! open) { setSelectedLink(null); } }}>
                <SheetContent side="right" className="flex flex-col sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Link details</SheetTitle>
                    </SheetHeader>
                    {selectedLink && (
                        <LinkDetailView link={selectedLink} onClose={() => setSelectedLink(null)} />
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
