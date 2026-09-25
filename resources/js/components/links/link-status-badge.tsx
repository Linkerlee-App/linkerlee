import {
    AlertTriangle,
    Ban,
    Clock,
    MoveRight,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ExtractionStatus, HealthStatus } from './types';

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

interface StatusBadgeStyle {
    label: string;
    icon: LucideIcon;
    variant: BadgeVariant;
    className?: string;
}

const AMBER_CLASSES =
    'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400';

/**
 * Extraction outcomes that need a badge, keyed as an explicit lookup so a
 * status added to the enum without a style here is a type error rather than
 * a silently blank badge. `ok` renders nothing and is excluded on purpose.
 */
const EXTRACTION_BADGE_STYLES: Record<
    Exclude<ExtractionStatus, 'ok'>,
    StatusBadgeStyle
> = {
    pending: { label: 'Extraction pending', icon: Clock, variant: 'secondary' },
    failed: {
        label: 'Extraction failed',
        icon: XCircle,
        variant: 'destructive',
    },
    blocked: {
        label: 'Extraction blocked',
        icon: Ban,
        variant: 'outline',
        className: AMBER_CLASSES,
    },
    unsupported: {
        label: 'Content unsupported',
        icon: AlertTriangle,
        variant: 'secondary',
    },
};

/**
 * Health-check outcomes that need a badge, same reasoning as above. `ok`
 * renders nothing.
 */
const HEALTH_BADGE_STYLES: Record<
    Exclude<HealthStatus, 'ok'>,
    StatusBadgeStyle
> = {
    redirected: { label: 'Moved', icon: MoveRight, variant: 'secondary' },
    suspect: {
        label: 'May be gone',
        icon: AlertTriangle,
        variant: 'outline',
        className: AMBER_CLASSES,
    },
    gone: { label: 'Gone', icon: XCircle, variant: 'destructive' },
    error: {
        label: 'Unreachable',
        icon: XCircle,
        variant: 'outline',
        className:
            'border-destructive/40 text-destructive dark:text-destructive',
    },
};

function StatusBadge({
    style,
    title,
}: {
    style: StatusBadgeStyle;
    title?: string | null;
}) {
    const Icon = style.icon;

    return (
        <Badge
            variant={style.variant}
            className={cn('gap-1 font-normal', style.className)}
            title={title ?? style.label}
        >
            <Icon className="size-3" aria-hidden="true" />
            {style.label}
        </Badge>
    );
}

interface LinkStatusBadgeProps {
    extractionStatus: ExtractionStatus | null;
    healthStatus: HealthStatus | null;
    /**
     * Shown as a tooltip on the extraction badge. Only ever set on the detail
     * view, where there's room for it.
     */
    extractionError?: string | null;
    /**
     * The page's current location, shown next to a `redirected` health badge.
     * Only rendered in `mode: 'detail'`.
     */
    redirectUrl?: string | null;
    /**
     * `card`: at most one badge, picked by precedence — a dead/erroring link
     * always wins over an extraction problem, which wins over a merely
     * suspect/redirected one. `pending` extraction never shows on cards.
     *
     * `detail` (default): shows the extraction badge (including `pending`)
     * and the health badge together, since both can be relevant there.
     */
    mode?: 'card' | 'detail';
}

/**
 * Flags a link that needs attention: its content extraction failed, or its
 * most recent health check found the page moved, suspect or gone. Renders
 * nothing when both statuses are `ok` or null.
 */
export function LinkStatusBadge({
    extractionStatus,
    healthStatus,
    extractionError,
    redirectUrl,
    mode = 'detail',
}: LinkStatusBadgeProps) {
    const extractionBadge =
        extractionStatus !== null && extractionStatus !== 'ok' ? (
            <StatusBadge
                key="extraction"
                style={EXTRACTION_BADGE_STYLES[extractionStatus]}
                title={extractionError}
            />
        ) : null;

    const healthBadge =
        healthStatus !== null && healthStatus !== 'ok' ? (
            <StatusBadge
                key="health"
                style={HEALTH_BADGE_STYLES[healthStatus]}
            />
        ) : null;

    if (mode === 'card') {
        if (healthStatus === 'gone' || healthStatus === 'error') {
            return healthBadge;
        }
        if (
            extractionStatus === 'failed' ||
            extractionStatus === 'blocked' ||
            extractionStatus === 'unsupported'
        ) {
            return extractionBadge;
        }
        if (healthStatus === 'suspect' || healthStatus === 'redirected') {
            return healthBadge;
        }
        return null;
    }

    if (!extractionBadge && !healthBadge) {
        return null;
    }

    return (
        <>
            {extractionBadge}
            {healthBadge}
            {healthStatus === 'redirected' && redirectUrl && (
                <a
                    key="redirect-url"
                    href={redirectUrl}
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    className="max-w-48 truncate text-xs text-muted-foreground hover:text-primary hover:underline"
                    title={redirectUrl}
                >
                    {redirectUrl}
                </a>
            )}
        </>
    );
}
