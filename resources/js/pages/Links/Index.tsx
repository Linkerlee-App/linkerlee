import { Head, WhenVisible } from '@inertiajs/react';
import { useState } from 'react';
import { BulkBar } from '@/components/links/bulk-bar';
import { CreateLinkForm } from '@/components/links/create-link-form';
import { FilterBar, navigateWithFilters, type LinkFilters } from '@/components/links/filter-bar';
import { LinkCard } from '@/components/links/link-card';
import { LinkDetailView } from '@/components/links/link-detail';
import type { CursorPaginator, FilterTag, GroupOption, LinkItem, TagOption } from '@/components/links/types';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import * as linksRoute from '@/routes/links';
import type { BreadcrumbItem } from '@/types';

interface Props {
    links: CursorPaginator<LinkItem>;
    searchString: string;
    filteredTags: FilterTag[];
    showUntaggedOnly: boolean;
    showUnreadOnly: boolean;
    showFavoritesOnly: boolean;
    allTags: TagOption[];
    allGroups: GroupOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Links', href: linksRoute.index().url },
];

export default function LinksIndex({
    links: paginator,
    searchString,
    filteredTags,
    showUntaggedOnly,
    showUnreadOnly,
    showFavoritesOnly,
    allTags,
    allGroups,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [selectedLinkId, setSelectedLinkId] = useState<number | null>(null);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    const filters: LinkFilters = {
        searchString,
        filteredTags,
        showFavoritesOnly,
        showUnreadOnly,
        showUntaggedOnly,
    };

    const baseUrl = linksRoute.index().url;

    /**
     * Tag filters are ANDed, so a single name that matches no tag of the
     * user's empties the page on its own. Naming it is the difference between
     * a filter they can undo and a library that looks like it lost their
     * links — the chip alone reads as an ordinary active filter.
     */
    const unknownTags = filteredTags.filter((tag) => tag.id === null);

    const emptyMessage = unknownTags.length > 0
        ? `No links match: ${unknownTags.map((tag) => tag.name).join(', ')} ${unknownTags.length === 1 ? 'is not one of your tags' : 'are not among your tags'}. Drop it to see the rest.`
        : filteredTags.length > 1
            ? `No links carry all ${filteredTags.length} of these tags.`
            : searchString || filteredTags.length > 0 || showFavoritesOnly || showUnreadOnly || showUntaggedOnly
                ? 'No links match the current filters.'
                : 'No links yet. Add your first one!';

    const selectedLink = selectedLinkId === null
        ? null
        : paginator.data.find((link) => link.id === selectedLinkId) ?? null;

    function toggleSelect(link: LinkItem) {
        setSelectedIds((ids) => ids.includes(link.id)
            ? ids.filter((id) => id !== link.id)
            : [...ids, link.id]);
    }

    function handleTagClick(tag: TagOption) {
        if (! filters.filteredTags.some((t) => t.name === tag.name)) {
            navigateWithFilters(baseUrl, {
                ...filters,
                filteredTags: [...filters.filteredTags, tag],
                showUntaggedOnly: false,
            });
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Links" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">Links</h1>
                    <Button onClick={() => setCreateOpen(true)}>Add Link</Button>
                </div>

                <FilterBar baseUrl={baseUrl} filters={filters} allTags={allTags} />

                <BulkBar
                    selectedIds={selectedIds}
                    allTags={allTags}
                    allGroups={allGroups}
                    onClearSelection={() => setSelectedIds([])}
                />

                <div className="flex flex-col gap-2">
                    {paginator.data.length === 0 && (
                        <p className="text-sm text-muted-foreground">{emptyMessage}</p>
                    )}
                    {paginator.data.map((link) => (
                        <LinkCard
                            key={link.id}
                            link={link}
                            onOpen={(l) => setSelectedLinkId(l.id)}
                            onTagClick={handleTagClick}
                            selectable
                            selected={selectedIds.includes(link.id)}
                            onToggleSelect={toggleSelect}
                        />
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

            <Sheet open={selectedLink !== null} onOpenChange={(open) => { if (! open) { setSelectedLinkId(null); } }}>
                <SheetContent side="right" className="flex flex-col sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Link details</SheetTitle>
                    </SheetHeader>
                    {selectedLink && (
                        <LinkDetailView
                            link={selectedLink}
                            allTags={allTags}
                            allGroups={allGroups}
                            variant="sheet"
                            onClose={() => setSelectedLinkId(null)}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
