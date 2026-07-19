import { Head, router } from '@inertiajs/react';
import { CreateLinkForm } from '@/components/links/create-link-form';
import type { GroupOption, TagOption } from '@/components/links/types';
import AppLayout from '@/layouts/app-layout';
import * as linksRoute from '@/routes/links';
import type { BreadcrumbItem } from '@/types';

interface Props {
    tags: TagOption[];
    groups: GroupOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Links', href: linksRoute.index().url },
    { title: 'Add link', href: linksRoute.create().url },
];

export default function LinksCreate({ tags, groups }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add link" />
            <div className="max-w-2xl">
                <CreateLinkForm
                    allTags={tags}
                    allGroups={groups}
                    onSuccess={() => router.visit(linksRoute.index().url)}
                />
            </div>
        </AppLayout>
    );
}
