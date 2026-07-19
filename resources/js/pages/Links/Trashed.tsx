import { Head, router } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
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
import AppLayout from '@/layouts/app-layout';
import * as linksRoute from '@/routes/links';
import type { BreadcrumbItem } from '@/types';

interface TrashedLink {
    id: number;
    title: string;
    link: string;
    deleted_at: string;
}

interface Props {
    links: TrashedLink[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Links', href: linksRoute.index().url },
    { title: 'Trash', href: linksRoute.trashed().url },
];

export default function LinksTrashed({ links }: Props) {
    const [deleteTarget, setDeleteTarget] = useState<TrashedLink | null>(null);
    const [confirmEmpty, setConfirmEmpty] = useState(false);

    function handleRestore(id: number) {
        router.patch(linksRoute.restore(id).url, {}, { preserveScroll: true });
    }

    function handleForceDelete() {
        if (deleteTarget === null) { return; }
        router.delete(linksRoute.forceDestroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    }

    function handleEmptyTrash() {
        router.delete(linksRoute.emptyTrash().url, {
            onSuccess: () => setConfirmEmpty(false),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trash" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">Trash</h1>
                    {links.length > 0 && (
                        <Button variant="destructive" size="sm" onClick={() => setConfirmEmpty(true)}>
                            <Trash2 />
                            Empty trash
                        </Button>
                    )}
                </div>

                {links.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No archived links.</p>
                ) : (
                    <div className="flex flex-col gap-2">
                        {links.map((link) => (
                            <div key={link.id} className="flex items-center justify-between gap-3 rounded-lg border border-border p-3">
                                <div className="flex min-w-0 flex-col">
                                    <span className="truncate font-medium">{link.title || link.link}</span>
                                    <span className="truncate text-sm text-muted-foreground">{link.link}</span>
                                    <span className="text-xs text-muted-foreground">Archived {link.deleted_at}</span>
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    <Button variant="outline" size="sm" onClick={() => handleRestore(link.id)}>
                                        <RotateCcw />
                                        Restore
                                    </Button>
                                    <Button variant="destructive" size="sm" onClick={() => setDeleteTarget(link)}>
                                        <Trash2 />
                                        Delete forever
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={deleteTarget !== null} onOpenChange={(open) => { if (! open) { setDeleteTarget(null); } }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this link forever?</DialogTitle>
                        <DialogDescription>
                            "{deleteTarget?.title || deleteTarget?.link}" will be permanently removed. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteTarget(null)}>Cancel</Button>
                        <Button variant="destructive" onClick={handleForceDelete}>Delete forever</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmEmpty} onOpenChange={setConfirmEmpty}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Empty the trash?</DialogTitle>
                        <DialogDescription>
                            All {links.length} trashed link{links.length > 1 ? 's' : ''} will be permanently removed. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmEmpty(false)}>Cancel</Button>
                        <Button variant="destructive" onClick={handleEmptyTrash}>Empty trash</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
