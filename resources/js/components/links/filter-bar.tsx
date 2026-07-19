import { router } from '@inertiajs/react';
import { BookmarkCheck, Search, Star, TagIcon, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { TagOption } from './types';

export interface LinkFilters {
    searchString: string;
    filteredTags: TagOption[];
    showFavoritesOnly: boolean;
    showUnreadOnly: boolean;
    showUntaggedOnly: boolean;
}

interface FilterBarProps {
    baseUrl: string;
    filters: LinkFilters;
}

function buildParams(filters: LinkFilters): Record<string, string | number> {
    const params: Record<string, string | number> = {};

    if (filters.searchString) { params.search = filters.searchString; }
    if (filters.filteredTags.length > 0) { params.tags = filters.filteredTags.map((tag) => tag.name).join(','); }
    if (filters.showFavoritesOnly) { params.favorite = 1; }
    if (filters.showUnreadOnly) { params.unreadOnly = 1; }
    if (filters.showUntaggedOnly) { params.untaggedOnly = 1; }

    return params;
}

export function navigateWithFilters(baseUrl: string, filters: LinkFilters) {
    router.get(baseUrl, buildParams(filters), { preserveState: true, preserveScroll: true });
}

export function FilterBar({ baseUrl, filters }: FilterBarProps) {
    const [search, setSearch] = useState(filters.searchString);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [prevSearchProp, setPrevSearchProp] = useState(filters.searchString);
    if (prevSearchProp !== filters.searchString) {
        setPrevSearchProp(filters.searchString);
        if (debounce.current === null) {
            setSearch(filters.searchString);
        }
    }

    function apply(next: Partial<LinkFilters>) {
        navigateWithFilters(baseUrl, { ...filters, searchString: search, ...next });
    }

    function handleSearchChange(value: string) {
        setSearch(value);
        if (debounce.current) { clearTimeout(debounce.current); }
        debounce.current = setTimeout(() => {
            debounce.current = null;
            navigateWithFilters(baseUrl, { ...filters, searchString: value });
        }, 350);
    }

    const hasActiveFilters = filters.searchString !== ''
        || filters.filteredTags.length > 0
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
                    onClick={() => apply({ showUntaggedOnly: ! filters.showUntaggedOnly })}
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

            {filters.filteredTags.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="text-xs text-muted-foreground">Tags:</span>
                    {filters.filteredTags.map((tag) => (
                        <button
                            key={tag.id}
                            type="button"
                            onClick={() => apply({ filteredTags: filters.filteredTags.filter((t) => t.id !== tag.id) })}
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
