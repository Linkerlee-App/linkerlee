import { Head } from '@inertiajs/react';
import { LinkDetailView } from '@/components/links/link-detail';
import type {
    GroupOption,
    LinkItem,
    TagOption,
} from '@/components/links/types';
import AppLayout from '@/layouts/app-layout';
import * as linksRoute from '@/routes/links';
import type { BreadcrumbItem } from '@/types';

interface Props {
    link: LinkItem;
    allTags: TagOption[];
    allGroups: GroupOption[];
}

export default function SingleLinkIndex({ link, allTags, allGroups }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Links', href: linksRoute.index().url },
        { title: link.title || link.link, href: linksRoute.show(link.id).url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={link.title || link.link} />
            <div className="max-w-2xl p-4">
                <LinkDetailView
                    link={link}
                    allTags={allTags}
                    allGroups={allGroups}
                    variant="page"
                />
            </div>
        </AppLayout>
    );
}
