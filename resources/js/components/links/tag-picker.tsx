import { useMemo, useRef, useState } from 'react';
import { Label } from '@/components/ui/label';
import type { TagOption } from './types';

const MAX_VISIBLE_UNSELECTED = 12;

interface TagPickerProps {
    allTags: TagOption[];
    selectedIds: number[];
    newTags: string[];
    onToggle: (id: number) => void;
    onAddNew: (name: string) => void;
    onRemoveNew: (name: string) => void;
    suggestedTags?: TagOption[];
}

export function TagPicker({
    allTags,
    selectedIds,
    newTags,
    onToggle,
    onAddNew,
    onRemoveNew,
    suggestedTags = [],
}: TagPickerProps) {
    const [input, setInput] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    const query = input.trim().toLowerCase();

    const visibleTags = useMemo(() => {
        const selected = allTags.filter((tag) => selectedIds.includes(tag.id));
        const suggestedUnselected = suggestedTags.filter(
            (tag) => !selectedIds.includes(tag.id),
        );
        const rest = allTags.filter(
            (tag) =>
                !selectedIds.includes(tag.id) &&
                !suggestedUnselected.some((s) => s.id === tag.id) &&
                (query === '' || tag.name.toLowerCase().includes(query)),
        );

        return {
            selected,
            suggested:
                query === ''
                    ? suggestedUnselected
                    : suggestedUnselected.filter((tag) =>
                          tag.name.toLowerCase().includes(query),
                      ),
            rest: query === '' ? rest.slice(0, MAX_VISIBLE_UNSELECTED) : rest,
            hiddenCount:
                query === ''
                    ? Math.max(0, rest.length - MAX_VISIBLE_UNSELECTED)
                    : 0,
        };
    }, [allTags, selectedIds, suggestedTags, query]);

    /**
     * Selecting a tag consumes the query it was found with, so the query goes.
     * Deselecting one does not: the selected chips are listed whatever is
     * typed, so that click is a removal and has nothing to do with the text
     * the user is still in the middle of.
     */
    function pickTag(id: number) {
        const isRemoval = selectedIds.includes(id);

        onToggle(id);

        if (!isRemoval) {
            setInput('');
        }
    }

    /**
     * A half-typed query only becomes a tag once focus leaves the picker for
     * good. Moving inside it — clicking a chip, tabbing to one — is the user
     * still choosing, and committing there turned the query into a tag of its
     * own instead of selecting the tag they were reaching for.
     */
    function handleBlur(e: React.FocusEvent<HTMLDivElement>) {
        if (containerRef.current?.contains(e.relatedTarget)) {
            return;
        }

        addNew(input);
    }

    function addNew(name: string) {
        const trimmed = name.trim();
        if (trimmed.length < 2) {
            return;
        }
        if (
            newTags.some((name) => name.toLowerCase() === trimmed.toLowerCase())
        ) {
            setInput('');

            return;
        }
        const existing = allTags.find(
            (tag) => tag.name.toLowerCase() === trimmed.toLowerCase(),
        );
        if (existing) {
            if (!selectedIds.includes(existing.id)) {
                onToggle(existing.id);
            }
            setInput('');
            return;
        }
        onAddNew(trimmed);
        setInput('');
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addNew(input);
        }
    }

    const chipClass = (active: boolean) =>
        `rounded-full border px-2.5 py-0.5 text-xs transition-colors ${active ? 'border-primary bg-primary text-primary-foreground' : 'border-border'}`;

    return (
        <div
            ref={containerRef}
            onBlur={handleBlur}
            className="flex flex-col gap-1.5"
        >
            <Label>Tags</Label>
            <div className="flex flex-wrap gap-1.5">
                {visibleTags.selected.map((tag) => (
                    <button
                        key={tag.id}
                        type="button"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => pickTag(tag.id)}
                        className={chipClass(true)}
                    >
                        {tag.name}
                    </button>
                ))}
                {newTags.map((name) => (
                    <span
                        key={name}
                        className="flex items-center gap-1 rounded-full border border-dashed border-primary px-2.5 py-0.5 text-xs text-primary"
                    >
                        {name}
                        <button
                            type="button"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => onRemoveNew(name)}
                            className="leading-none hover:opacity-70"
                        >
                            &times;
                        </button>
                    </span>
                ))}
                {visibleTags.suggested.map((tag) => (
                    <button
                        key={tag.id}
                        type="button"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => pickTag(tag.id)}
                        className="rounded-full border border-dashed border-primary/60 px-2.5 py-0.5 text-xs text-primary transition-colors hover:bg-primary hover:text-primary-foreground"
                        title="Suggested from the page content"
                    >
                        + {tag.name}
                    </button>
                ))}
                {visibleTags.rest.map((tag) => (
                    <button
                        key={tag.id}
                        type="button"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => pickTag(tag.id)}
                        className={chipClass(false)}
                    >
                        {tag.name}
                    </button>
                ))}
                {visibleTags.hiddenCount > 0 && (
                    <span className="px-1 py-0.5 text-xs text-muted-foreground">
                        +{visibleTags.hiddenCount} more — type to filter
                    </span>
                )}
            </div>
            <input
                type="text"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder="Search or add a tag…"
                className="mt-1 w-full rounded-md border border-border bg-transparent px-3 py-1.5 text-sm placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none"
            />
        </div>
    );
}
