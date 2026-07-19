import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { suggestTags } from '@/routes';
import * as linksRoute from '@/routes/links';
import { TagPicker } from './tag-picker';
import type { GroupOption, TagOption } from './types';

interface CreateLinkFormProps {
    allTags: TagOption[];
    allGroups: GroupOption[];
    onSuccess: () => void;
}

export function CreateLinkForm({ allTags, allGroups, onSuccess }: CreateLinkFormProps) {
    const form = useForm({
        link: '',
        title: '',
        description: '',
        tags: [] as number[],
        newTags: [] as string[],
        groups: [] as number[],
    });

    const [suggestedTags, setSuggestedTags] = useState<TagOption[]>([]);
    const lastSuggestedFor = useRef<string>('');

    function toggleTag(id: number) {
        form.setData('tags', form.data.tags.includes(id)
            ? form.data.tags.filter((t) => t !== id)
            : [...form.data.tags, id]);
    }

    function toggleGroup(id: number) {
        form.setData('groups', form.data.groups.includes(id)
            ? form.data.groups.filter((g) => g !== id)
            : [...form.data.groups, id]);
    }

    async function fetchSuggestions() {
        const url = form.data.link.trim();

        if (url === '' || url === lastSuggestedFor.current) {
            return;
        }

        const normalized = /^https?:\/\//i.test(url) ? url : `https://${url}`;
        lastSuggestedFor.current = url;

        try {
            const { data } = await axios.post<TagOption[]>(suggestTags().url, { link: normalized });
            setSuggestedTags(data);
        } catch {
            setSuggestedTags([]);
        }
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(linksRoute.store().url, { onSuccess });
    }

    return (
        <form onSubmit={submit} className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-link">URL *</Label>
                <Input
                    id="create-link"
                    type="text"
                    value={form.data.link}
                    onChange={(e) => form.setData('link', e.target.value)}
                    onBlur={fetchSuggestions}
                    placeholder="https://example.com"
                    autoFocus
                />
                <InputError message={form.errors.link} />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-title">
                    Title <span className="text-muted-foreground text-xs">(optional, fetched automatically)</span>
                </Label>
                <Input
                    id="create-title"
                    type="text"
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    placeholder="My favourite article"
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-description">
                    Description <span className="text-muted-foreground text-xs">(optional, fetched automatically)</span>
                </Label>
                <textarea
                    id="create-description"
                    value={form.data.description}
                    onChange={(e) => form.setData('description', e.target.value)}
                    placeholder="What's this link about?"
                    rows={3}
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                />
                <InputError message={form.errors.description} />
            </div>

            <TagPicker
                allTags={allTags}
                selectedIds={form.data.tags}
                newTags={form.data.newTags}
                onToggle={toggleTag}
                onAddNew={(name) => form.setData('newTags', [...form.data.newTags, name])}
                onRemoveNew={(name) => form.setData('newTags', form.data.newTags.filter((t) => t !== name))}
                suggestedTags={suggestedTags}
            />

            {allGroups.length > 0 && (
                <div className="flex flex-col gap-1.5">
                    <Label>Collections</Label>
                    <div className="flex flex-wrap gap-1.5">
                        {allGroups.map((group) => (
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

            <div className="mt-auto flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save link'}
                </Button>
            </div>
        </form>
    );
}
