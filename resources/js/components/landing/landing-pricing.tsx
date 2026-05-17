import { Link } from '@inertiajs/react';
import { register } from '@/routes';

type Tier = {
    name: string;
    price: string;
    period?: string;
    description: string;
    features: string[];
    cta: string;
    href: string;
    featured?: boolean;
    badge?: string;
};

const tiers: Tier[] = [
    {
        name: 'Free',
        price: '$0',
        period: 'forever',
        description: 'Everything you need for a tidy personal library.',
        features: [
            'Unlimited bookmarks',
            'Unlimited tags & groups',
            '1 public collection',
            'Browser bookmark import',
        ],
        cta: 'Start free',
        href: register().url,
    },
    {
        name: 'Pro',
        price: '$5',
        period: '/ month',
        description: 'For people who live in their bookmarks.',
        features: [
            'Everything in Free',
            'Unlimited public collections',
            'API access & tokens',
            'Bulk import & export',
            'Priority support',
        ],
        cta: 'Coming soon',
        href: register().url,
        featured: true,
        badge: 'Most popular',
    },
    {
        name: 'Team',
        price: '$12',
        period: '/ user / month',
        description: 'Shared knowledge for the whole crew.',
        features: [
            'Everything in Pro',
            'Shared collections',
            'Member roles & permissions',
            'Audit log',
            'SSO (coming soon)',
        ],
        cta: 'Coming soon',
        href: register().url,
    },
];

export function LandingPricing() {
    return (
        <section
            id="pricing"
            className="border-t border-[#1a141010] bg-white py-20 dark:border-white/10 dark:bg-[#0a0a0a]"
        >
            <div className="mx-auto w-full max-w-6xl px-6">
                <div className="mx-auto max-w-2xl text-center">
                    <h2 className="text-balance text-3xl font-semibold tracking-tight text-[#1a1410] sm:text-4xl dark:text-white">
                        Pricing that doesn&apos;t get in the way.
                    </h2>
                    <p className="mt-4 text-[#1a1410]/60 dark:text-white/60">
                        Start free. Upgrade when you want the extras. Cancel any time.
                    </p>
                </div>

                <div className="mt-14 grid gap-6 lg:grid-cols-3">
                    {tiers.map((t) => (
                        <div
                            key={t.name}
                            className={
                                t.featured
                                    ? 'relative rounded-2xl border-2 border-[#fba115] bg-gradient-to-b from-[#fff8ec] to-white p-7 shadow-[0_24px_60px_-20px_rgba(251,161,21,0.4)] dark:from-[#1a1410] dark:to-[#0f0f0f]'
                                    : 'rounded-2xl border border-[#1a141010] bg-white p-7 dark:border-white/10 dark:bg-[#0f0f0f]'
                            }
                        >
                            {t.badge && (
                                <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-[#fba115] px-3 py-1 text-xs font-semibold text-[#1a1410]">
                                    {t.badge}
                                </span>
                            )}
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-[#c97208] dark:text-[#ffc266]">
                                {t.name}
                            </h3>
                            <div className="mt-3 flex items-baseline gap-1">
                                <span className="text-4xl font-semibold tracking-tight text-[#1a1410] dark:text-white">
                                    {t.price}
                                </span>
                                {t.period && (
                                    <span className="text-sm text-[#1a1410]/50 dark:text-white/40">
                                        {t.period}
                                    </span>
                                )}
                            </div>
                            <p className="mt-3 text-sm text-[#1a1410]/60 dark:text-white/60">
                                {t.description}
                            </p>
                            <Link
                                href={t.href}
                                className={
                                    t.featured
                                        ? 'mt-6 inline-flex h-10 w-full items-center justify-center rounded-md bg-[#fba115] text-sm font-semibold text-[#1a1410] transition hover:bg-[#e8890a] hover:text-white'
                                        : 'mt-6 inline-flex h-10 w-full items-center justify-center rounded-md border border-[#1a141020] bg-white text-sm font-medium text-[#1a1410] transition hover:border-[#1a141040] dark:border-white/15 dark:bg-white/5 dark:text-white dark:hover:border-white/30'
                                }
                            >
                                {t.cta}
                            </Link>
                            <ul className="mt-6 space-y-3 text-sm">
                                {t.features.map((f) => (
                                    <li
                                        key={f}
                                        className="flex items-start gap-2 text-[#1a1410]/80 dark:text-white/70"
                                    >
                                        <svg
                                            className="mt-0.5 size-4 shrink-0 text-[#fba115]"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2.5"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <polyline points="20 6 9 17 4 12" />
                                        </svg>
                                        {f}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
