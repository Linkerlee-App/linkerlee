import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { GroupFormDialog } from '@/components/groups/group-form-dialog';
import type { TagOption } from '@/components/links/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import * as groupsRoute from '@/routes/groups';
import type { BreadcrumbItem, GroupListItem, TagRef } from '@/types';

interface Props {
    groups: GroupListItem[];
    allTags: TagOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Collections', href: groupsRoute.index().url },
];

/** The collections nested directly under `parentId`, in listing order. */
function childrenOf(
    groups: GroupListItem[],
    parentId: number | null,
): GroupListItem[] {
    return groups.filter((group) => group.parentGroupId === parentId);
}

function RuleSummary({ group }: { group: GroupListItem }) {
    const parts: string[] = [];

    const names = (tags: TagRef[]) => tags.map((tag) => tag.name).join(', ');

    if (group.andTags.length > 0) {
        parts.push(`all of ${names(group.andTags)}`);
    }
    if (group.orTags.length > 0) {
        parts.push(`any of ${names(group.orTags)}`);
    }
    if (group.notTags.length > 0) {
        parts.push(`not ${names(group.notTags)}`);
    }

    if (parts.length === 0) {
        return null;
    }

    return (
        <span className="truncate text-xs text-muted-foreground">
            {parts.join(' · ')}
        </span>
    );
}

export default function GroupsIndex({ groups, allTags }: Props) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<GroupListItem | null>(null);
    const [deleting, setDeleting] = useState<GroupListItem | null>(null);

    function confirmDelete() {
        if (deleting === null) {
            return;
        }

        router.delete(groupsRoute.destroy(deleting.id).url, {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    }

    function renderRows(parentId: number | null, depth: number) {
        return childrenOf(groups, parentId).map((group) => (
            <div key={group.id} className="flex flex-col gap-2">
                <div
                    className="flex items-center gap-2 rounded-lg border border-border p-3 hover:bg-accent"
                    style={{ marginLeft: depth * 20 }}
                >
                    <Link
                        href={groupsRoute.show(group.id).url}
                        className="flex min-w-0 flex-1 flex-col"
                    >
                        <span className="font-medium">{group.title}</span>
                        <RuleSummary group={group} />
                    </Link>
                    <span className="shrink-0 text-sm text-muted-foreground">
                        {group.linksCount} link
                        {group.linksCount !== 1 ? 's' : ''}
                        {group.childGroupsCount > 0 &&
                            ` · ${group.childGroupsCount} sub-collection${group.childGroupsCount !== 1 ? 's' : ''}`}
                    </span>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Actions for ${group.title}`}
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onSelect={() => setEditing(group)}
                            >
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setDeleting(group)}
                            >
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                {renderRows(group.id, depth + 1)}
            </div>
        ));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Collections" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Collections</h1>
                    <Button onClick={() => setCreating(true)}>
                        New collection
                    </Button>
                </div>

                {groups.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No collections yet.
                    </p>
                ) : (
                    <div className="flex flex-col gap-2">
                        {renderRows(null, 0)}
                    </div>
                )}
            </div>

            <GroupFormDialog
                open={creating}
                onOpenChange={setCreating}
                groups={groups}
                allTags={allTags}
            />

            {/*
                Keyed on the collection so the form resets to that collection's
                own rules — `useForm` reads its initial values only once.
            */}
            {editing !== null && (
                <GroupFormDialog
                    key={editing.id}
                    open
                    onOpenChange={(next) => !next && setEditing(null)}
                    groups={groups}
                    allTags={allTags}
                    group={editing}
                />
            )}

            <Dialog
                open={deleting !== null}
                onOpenChange={(next) => !next && setDeleting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete “{deleting?.title}”?</DialogTitle>
                        <DialogDescription>
                            The collection and its tag rules are removed. The
                            links it held are not deleted, and any collection
                            nested inside it moves to the top level.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
