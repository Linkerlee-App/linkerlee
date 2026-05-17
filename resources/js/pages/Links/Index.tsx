import { Head, WhenVisible, useForm, router } from '@inertiajs/react';
import axios from 'axios';
import { Archive, Check, Copy, ExternalLink, Pencil, Star, Trash2 } from 'lucide-react';
import { useState, useRef } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
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

function faviconUrl(url: string): string | null {
    try {
        const host = new URL(url).hostname;
        return `https://www.google.com/s2/favicons?sz=64&domain=${host}`;
    } catch {
        return null;
    }
}

function EditLinkForm({
    link,
    allTags,
    allGroups,
    onCancel,
    onSaved,
}: {
    link: LinkItem;
    allTags: Tag[];
    allGroups: Group[];
    onCancel: () => void;
    onSaved: () => void;
}) {
    const form = useForm({
        link: link.link,
        title: link.title ?? '',
        description: link.description ?? '',
        tags: link.tag_ids,
        newTags: [] as string[],
        groups: link.group_ids,
    });

    const [tagInput, setTagInput] = useState('');

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
        form.put(linksRoute.update(link.id).url, {
            onSuccess: () => {
                onSaved();
                router.reload({ only: ['links'] });
            },
        });
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="edit-link">URL *</Label>
                <Input
                    id="edit-link"
                    type="text"
                    value={form.data.link}
                    onChange={(e) => form.setData('link', e.target.value)}
                />
                <InputError message={form.errors.link} />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="edit-title">Title</Label>
                <Input
                    id="edit-title"
                    type="text"
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="edit-description">
                    Description <span className="text-xs text-muted-foreground">(optional)</span>
                </Label>
                <textarea
                    id="edit-description"
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
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

            <div className="sticky bottom-0 -mx-4 flex gap-2 border-t bg-background px-4 pt-4">
                <Button type="submit" size="sm" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save changes'}
                </Button>
                <Button type="button" size="sm" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

function LinkDetailView({
    link,
    onClose,
    allTags,
    allGroups,
}: {
    link: LinkItem;
    onClose: () => void;
    allTags: Tag[];
    allGroups: Group[];
}) {
    const [isFavorite, setIsFavorite] = useState(link.is_favorite);
    const [rating, setRating] = useState(link.rating);
    const [isEditing, setIsEditing] = useState(false);
    const [copied, setCopied] = useState(false);
    const [confirmArchive, setConfirmArchive] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const favicon = faviconUrl(link.link);

    async function handleToggleFavorite() {
        const { data } = await axios.patch<{ is_favorite: boolean }>(linksRoute.toggleFavorite(link.id).url);
        setIsFavorite(data.is_favorite);
        router.reload({ only: ['links'] });
    }

    async function handleRate(value: number) {
        const newRating = rating === value ? null : value;
        const { data } = await axios.patch<{ rating: number | null }>(linksRoute.rate(link.id).url, { rating: newRating });
        setRating(data.rating);
        router.reload({ only: ['links'] });
    }

    async function handleCopy() {
        await navigator.clipboard.writeText(link.link);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    }

    function handleArchive() {
        router.patch(linksRoute.archive(link.id).url, {}, {
            onSuccess: () => {
                setConfirmArchive(false);
                onClose();
            },
            only: ['links'],
            preserveScroll: true,
        });
    }

    function handleDelete() {
        router.delete(linksRoute.destroy(link.id).url, {
            onSuccess: () => {
                setConfirmDelete(false);
                onClose();
            },
            only: ['links'],
            preserveScroll: true,
        });
    }

    return (
        <div className="flex flex-1 flex-col overflow-y-auto p-4">
            <div className="flex items-start gap-3">
                {favicon && (
                    <img
                        src={favicon}
                        alt=""
                        className="mt-1 size-8 shrink-0 rounded border border-border bg-muted"
                        onError={(e) => { e.currentTarget.style.display = 'none'; }}
                    />
                )}
                <div className="min-w-0 flex-1">
                    <h2 className="text-lg leading-tight font-semibold break-words">
                        {link.title || '(no title)'}
                    </h2>
                    <a
                        href={link.link}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mt-1 block truncate text-sm text-muted-foreground hover:text-primary hover:underline"
                        title={link.link}
                    >
                        {link.link}
                    </a>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={handleToggleFavorite}
                    title={isFavorite ? 'Remove from favorites' : 'Add to favorites'}
                    aria-pressed={isFavorite}
                    className="shrink-0"
                >
                    <Star className={isFavorite ? 'fill-yellow-400 text-yellow-500' : ''} />
                </Button>
            </div>

            <div className="mt-3 flex gap-2">
                <Button asChild size="sm">
                    <a href={link.link} target="_blank" rel="noopener noreferrer">
                        <ExternalLink />
                        Open link
                    </a>
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={handleCopy}>
                    {copied ? <Check /> : <Copy />}
                    {copied ? 'Copied' : 'Copy URL'}
                </Button>
            </div>

            {isEditing ? (
                <div className="mt-4">
                    <EditLinkForm
                        link={link}
                        allTags={allTags}
                        allGroups={allGroups}
                        onCancel={() => setIsEditing(false)}
                        onSaved={() => setIsEditing(false)}
                    />
                </div>
            ) : (
                <>
                    {link.description && (
                        <>
                            <Separator className="my-4" />
                            <p className="text-sm whitespace-pre-wrap text-foreground/90">
                                {link.description}
                            </p>
                        </>
                    )}

                    <Separator className="my-4" />

                    <div className="flex flex-col gap-3">
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Rating</span>
                            <div className="flex items-center gap-0.5">
                                {[1, 2, 3, 4, 5].map((star) => {
                                    const active = rating !== null && rating >= star;
                                    return (
                                        <button
                                            key={star}
                                            type="button"
                                            onClick={() => handleRate(star)}
                                            className="rounded p-0.5 text-muted-foreground transition-colors hover:text-yellow-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title={`Rate ${star} star${star > 1 ? 's' : ''}`}
                                            aria-label={`Rate ${star} star${star > 1 ? 's' : ''}`}
                                        >
                                            <Star className={`size-5 ${active ? 'fill-yellow-400 text-yellow-500' : ''}`} />
                                        </button>
                                    );
                                })}
                                {rating !== null && (
                                    <span className="ml-1 text-xs text-muted-foreground">{rating}/5</span>
                                )}
                            </div>
                        </div>

                        {link.tags.length > 0 && (
                            <div className="flex flex-col gap-1.5">
                                <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tags</span>
                                <div className="flex flex-wrap gap-1.5">
                                    {link.tags.map((tag) => (
                                        <Badge key={tag.id} variant="secondary">{tag.name}</Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        {link.linkGroups.length > 0 && (
                            <div className="flex flex-col gap-1.5">
                                <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Collections</span>
                                <div className="flex flex-wrap gap-1.5">
                                    {link.linkGroups.map((group) => (
                                        <Badge key={group.id} variant="outline">{group.title}</Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        <p className="text-xs text-muted-foreground" title={link.created_at_with_time}>
                            Added {link.created_at}
                        </p>
                    </div>

                    <div className="sticky bottom-0 -mx-4 mt-auto flex flex-wrap gap-2 border-t bg-background px-4 pt-4">
                        <Button type="button" variant="outline" size="sm" onClick={() => setIsEditing(true)}>
                            <Pencil />
                            Edit
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={() => setConfirmArchive(true)}>
                            <Archive />
                            Archive
                        </Button>
                        <Button type="button" variant="destructive" size="sm" className="ml-auto" onClick={() => setConfirmDelete(true)}>
                            <Trash2 />
                            Delete
                        </Button>
                    </div>
                </>
            )}

            <Dialog open={confirmArchive} onOpenChange={setConfirmArchive}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Archive this link?</DialogTitle>
                        <DialogDescription>
                            It will be hidden from your main list. You can restore it later from the trash view.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmArchive(false)}>Cancel</Button>
                        <Button onClick={handleArchive}>Archive</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this link?</DialogTitle>
                        <DialogDescription>
                            This action cannot be undone. The link and all its metadata will be permanently removed.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmDelete(false)}>Cancel</Button>
                        <Button variant="destructive" onClick={handleDelete}>Delete</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
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
                        <LinkDetailView
                            link={selectedLink}
                            onClose={() => setSelectedLink(null)}
                            allTags={allTags}
                            allGroups={allGroups}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
