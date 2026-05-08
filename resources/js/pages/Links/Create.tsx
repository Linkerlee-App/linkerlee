import { Head, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';
import * as linksRoute from '@/routes/links';

interface Tag {
    id: number;
    name: string;
}

interface Group {
    id: number;
    title: string;
}

interface Props {
    tags: Tag[];
    groups: Group[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Links', href: linksRoute.index().url },
    { title: 'Add link', href: linksRoute.create().url },
];

export default function LinksCreate({ tags, groups }: Props) {
    const form = useForm({
        link: '',
        title: '',
        description: '',
        tags: [] as number[],
        newTags: [] as string[],
        groups: [] as number[],
    });

    const [tagInput, setTagInput] = useState('');
    const tagInputRef = useRef<HTMLInputElement>(null);

    function addNewTag(name: string) {
        const trimmed = name.trim();
        if (trimmed.length < 2) { return; }
        if (form.data.newTags.includes(trimmed)) { return; }
        if (tags.some((t) => t.name.toLowerCase() === trimmed.toLowerCase())) { return; }
        form.setData('newTags', [...form.data.newTags, trimmed]);
        setTagInput('');
    }

    function removeNewTag(name: string) {
        form.setData('newTags', form.data.newTags.filter((t) => t !== name));
    }

    function handleTagKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addNewTag(tagInput);
        }
    }

    function toggleTag(id: number) {
        form.setData('tags', form.data.tags.includes(id)
            ? form.data.tags.filter((t) => t !== id)
            : [...form.data.tags, id],
        );
    }

    function toggleGroup(id: number) {
        form.setData('groups', form.data.groups.includes(id)
            ? form.data.groups.filter((g) => g !== id)
            : [...form.data.groups, id],
        );
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(linksRoute.store().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add link" />
            <div className="max-w-xl p-4">
                <h1 className="mb-4 text-xl font-semibold">Add link</h1>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="link">URL *</Label>
                        <Input
                            id="link"
                            type="text"
                            value={form.data.link}
                            onChange={(e) => form.setData('link', e.target.value)}
                            placeholder="https://example.com"
                            autoFocus
                        />
                        <InputError message={form.errors.link} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="title">Title <span className="text-muted-foreground">(optional — fetched automatically if blank)</span></Label>
                        <Input
                            id="title"
                            type="text"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            placeholder="My favourite article"
                        />
                        <InputError message={form.errors.title} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="description">Description <span className="text-muted-foreground">(optional)</span></Label>
                        <textarea
                            id="description"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="What's this link about?"
                            rows={3}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label>Tags</Label>
                        <div className="flex flex-wrap gap-1.5">
                            {tags.map((tag) => (
                                <button
                                    key={tag.id}
                                    type="button"
                                    onClick={() => toggleTag(tag.id)}
                                    className={`rounded-full border px-2.5 py-0.5 text-xs transition-colors ${form.data.tags.includes(tag.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'}`}
                                >
                                    {tag.name}
                                </button>
                            ))}
                            {form.data.newTags.map((name) => (
                                <span
                                    key={name}
                                    className="flex items-center gap-1 rounded-full border border-dashed border-primary px-2.5 py-0.5 text-xs text-primary"
                                >
                                    {name}
                                    <button type="button" onClick={() => removeNewTag(name)} className="leading-none hover:opacity-70">&times;</button>
                                </span>
                            ))}
                        </div>
                        <input
                            ref={tagInputRef}
                            type="text"
                            value={tagInput}
                            onChange={(e) => setTagInput(e.target.value)}
                            onKeyDown={handleTagKeyDown}
                            onBlur={() => addNewTag(tagInput)}
                            placeholder="Add a tag…"
                            className="mt-1 w-full rounded-md border border-border bg-transparent px-3 py-1.5 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        />
                    </div>

                    {groups.length > 0 && (
                        <div className="flex flex-col gap-1.5">
                            <Label>Collections</Label>
                            <div className="flex flex-wrap gap-1.5">
                                {groups.map((group) => (
                                    <button
                                        key={group.id}
                                        type="button"
                                        onClick={() => toggleGroup(group.id)}
                                        className={`rounded-md border px-2.5 py-0.5 text-xs transition-colors ${form.data.groups.includes(group.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-border'}`}
                                    >
                                        {group.title}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save link'}
                        </Button>
                        <Button type="button" variant="outline" onClick={() => history.back()}>
                            Cancel
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
