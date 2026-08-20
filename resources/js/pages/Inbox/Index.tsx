import { Head, WhenVisible, router } from '@inertiajs/react';
import { useState } from 'react';
import { LinkCard } from '@/components/links/link-card';
import { LinkDetailView } from '@/components/links/link-detail';
import type {
    CursorPaginator,
    GroupOption,
    LinkItem,
    TagOption,
} from '@/components/links/types';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import * as inboxRoute from '@/routes/inbox';
import type { BreadcrumbItem } from '@/types';

interface Props {
    links: CursorPaginator<LinkItem>;
    searchString: string;
    untagged: boolean;
    ungrouped: boolean;
    allTags: TagOption[];
    allGroups: GroupOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inbox', href: inboxRoute.index().url },
];

export default function InboxIndex({
    links: paginator,
    untagged,
    ungrouped,
    allTags,
    allGroups,
}: Props) {
    const [selectedLinkId, setSelectedLinkId] = useState<number | null>(null);

    const selectedLink =
        selectedLinkId === null
            ? null
            : (paginator.data.find((link) => link.id === selectedLinkId) ??
              null);

    function applyToggles(nextUntagged: boolean, nextUngrouped: boolean) {
        router.get(
            inboxRoute.index().url,
            {
                untagged: nextUntagged ? 1 : 0,
                ungrouped: nextUngrouped ? 1 : 0,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inbox" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-xl font-semibold">Inbox</h1>
                        <p className="text-sm text-muted-foreground">
                            Links that still need tags or a collection. Open one
                            to triage it.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant={untagged ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => applyToggles(!untagged, ungrouped)}
                            aria-pressed={untagged}
                        >
                            Untagged
                        </Button>
                        <Button
                            variant={ungrouped ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => applyToggles(untagged, !ungrouped)}
                            aria-pressed={ungrouped}
                        >
                            Not in a collection
                        </Button>
                    </div>
                </div>

                <div className="flex flex-col gap-2">
                    {paginator.data.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Inbox is empty. Nice work!
                        </p>
                    )}
                    {paginator.data.map((link) => (
                        <LinkCard
                            key={link.id}
                            link={link}
                            onOpen={(l) => setSelectedLinkId(l.id)}
                            onArchived={() =>
                                router.reload({ only: ['links'] })
                            }
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
                            <span className="text-sm text-muted-foreground">
                                Loading more...
                            </span>
                        </div>
                    </WhenVisible>
                )}
            </div>

            <Sheet
                open={selectedLink !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedLinkId(null);
                    }
                }}
            >
                <SheetContent
                    side="right"
                    className="flex flex-col sm:max-w-lg"
                >
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
