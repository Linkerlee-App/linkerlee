import { Head, Link, WhenVisible } from '@inertiajs/react';
import { useState } from 'react';
import { GroupFormDialog } from '@/components/groups/group-form-dialog';
import type { TagOption } from '@/components/links/types';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import * as groupsRoute from '@/routes/groups';
import * as linksRoute from '@/routes/links';
import type {
    BreadcrumbItem,
    GroupSummary,
    GroupWithRules,
    TagRef,
} from '@/types';

interface LinkItem {
    id: number;
    title: string;
    link: string;
}

interface PublicLinkInfo {
    id: number | null;
    link: string | null;
}

interface CursorPaginator<T> {
    data: T[];
    next_cursor: string | null;
    path: string;
}

interface Props {
    group: GroupWithRules;
    links: CursorPaginator<LinkItem>;
    publicLink: PublicLinkInfo;
    searchString: string;
    filteredTags: TagRef[];
    showUntaggedOnly: boolean;
    allTags: TagOption[];
    allGroups: GroupSummary[];
}

const RULE_LABELS: {
    field: 'andTags' | 'orTags' | 'notTags';
    label: string;
}[] = [
    { field: 'andTags', label: 'Match all of' },
    { field: 'orTags', label: 'Match any of' },
    { field: 'notTags', label: 'Exclude' },
];

function RuleChips({ group }: { group: GroupWithRules }) {
    const active = RULE_LABELS.filter(({ field }) => group[field].length > 0);

    if (active.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1.5 rounded-lg border border-border p-3">
            {active.map(({ field, label }) => (
                <div
                    key={field}
                    className="flex flex-wrap items-center gap-1.5"
                >
                    <span className="text-xs text-muted-foreground">
                        {label}
                    </span>
                    {group[field].map((tag) => (
                        <span
                            key={tag.id}
                            className="rounded-full border border-border px-2.5 py-0.5 text-xs"
                        >
                            {tag.name}
                        </span>
                    ))}
                </div>
            ))}
        </div>
    );
}

export default function SingleGroupIndex({
    group,
    links: paginator,
    publicLink,
    allTags,
    allGroups,
}: Props) {
    const [editing, setEditing] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Links', href: linksRoute.index().url },
        { title: group.title, href: groupsRoute.show(group.id).url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={group.title} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">{group.title}</h1>
                    <div className="flex items-center gap-3">
                        {publicLink.link && (
                            <a
                                href={publicLink.link}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-sm text-blue-500 hover:underline"
                            >
                                Public link
                            </a>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => setEditing(true)}
                        >
                            Edit collection
                        </Button>
                    </div>
                </div>

                <RuleChips group={group} />

                <div className="flex flex-col gap-2">
                    {paginator.data.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No links in this collection.
                        </p>
                    )}
                    {paginator.data.map((link) => (
                        <Link
                            key={link.id}
                            href={linksRoute.show(link.id).url}
                            className="flex flex-col rounded-lg border border-border p-3 hover:bg-accent"
                        >
                            <span className="font-medium">
                                {link.title || link.link}
                            </span>
                            <span className="truncate text-sm text-muted-foreground">
                                {link.link}
                            </span>
                        </Link>
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

            {/*
                Mounted only while open so each visit starts from the
                collection as it stands now — `useForm` reads its initial
                values once, and a dialog left mounted across a save would
                keep offering the values the page first loaded with.
            */}
            {editing && (
                <GroupFormDialog
                    open
                    onOpenChange={setEditing}
                    groups={allGroups}
                    allTags={allTags}
                    group={group}
                />
            )}
        </AppLayout>
    );
}
