import { router } from '@inertiajs/react';
import { BookmarkCheck, ChevronDown, Search, Star, TagIcon, X } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import type { FilterTag, TagOption } from './types';

export interface LinkFilters {
    searchString: string;
    filteredTags: FilterTag[];
    showFavoritesOnly: boolean;
    showUnreadOnly: boolean;
    showUntaggedOnly: boolean;
}

interface FilterBarProps {
    baseUrl: string;
    filters: LinkFilters;
    allTags: TagOption[];
}

function buildParams(filters: LinkFilters): Record<string, string | number | string[]> {
    const params: Record<string, string | number | string[]> = {};

    if (filters.searchString) { params.search = filters.searchString; }
    if (filters.filteredTags.length > 0) { params.tags = filters.filteredTags.map((tag) => tag.name); }
    if (filters.showFavoritesOnly) { params.favorite = 1; }
    if (filters.showUnreadOnly) { params.unreadOnly = 1; }
    if (filters.showUntaggedOnly) { params.untaggedOnly = 1; }

    return params;
}

export function navigateWithFilters(baseUrl: string, filters: LinkFilters) {
    router.get(baseUrl, buildParams(filters), { preserveState: true, preserveScroll: true });
}

export function FilterBar({ baseUrl, filters, allTags }: FilterBarProps) {
    const [search, setSearch] = useState(filters.searchString);
    const [tagQuery, setTagQuery] = useState('');
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [prevSearchProp, setPrevSearchProp] = useState(filters.searchString);
    if (prevSearchProp !== filters.searchString) {
        setPrevSearchProp(filters.searchString);
        if (debounce.current === null) {
            setSearch(filters.searchString);
        }
    }

    /**
     * The tag menu stays open so several tags can be picked in a row, but the
     * props only catch up once each navigation lands. Selections are tracked
     * locally in the meantime so a quick second pick doesn't drop the first.
     */
    const [pendingTags, setPendingTags] = useState<FilterTag[] | null>(null);
    const [prevTagsProp, setPrevTagsProp] = useState(filters.filteredTags);
    if (prevTagsProp !== filters.filteredTags) {
        setPrevTagsProp(filters.filteredTags);
        setPendingTags(null);
    }

    const activeTags = pendingTags ?? filters.filteredTags;

    function apply(next: Partial<LinkFilters>) {
        navigateWithFilters(baseUrl, { ...filters, searchString: search, filteredTags: activeTags, ...next });
    }

    function applyTags(nextTags: FilterTag[], next: Partial<LinkFilters> = {}) {
        setPendingTags(nextTags);
        apply({ filteredTags: nextTags, ...next });
    }

    function handleSearchChange(value: string) {
        setSearch(value);
        if (debounce.current) { clearTimeout(debounce.current); }
        debounce.current = setTimeout(() => {
            debounce.current = null;
            navigateWithFilters(baseUrl, { ...filters, searchString: value });
        }, 350);
    }

    function toggleTag(tag: TagOption) {
        if (activeTags.some((t) => t.name === tag.name)) {
            applyTags(activeTags.filter((t) => t.name !== tag.name));

            return;
        }

        applyTags([...activeTags, tag], { showUntaggedOnly: false });
    }

    const matchingTags = useMemo(() => {
        const query = tagQuery.trim().toLowerCase();

        return query === ''
            ? allTags
            : allTags.filter((tag) => tag.name.toLowerCase().includes(query));
    }, [allTags, tagQuery]);

    const hasActiveFilters = filters.searchString !== ''
        || activeTags.length > 0
        || filters.showFavoritesOnly
        || filters.showUnreadOnly
        || filters.showUntaggedOnly;

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative min-w-48 flex-1 sm:max-w-sm">
                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={search}
                        onChange={(e) => handleSearchChange(e.target.value)}
                        placeholder="Search title, URL or page content…"
                        className="pl-8"
                        aria-label="Search links"
                    />
                </div>
                <DropdownMenu onOpenChange={(open) => { if (! open) { setTagQuery(''); } }}>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant={activeTags.length > 0 ? 'default' : 'outline'}
                            size="sm"
                            disabled={allTags.length === 0}
                        >
                            <TagIcon />
                            {activeTags.length > 0
                                ? `Tags (${activeTags.length})`
                                : 'Tags'}
                            <ChevronDown />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="max-h-80 w-56 overflow-y-auto">
                        <DropdownMenuLabel>Filter by tag</DropdownMenuLabel>
                        <div className="px-1 pb-1">
                            <Input
                                type="search"
                                value={tagQuery}
                                onChange={(e) => setTagQuery(e.target.value)}
                                onKeyDown={(e) => e.stopPropagation()}
                                placeholder="Find a tag…"
                                className="h-8"
                                aria-label="Find a tag"
                            />
                        </div>
                        {matchingTags.length === 0 && (
                            <p className="px-2 py-1.5 text-xs text-muted-foreground">No tags match.</p>
                        )}
                        {matchingTags.map((tag) => (
                            <DropdownMenuCheckboxItem
                                key={tag.id}
                                checked={activeTags.some((t) => t.name === tag.name)}
                                onSelect={(e) => { e.preventDefault(); toggleTag(tag); }}
                            >
                                {tag.name}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button
                    variant={filters.showFavoritesOnly ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => apply({ showFavoritesOnly: ! filters.showFavoritesOnly })}
                    aria-pressed={filters.showFavoritesOnly}
                >
                    <Star className={filters.showFavoritesOnly ? '' : 'text-yellow-500'} />
                    Favorites
                </Button>
                <Button
                    variant={filters.showUnreadOnly ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => apply({ showUnreadOnly: ! filters.showUnreadOnly })}
                    aria-pressed={filters.showUnreadOnly}
                >
                    <BookmarkCheck />
                    Unread
                </Button>
                <Button
                    variant={filters.showUntaggedOnly ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => applyTags(
                        filters.showUntaggedOnly ? activeTags : [],
                        { showUntaggedOnly: ! filters.showUntaggedOnly },
                    )}
                    aria-pressed={filters.showUntaggedOnly}
                >
                    <TagIcon />
                    Untagged
                </Button>
                {hasActiveFilters && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setSearch('');
                            setPendingTags([]);
                            navigateWithFilters(baseUrl, {
                                searchString: '',
                                filteredTags: [],
                                showFavoritesOnly: false,
                                showUnreadOnly: false,
                                showUntaggedOnly: false,
                            });
                        }}
                    >
                        <X />
                        Clear
                    </Button>
                )}
            </div>

            {activeTags.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="text-xs text-muted-foreground">Tags:</span>
                    {activeTags.map((tag) => (
                        <button
                            key={tag.name}
                            type="button"
                            onClick={() => applyTags(activeTags.filter((t) => t.name !== tag.name))}
                            className="flex items-center gap-1 rounded-full bg-primary px-2.5 py-0.5 text-xs text-primary-foreground hover:opacity-80"
                            title={`Stop filtering by ${tag.name}`}
                        >
                            {tag.name}
                            <X className="size-3" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
