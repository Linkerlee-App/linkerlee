import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { TagPicker } from '@/components/links/tag-picker';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as groupsRoute from '@/routes/groups';
import type { GroupSummary, GroupWithRules, TagRef } from '@/types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** All of the user's collections, to pick a parent from. */
    groups: GroupSummary[];
    allTags: TagOption[];
    /** The collection being edited; omitted when creating a new one. */
    group?: GroupWithRules;
}

type RuleField = 'andTags' | 'orTags' | 'notTags';

const RULES: { field: RuleField; label: string; hint: string }[] = [
    {
        field: 'andTags',
        label: 'Match all of',
        hint: 'A link is gathered only if it carries every one of these tags.',
    },
    {
        field: 'orTags',
        label: 'Match any of',
        hint: 'A link also has to carry at least one of these tags.',
    },
    {
        field: 'notTags',
        label: 'Exclude',
        hint: 'A link carrying any of these is left out, even if you added it by hand.',
    },
];

const ids = (tags: TagRef[]): number[] => tags.map((tag) => tag.id);

/**
 * The parent picker must not offer the collection itself or anything nested
 * under it — that would make the hierarchy a loop, which the server rejects.
 */
function eligibleParents(
    groups: GroupSummary[],
    group?: GroupWithRules,
): GroupSummary[] {
    if (!group) {
        return groups;
    }

    const excluded = new Set<number>([group.id]);
    let grew = true;

    while (grew) {
        grew = false;
        for (const candidate of groups) {
            if (
                candidate.parentGroupId !== null &&
                excluded.has(candidate.parentGroupId) &&
                !excluded.has(candidate.id)
            ) {
                excluded.add(candidate.id);
                grew = true;
            }
        }
    }

    return groups.filter((candidate) => !excluded.has(candidate.id));
}

export function GroupFormDialog({
    open,
    onOpenChange,
    groups,
    allTags,
    group,
}: Props) {
    const isEditing = group !== undefined;

    const form = useForm({
        title: group?.title ?? '',
        parentGroupId: group?.parentGroupId ?? null,
        andTags: group ? ids(group.andTags) : [],
        orTags: group ? ids(group.orTags) : [],
        notTags: group ? ids(group.notTags) : [],
    });

    const [matchCount, setMatchCount] = useState<number | null>(null);
    const [counting, setCounting] = useState(false);

    const { andTags, orTags, notTags } = form.data;

    /**
     * The count follows the rules as they are picked, so the effect keys off
     * the rule arrays rather than the whole form — retyping the title should
     * not send a request. A rule picked while a request is still out supersedes
     * it, so the stale reply is dropped rather than shown.
     */
    useEffect(() => {
        if (!open) {
            return;
        }

        let superseded = false;

        const timer = setTimeout(async () => {
            setCounting(true);

            try {
                const { data } = await axios.post<{ count: number }>(
                    groupsRoute.matchCount().url,
                    { groupId: group?.id ?? null, andTags, orTags, notTags },
                );
                if (!superseded) {
                    setMatchCount(data.count);
                }
            } catch {
                if (!superseded) {
                    setMatchCount(null);
                }
            } finally {
                if (!superseded) {
                    setCounting(false);
                }
            }
        }, 300);

        return () => {
            superseded = true;
            clearTimeout(timer);
        };
    }, [open, group?.id, andTags, orTags, notTags]);

    function submit(e: React.FormEvent) {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                if (!isEditing) {
                    form.reset();
                }
            },
        };

        if (isEditing) {
            form.put(groupsRoute.update(group.id).url, options);
        } else {
            form.post(groupsRoute.store().url, options);
        }
    }

    function handleOpenChange(next: boolean) {
        onOpenChange(next);

        if (!next) {
            form.reset();
            form.clearErrors();
        }
    }

    function toggleRuleTag(field: RuleField, id: number) {
        form.setData(
            field,
            form.data[field].includes(id)
                ? form.data[field].filter((tagId) => tagId !== id)
                : [...form.data[field], id],
        );
    }

    const parents = eligibleParents(groups, group);

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit collection' : 'New collection'}
                    </DialogTitle>
                    <DialogDescription>
                        A collection holds the links you add to it by hand, plus
                        any link its tag rules match.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="group-title">Title *</Label>
                        <Input
                            id="group-title"
                            type="text"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            placeholder="My collection"
                            autoFocus
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    {parents.length > 0 && (
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="group-parent">
                                Parent collection{' '}
                                <span className="text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <select
                                id="group-parent"
                                value={form.data.parentGroupId ?? ''}
                                onChange={(e) =>
                                    form.setData(
                                        'parentGroupId',
                                        e.target.value === ''
                                            ? null
                                            : Number(e.target.value),
                                    )
                                }
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus:ring-1 focus:ring-ring focus:outline-none"
                            >
                                <option value="">None</option>
                                {parents.map((candidate) => (
                                    <option
                                        key={candidate.id}
                                        value={candidate.id}
                                    >
                                        {candidate.title}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.parentGroupId} />
                        </div>
                    )}

                    <div className="flex flex-col gap-4 rounded-lg border border-border p-3">
                        <p className="text-sm font-medium">Tag rules</p>

                        {allTags.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Tag some links first and you can have this
                                collection gather them automatically.
                            </p>
                        ) : (
                            RULES.map(({ field, label, hint }) => (
                                <div key={field} className="flex flex-col">
                                    <TagPicker
                                        label={label}
                                        allTags={allTags}
                                        selectedIds={form.data[field]}
                                        onToggle={(id) =>
                                            toggleRuleTag(field, id)
                                        }
                                        allowCreate={false}
                                    />
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {hint}
                                    </p>
                                    <InputError
                                        message={form.errors[field]}
                                        className="mt-1"
                                    />
                                </div>
                            ))
                        )}
                    </div>

                    <DialogFooter className="items-center sm:justify-between">
                        <span className="text-sm text-muted-foreground">
                            {counting && 'Counting…'}
                            {!counting &&
                                matchCount !== null &&
                                `${matchCount} link${matchCount === 1 ? '' : 's'} in this collection`}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => handleOpenChange(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Saving…'
                                    : isEditing
                                      ? 'Save'
                                      : 'Create'}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
