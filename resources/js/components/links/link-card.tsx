import { router } from '@inertiajs/react';
import axios from 'axios';
import { Archive, Star } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import * as linksRoute from '@/routes/links';
import { LinkSourceBadge } from './source-badge';
import { faviconFor, type LinkItem, type TagOption } from './types';

interface LinkCardProps {
    link: LinkItem;
    onOpen: (link: LinkItem) => void;
    onTagClick?: (tag: TagOption) => void;
    onArchived?: () => void;
    selectable?: boolean;
    selected?: boolean;
    onToggleSelect?: (link: LinkItem) => void;
    reloadProps?: string[];
}

export function LinkCard({
    link,
    onOpen,
    onTagClick,
    onArchived,
    selectable = false,
    selected = false,
    onToggleSelect,
    reloadProps = ['links'],
}: LinkCardProps) {
    const favicon = faviconFor(link);

    function reload() {
        router.reload({ only: reloadProps });
    }

    async function handleToggleFavorite(e: React.MouseEvent) {
        e.stopPropagation();
        await axios.patch(linksRoute.toggleFavorite(link.id).url);
        reload();
    }

    function handleVisit(e: React.MouseEvent) {
        e.stopPropagation();
        if (link.read_at === null) {
            void axios.patch(linksRoute.toggleRead(link.id).url).then(reload);
        }
    }

    function handleArchive(e: React.MouseEvent) {
        e.stopPropagation();
        router.patch(
            linksRoute.archive(link.id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => onArchived?.(),
            },
        );
    }

    function handleKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Enter' && e.target === e.currentTarget) {
            onOpen(link);
        }
    }

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={() => onOpen(link)}
            onKeyDown={handleKeyDown}
            aria-label={`Details for ${link.title || link.link}`}
            className={`group flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-accent ${selected ? 'border-primary bg-accent/50' : 'border-border'}`}
        >
            {selectable && (
                <span
                    onClick={(e) => e.stopPropagation()}
                    className="mt-1 flex items-center"
                >
                    <Checkbox
                        checked={selected}
                        onCheckedChange={() => onToggleSelect?.(link)}
                        aria-label={`Select ${link.title || link.link}`}
                    />
                </span>
            )}

            {favicon && (
                <img
                    src={favicon}
                    alt=""
                    className="mt-0.5 size-5 shrink-0 rounded"
                    onError={(e) => {
                        e.currentTarget.style.display = 'none';
                    }}
                />
            )}

            <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                <span className="flex items-center gap-2">
                    {link.read_at === null && (
                        <span
                            className="size-2 shrink-0 rounded-full bg-primary"
                            title="Unread"
                        />
                    )}
                    <a
                        href={link.link}
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick={handleVisit}
                        className="truncate font-medium hover:underline"
                        title={`Open ${link.link}`}
                    >
                        {link.title || link.link}
                    </a>
                    {link.rating !== null && (
                        <span className="flex shrink-0 items-center gap-0.5 text-xs text-muted-foreground">
                            <Star className="size-3 fill-yellow-400 text-yellow-500" />
                            {link.rating}
                        </span>
                    )}
                </span>
                <span className="truncate text-sm text-muted-foreground">
                    {link.link}
                </span>
                {link.description && (
                    <span className="line-clamp-2 text-sm text-muted-foreground">
                        {link.description}
                    </span>
                )}
                {link.tags.length > 0 && (
                    <span className="flex flex-wrap gap-1">
                        {link.tags.map((tag) => (
                            <button
                                key={tag.id}
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onTagClick?.(tag);
                                }}
                                className={`rounded-full bg-accent px-2 py-0.5 text-xs ${onTagClick ? 'hover:bg-primary hover:text-primary-foreground' : 'cursor-default'}`}
                                title={
                                    onTagClick
                                        ? `Filter by ${tag.name}`
                                        : undefined
                                }
                            >
                                {tag.name}
                            </button>
                        ))}
                    </span>
                )}
            </span>

            <span className="flex shrink-0 items-center gap-1.5">
                <LinkSourceBadge source={link.source} variant="icon" />
                <span
                    className="hidden text-xs text-muted-foreground sm:inline"
                    title={link.created_at_with_time}
                >
                    {link.created_at}
                </span>
                {onArchived && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={handleArchive}
                        title="Archive"
                        className="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                    >
                        <Archive className="size-4" />
                    </Button>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={handleToggleFavorite}
                    title={
                        link.is_favorite
                            ? 'Remove from favorites'
                            : 'Add to favorites'
                    }
                    aria-pressed={link.is_favorite}
                    className={
                        link.is_favorite
                            ? ''
                            : 'opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100'
                    }
                >
                    <Star
                        className={`size-4 ${link.is_favorite ? 'fill-yellow-400 text-yellow-500' : ''}`}
                    />
                </Button>
            </span>
        </div>
    );
}
