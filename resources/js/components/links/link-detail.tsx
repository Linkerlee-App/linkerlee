import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import {
    Archive,
    BookmarkCheck,
    Check,
    Copy,
    ExternalLink,
    Pencil,
    Star,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
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
import * as linksRoute from '@/routes/links';
import { TagPicker } from './tag-picker';
import {
    faviconFor,
    type GroupOption,
    type LinkItem,
    type TagOption,
} from './types';

interface EditLinkFormProps {
    link: LinkItem;
    allTags: TagOption[];
    allGroups: GroupOption[];
    onCancel: () => void;
    onSaved: () => void;
}

export function EditLinkForm({
    link,
    allTags,
    allGroups,
    onCancel,
    onSaved,
}: EditLinkFormProps) {
    const form = useForm({
        link: link.link,
        title: link.title ?? '',
        description: link.description ?? '',
        tags: link.tag_ids,
        newTags: [] as string[],
        groups: link.group_ids,
    });

    function toggleTag(id: number) {
        form.setData(
            'tags',
            form.data.tags.includes(id)
                ? form.data.tags.filter((t) => t !== id)
                : [...form.data.tags, id],
        );
    }

    function toggleGroup(id: number) {
        form.setData(
            'groups',
            form.data.groups.includes(id)
                ? form.data.groups.filter((g) => g !== id)
                : [...form.data.groups, id],
        );
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.put(linksRoute.update(link.id).url, {
            onSuccess: () => onSaved(),
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
                    Description{' '}
                    <span className="text-xs text-muted-foreground">
                        (optional)
                    </span>
                </Label>
                <textarea
                    id="edit-description"
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    rows={3}
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
                />
                <InputError message={form.errors.description} />
            </div>

            <TagPicker
                allTags={allTags}
                selectedIds={form.data.tags}
                newTags={form.data.newTags}
                onToggle={toggleTag}
                onAddNew={(name) =>
                    form.setData('newTags', [...form.data.newTags, name])
                }
                onRemoveNew={(name) =>
                    form.setData(
                        'newTags',
                        form.data.newTags.filter((t) => t !== name),
                    )
                }
            />

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
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={onCancel}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}

interface LinkDetailViewProps {
    link: LinkItem;
    allTags: TagOption[];
    allGroups: GroupOption[];
    variant?: 'sheet' | 'page';
    onClose?: () => void;
    reloadProps?: string[];
}

export function LinkDetailView({
    link,
    allTags,
    allGroups,
    variant = 'sheet',
    onClose,
    reloadProps = ['links'],
}: LinkDetailViewProps) {
    const [isFavorite, setIsFavorite] = useState(link.is_favorite);
    const [rating, setRating] = useState(link.rating);
    const [readAt, setReadAt] = useState(link.read_at);
    const [isEditing, setIsEditing] = useState(false);
    const [copied, setCopied] = useState(false);
    const [confirmArchive, setConfirmArchive] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const [prevLink, setPrevLink] = useState(link);
    if (prevLink !== link) {
        setPrevLink(link);
        setIsFavorite(link.is_favorite);
        setRating(link.rating);
        setReadAt(link.read_at);
    }

    const favicon = faviconFor(link);

    function reload() {
        if (variant === 'sheet') {
            router.reload({ only: reloadProps });
        } else {
            router.reload();
        }
    }

    async function handleToggleFavorite() {
        const { data } = await axios.patch<{ is_favorite: boolean }>(
            linksRoute.toggleFavorite(link.id).url,
        );
        setIsFavorite(data.is_favorite);
        reload();
    }

    async function handleToggleRead() {
        const { data } = await axios.patch<{ read_at: string | null }>(
            linksRoute.toggleRead(link.id).url,
        );
        setReadAt(data.read_at);
        reload();
    }

    function handleOpen() {
        if (readAt === null) {
            void handleToggleRead();
        }
    }

    async function handleRate(value: number) {
        const newRating = rating === value ? null : value;
        const { data } = await axios.patch<{ rating: number | null }>(
            linksRoute.rate(link.id).url,
            { rating: newRating },
        );
        setRating(data.rating);
        reload();
    }

    async function handleCopy() {
        await navigator.clipboard.writeText(link.link);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    }

    function afterRemoval() {
        if (variant === 'sheet') {
            setConfirmArchive(false);
            setConfirmDelete(false);
            onClose?.();
        } else {
            router.visit(linksRoute.index().url);
        }
    }

    function handleArchive() {
        router.patch(
            linksRoute.archive(link.id).url,
            {},
            {
                onSuccess: afterRemoval,
                only: variant === 'sheet' ? reloadProps : undefined,
                preserveScroll: true,
            },
        );
    }

    function handleDelete() {
        router.delete(linksRoute.destroy(link.id).url, {
            onSuccess: afterRemoval,
            only: variant === 'sheet' ? reloadProps : undefined,
            preserveScroll: true,
        });
    }

    return (
        <div
            className={
                variant === 'sheet'
                    ? 'flex flex-1 flex-col overflow-y-auto p-4'
                    : 'flex flex-col'
            }
        >
            <div className="flex items-start gap-3">
                {favicon && (
                    <img
                        src={favicon}
                        alt=""
                        className="mt-1 size-8 shrink-0 rounded border border-border bg-muted"
                        onError={(e) => {
                            e.currentTarget.style.display = 'none';
                        }}
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
                        onClick={handleOpen}
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
                    title={
                        isFavorite
                            ? 'Remove from favorites'
                            : 'Add to favorites'
                    }
                    aria-pressed={isFavorite}
                    className="shrink-0"
                >
                    <Star
                        className={
                            isFavorite ? 'fill-yellow-400 text-yellow-500' : ''
                        }
                    />
                </Button>
            </div>

            <div className="mt-3 flex flex-wrap gap-2">
                <Button asChild size="sm">
                    <a
                        href={link.link}
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick={handleOpen}
                    >
                        <ExternalLink />
                        Open link
                    </a>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleCopy}
                >
                    {copied ? <Check /> : <Copy />}
                    {copied ? 'Copied' : 'Copy URL'}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleToggleRead}
                    title={readAt === null ? 'Mark as read' : 'Mark as unread'}
                >
                    <BookmarkCheck
                        className={readAt !== null ? 'text-green-600' : ''}
                    />
                    {readAt === null ? 'Mark read' : 'Read'}
                </Button>
            </div>

            {link.preview_image_url && !isEditing && (
                <img
                    src={link.preview_image_url}
                    alt=""
                    className="mt-4 max-h-48 w-full rounded-md border border-border object-cover"
                    onError={(e) => {
                        e.currentTarget.style.display = 'none';
                    }}
                />
            )}

            {isEditing ? (
                <div className="mt-4">
                    <EditLinkForm
                        link={link}
                        allTags={allTags}
                        allGroups={allGroups}
                        onCancel={() => setIsEditing(false)}
                        onSaved={() => {
                            setIsEditing(false);
                            reload();
                        }}
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
                            <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Rating
                            </span>
                            <div className="flex items-center gap-0.5">
                                {[1, 2, 3, 4, 5].map((star) => {
                                    const active =
                                        rating !== null && rating >= star;
                                    return (
                                        <button
                                            key={star}
                                            type="button"
                                            onClick={() => handleRate(star)}
                                            className="rounded p-0.5 text-muted-foreground transition-colors hover:text-yellow-500 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            title={`Rate ${star} star${star > 1 ? 's' : ''}`}
                                            aria-label={`Rate ${star} star${star > 1 ? 's' : ''}`}
                                        >
                                            <Star
                                                className={`size-5 ${active ? 'fill-yellow-400 text-yellow-500' : ''}`}
                                            />
                                        </button>
                                    );
                                })}
                                {rating !== null && (
                                    <span className="ml-1 text-xs text-muted-foreground">
                                        {rating}/5
                                    </span>
                                )}
                            </div>
                        </div>

                        {link.tags.length > 0 && (
                            <div className="flex flex-col gap-1.5">
                                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Tags
                                </span>
                                <div className="flex flex-wrap gap-1.5">
                                    {link.tags.map((tag) => (
                                        <Badge key={tag.id} variant="secondary">
                                            {tag.name}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        {link.linkGroups.length > 0 && (
                            <div className="flex flex-col gap-1.5">
                                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Collections
                                </span>
                                <div className="flex flex-wrap gap-1.5">
                                    {link.linkGroups.map((group) => (
                                        <Badge key={group.id} variant="outline">
                                            {group.title}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        <p
                            className="text-xs text-muted-foreground"
                            title={link.created_at_with_time}
                        >
                            Added {link.created_at}
                        </p>
                    </div>

                    <div
                        className={`${variant === 'sheet' ? 'sticky bottom-0 -mx-4 mt-auto border-t px-4 pt-4' : 'mt-6'} flex flex-wrap gap-2 bg-background`}
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setIsEditing(true)}
                        >
                            <Pencil />
                            Edit
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setConfirmArchive(true)}
                        >
                            <Archive />
                            Archive
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            className="ml-auto"
                            onClick={() => setConfirmDelete(true)}
                        >
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
                            It will be hidden from your main list. You can
                            restore it later from the trash view.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmArchive(false)}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handleArchive}>Archive</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this link?</DialogTitle>
                        <DialogDescription>
                            This action cannot be undone. The link and all its
                            metadata will be permanently removed.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDelete(false)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
