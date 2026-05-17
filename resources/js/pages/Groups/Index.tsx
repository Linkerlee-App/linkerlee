import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import * as groupsRoute from '@/routes/groups';
import type { BreadcrumbItem } from '@/types';

interface GroupItem {
    id: number;
    title: string;
    parentGroupId: number | null;
    linksCount: number;
    childGroupsCount: number;
}

interface Props {
    groups: GroupItem[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Collections', href: groupsRoute.index().url },
];

export default function GroupsIndex({ groups }: Props) {
    const rootGroups = groups.filter((g) => g.parentGroupId === null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Collections" />
            <div className="flex flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Collections</h1>

                {groups.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No collections yet.</p>
                ) : (
                    <div className="flex flex-col gap-2">
                        {rootGroups.map((group) => (
                            <Link
                                key={group.id}
                                href={groupsRoute.show(group.id).url}
                                className="flex items-center justify-between rounded-lg border border-border p-3 hover:bg-accent"
                            >
                                <span className="font-medium">{group.title}</span>
                                <span className="text-sm text-muted-foreground">
                                    {group.linksCount} link{group.linksCount !== 1 ? 's' : ''}
                                    {group.childGroupsCount > 0 && ` · ${group.childGroupsCount} sub-collection${group.childGroupsCount !== 1 ? 's' : ''}`}
                                </span>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
