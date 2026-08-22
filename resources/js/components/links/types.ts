export interface TagOption {
    id: number;
    name: string;
}

/**
 * An active tag filter. `id` is null when the name no longer matches a tag
 * (renamed or deleted), which still filters the listing and stays visible.
 */
export interface FilterTag {
    id: number | null;
    name: string;
}

export interface GroupOption {
    id: number;
    title: string;
}

/**
 * How a link was captured. Null on links saved before the source was recorded.
 */
export type LinkSource = 'web' | 'extension' | 'api' | 'email' | 'import';

export interface LinkItem {
    id: number;
    title: string | null;
    description: string | null;
    link: string;
    is_favorite: boolean;
    rating: number | null;
    read_at: string | null;
    source: LinkSource | null;
    favicon_url: string | null;
    preview_image_url: string | null;
    tags: TagOption[];
    tag_ids: number[];
    linkGroups: GroupOption[];
    group_ids: number[];
    created_at: string;
    created_at_with_time: string;
}

export interface CursorPaginator<T> {
    data: T[];
    next_cursor: string | null;
    path: string;
}

export function fallbackFaviconUrl(url: string): string | null {
    try {
        const host = new URL(url).hostname;
        return `https://www.google.com/s2/favicons?sz=64&domain=${host}`;
    } catch {
        return null;
    }
}

export function faviconFor(link: LinkItem): string | null {
    return link.favicon_url ?? fallbackFaviconUrl(link.link);
}
