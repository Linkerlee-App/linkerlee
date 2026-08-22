import { Badge } from '@/components/ui/badge';
import {
    LINK_SOURCE_ICONS,
    LINK_SOURCE_LABELS,
    type LinkSource,
} from './types';

interface LinkSourceBadgeProps {
    source: LinkSource | null;
    /**
     * `icon` drops the text, for the link cards where every row already
     * competes for the same line.
     */
    variant?: 'full' | 'icon';
}

/**
 * Renders nothing for a link with no source. Every link saved before the
 * column existed is in that state, so "Unknown" would be the most common
 * label on the page while saying nothing the user can act on.
 */
export function LinkSourceBadge({
    source,
    variant = 'full',
}: LinkSourceBadgeProps) {
    if (source === null) {
        return null;
    }

    const Icon = LINK_SOURCE_ICONS[source];
    const label = LINK_SOURCE_LABELS[source];

    if (variant === 'icon') {
        return (
            <span
                className="hidden shrink-0 text-muted-foreground sm:inline"
                title={`Saved from: ${label}`}
            >
                <Icon className="size-3.5" aria-hidden="true" />
                <span className="sr-only">Saved from {label}</span>
            </span>
        );
    }

    return (
        <Badge variant="outline" className="gap-1 font-normal">
            <Icon className="size-3" aria-hidden="true" />
            {label}
        </Badge>
    );
}
