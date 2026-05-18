import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    const [open, setOpen] = useState(false);

    const form = useForm({
        title: '',
        parentGroupId: null as number | null,
        orTags: [] as number[],
        andTags: [] as number[],
        notTags: [] as number[],
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(groupsRoute.store().url, {
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    }

    function handleOpenChange(next: boolean) {
        setOpen(next);
        if (!next) {
            form.reset();
            form.clearErrors();
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Collections" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Collections</h1>

                    <Dialog open={open} onOpenChange={handleOpenChange}>
                        <DialogTrigger asChild>
                            <Button>New collection</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>New collection</DialogTitle>
                            </DialogHeader>

                            <form onSubmit={submit} className="flex flex-col gap-4">
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="title">Title *</Label>
                                    <Input
                                        id="title"
                                        type="text"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                        placeholder="My collection"
                                        autoFocus
                                    />
                                    <InputError message={form.errors.title} />
                                </div>

                                {groups.length > 0 && (
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="parentGroupId">Parent collection <span className="text-muted-foreground">(optional)</span></Label>
                                        <select
                                            id="parentGroupId"
                                            value={form.data.parentGroupId ?? ''}
                                            onChange={(e) => form.setData('parentGroupId', e.target.value === '' ? null : Number(e.target.value))}
                                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                        >
                                            <option value="">None</option>
                                            {groups.map((g) => (
                                                <option key={g.id} value={g.id}>{g.title}</option>
                                            ))}
                                        </select>
                                        <InputError message={form.errors.parentGroupId} />
                                    </div>
                                )}

                                <DialogFooter>
                                    <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={form.processing}>
                                        {form.processing ? 'Saving…' : 'Create'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

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
