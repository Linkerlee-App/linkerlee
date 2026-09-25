import { AlertTriangle, Ban, Clock, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { ExtractionStatus } from './types';

interface ExtractionStatusBadgeProps {
    status: ExtractionStatus | null;
    /**
     * Shown as a tooltip on hover. Only ever set on the detail view, where
     * there's room for it.
     */
    error?: string | null;
}

const EXTRACTION_STATUS_LABELS: Record<ExtractionStatus, string> = {
    pending: 'Extraction pending',
    ok: 'Extracted',
    failed: 'Extraction failed',
    blocked: 'Extraction blocked',
    unsupported: 'Content unsupported',
};

/**
 * Flags a link whose content extraction needs attention. Renders nothing for
 * `ok` and null: a working link has nothing to flag. Callers on the link
 * card grid should also skip `pending`, so a link freshly saved (or just
 * retried) doesn't flicker a badge before the worker gets to it; the detail
 * view is where `pending` belongs.
 */
export function ExtractionStatusBadge({
    status,
    error,
}: ExtractionStatusBadgeProps) {
    if (status === null || status === 'ok') {
        return null;
    }

    const label = EXTRACTION_STATUS_LABELS[status];
    const title = error ?? label;

    if (status === 'pending') {
        return (
            <Badge variant="secondary" className="gap-1 font-normal">
                <Clock className="size-3" aria-hidden="true" />
                {label}
            </Badge>
        );
    }

    if (status === 'blocked') {
        return (
            <Badge
                variant="outline"
                className="gap-1 border-amber-300 bg-amber-50 font-normal text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
                title={title}
            >
                <Ban className="size-3" aria-hidden="true" />
                {label}
            </Badge>
        );
    }

    if (status === 'unsupported') {
        return (
            <Badge
                variant="secondary"
                className="gap-1 font-normal"
                title={title}
            >
                <AlertTriangle className="size-3" aria-hidden="true" />
                {label}
            </Badge>
        );
    }

    return (
        <Badge
            variant="destructive"
            className="gap-1 font-normal"
            title={title}
        >
            <XCircle className="size-3" aria-hidden="true" />
            {label}
        </Badge>
    );
}
