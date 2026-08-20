import { router } from '@inertiajs/react';
import axios from 'axios';
import {
    FolderMinus,
    FolderPlus,
    Tag as TagIcon,
    TagsIcon,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
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
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { bulkEditLinks } from '@/routes';
import type { GroupOption, TagOption } from './types';

type BulkAction =
    | 'add_tags'
    | 'remove_tags'
    | 'add_to_groups'
    | 'remove_from_groups'
    | 'delete';

interface BulkBarProps {
    selectedIds: number[];
    allTags: TagOption[];
    allGroups: GroupOption[];
    onClearSelection: () => void;
    reloadProps?: string[];
}

export function BulkBar({
    selectedIds,
    allTags,
    allGroups,
    onClearSelection,
    reloadProps = ['links'],
}: BulkBarProps) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [busy, setBusy] = useState(false);

    if (selectedIds.length === 0) {
        return null;
    }

    async function run(
        action: BulkAction,
        tagIds: number[] = [],
        groupIds: number[] = [],
    ) {
        setBusy(true);
        try {
            await axios.post(bulkEditLinks().url, {
                action,
                links: selectedIds,
                tags: tagIds,
                groups: groupIds,
            });
            if (action === 'delete') {
                onClearSelection();
                setConfirmDelete(false);
            }
            router.reload({ only: reloadProps });
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="sticky top-2 z-10 flex flex-wrap items-center gap-2 rounded-lg border border-border bg-background/95 p-2 shadow-sm backdrop-blur">
            <span className="px-1 text-sm font-medium">
                {selectedIds.length} selected
            </span>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={busy || allTags.length === 0}
                    >
                        <TagIcon />
                        Add tag
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="max-h-72 overflow-y-auto">
                    <DropdownMenuLabel>Add tag to selection</DropdownMenuLabel>
                    {allTags.map((tag) => (
                        <DropdownMenuItem
                            key={tag.id}
                            onSelect={() => run('add_tags', [tag.id])}
                        >
                            {tag.name}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={busy || allTags.length === 0}
                    >
                        <TagsIcon />
                        Remove tag
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="max-h-72 overflow-y-auto">
                    <DropdownMenuLabel>
                        Remove tag from selection
                    </DropdownMenuLabel>
                    {allTags.map((tag) => (
                        <DropdownMenuItem
                            key={tag.id}
                            onSelect={() => run('remove_tags', [tag.id])}
                        >
                            {tag.name}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>

            {allGroups.length > 0 && (
                <>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm" disabled={busy}>
                                <FolderPlus />
                                Add to collection
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="max-h-72 overflow-y-auto">
                            <DropdownMenuLabel>
                                Add selection to collection
                            </DropdownMenuLabel>
                            {allGroups.map((group) => (
                                <DropdownMenuItem
                                    key={group.id}
                                    onSelect={() =>
                                        run('add_to_groups', [], [group.id])
                                    }
                                >
                                    {group.title}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm" disabled={busy}>
                                <FolderMinus />
                                Remove from collection
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="max-h-72 overflow-y-auto">
                            <DropdownMenuLabel>
                                Remove selection from collection
                            </DropdownMenuLabel>
                            {allGroups.map((group) => (
                                <DropdownMenuItem
                                    key={group.id}
                                    onSelect={() =>
                                        run(
                                            'remove_from_groups',
                                            [],
                                            [group.id],
                                        )
                                    }
                                >
                                    {group.title}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </>
            )}

            <Button
                variant="destructive"
                size="sm"
                className="ml-auto"
                disabled={busy}
                onClick={() => setConfirmDelete(true)}
            >
                <Trash2 />
                Move to trash
            </Button>
            <Button
                variant="ghost"
                size="sm"
                onClick={onClearSelection}
                disabled={busy}
            >
                <X />
                Clear
            </Button>

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Move {selectedIds.length} link
                            {selectedIds.length > 1 ? 's' : ''} to trash?
                        </DialogTitle>
                        <DialogDescription>
                            The selected links will be archived. You can restore
                            them from the trash view.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDelete(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => run('delete')}
                            disabled={busy}
                        >
                            Move to trash
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
