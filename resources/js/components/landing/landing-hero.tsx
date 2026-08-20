import { Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import type { User } from '@/types';

type Props = {
    user: User | null;
    canRegister?: boolean;
};

const mockLinks = [
    {
        favicon: 'A',
        title: 'Designing for serendipity',
        domain: 'aworkinglibrary.com',
        snippet: 'Notes on building tools that make space for the unexpected.',
        tags: ['design', 'essays'],
        rating: 5,
    },
    {
        favicon: 'L',
        title: 'Laravel 12 release notes',
        domain: 'laravel.com',
        snippet: 'Streamlined application structure, improved DX, new helpers.',
        tags: ['laravel', 'dev'],
        rating: 4,
    },
    {
        favicon: 'V',
        title: 'Vite 6 — first look',
        domain: 'vite.dev',
        snippet: "Environment API, faster cold start, what's new for plugins.",
        tags: ['frontend', 'tooling'],
        rating: 4,
    },
    {
        favicon: 'N',
        title: 'The case for digital gardens',
        domain: 'nesslabs.com',
        snippet: 'On thinking in public and the long-term compound of notes.',
        tags: ['writing', 'thinking'],
        rating: 5,
    },
];

export function LandingHero({ user, canRegister = true }: Props) {
    return (
        <section className="relative overflow-hidden">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[640px] bg-[radial-gradient(60%_60%_at_50%_0%,#ffd494_0%,transparent_70%)] dark:bg-[radial-gradient(60%_60%_at_50%_0%,#3a2208_0%,transparent_70%)]"
            />
            <div className="mx-auto w-full max-w-6xl px-6 pt-16 pb-12 lg:pt-24 lg:pb-20">
                <div className="mx-auto max-w-3xl text-center">
                    <span className="inline-flex items-center gap-2 rounded-full border border-[#fba115]/30 bg-white/60 px-3 py-1 text-xs font-medium text-[#c97208] backdrop-blur dark:border-[#fba115]/40 dark:bg-white/5 dark:text-[#ffc266]">
                        <span className="inline-block size-1.5 rounded-full bg-[#fba115]" />
                        Built for people with too many open tabs
                    </span>
                    <h1 className="mt-6 text-5xl leading-[1.05] font-semibold tracking-tight text-balance text-[#1a1410] sm:text-6xl lg:text-7xl dark:text-white">
                        Your bookmarks,
                        <br />
                        <span className="bg-gradient-to-br from-[#fba115] via-[#ffb347] to-[#e8890a] bg-clip-text text-transparent">
                            finally organized.
                        </span>
                    </h1>
                    <p className="mx-auto mt-6 max-w-xl text-lg text-pretty text-[#1a1410]/70 dark:text-white/70">
                        Save any link, tag it, drop it in a smart group, share
                        it. Linkerlee turns the chaos of saved tabs into a
                        library you&apos;ll actually come back to.
                    </p>
                    <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        {user ? (
                            <Link
                                href="/links"
                                className="inline-flex h-11 items-center justify-center rounded-md bg-[#fba115] px-6 text-sm font-semibold text-[#1a1410] shadow-[0_8px_24px_-8px_rgba(251,161,21,0.6)] transition hover:bg-[#e8890a] hover:text-white"
                            >
                                Open your library →
                            </Link>
                        ) : (
                            <>
                                {canRegister && (
                                    <Link
                                        href={register()}
                                        className="inline-flex h-11 items-center justify-center rounded-md bg-[#fba115] px-6 text-sm font-semibold text-[#1a1410] shadow-[0_8px_24px_-8px_rgba(251,161,21,0.6)] transition hover:bg-[#e8890a] hover:text-white"
                                    >
                                        Get started — it&apos;s free
                                    </Link>
                                )}
                                <Link
                                    href={login()}
                                    className="inline-flex h-11 items-center justify-center rounded-md border border-[#1a141020] bg-white px-6 text-sm font-medium text-[#1a1410] hover:border-[#1a141040] dark:border-white/15 dark:bg-white/5 dark:text-white dark:hover:border-white/30"
                                >
                                    Log in
                                </Link>
                            </>
                        )}
                    </div>
                    <p className="mt-4 text-xs text-[#1a1410]/50 dark:text-white/40">
                        Free forever for personal use. No credit card required.
                    </p>
                </div>

                {/* Browser-chrome mock */}
                <div className="mx-auto mt-16 max-w-5xl">
                    <div className="rounded-2xl border border-[#1a141015] bg-white/70 p-2 shadow-[0_24px_60px_-20px_rgba(26,20,16,0.25)] backdrop-blur dark:border-white/10 dark:bg-white/5 dark:shadow-[0_24px_60px_-20px_rgba(0,0,0,0.6)]">
                        <div className="rounded-xl border border-[#1a141010] bg-white dark:border-white/10 dark:bg-[#0f0f0f]">
                            {/* chrome bar */}
                            <div className="flex items-center gap-2 border-b border-[#1a141010] px-4 py-3 dark:border-white/10">
                                <span className="size-3 rounded-full bg-[#ff5f57]" />
                                <span className="size-3 rounded-full bg-[#ffbd2e]" />
                                <span className="size-3 rounded-full bg-[#28c840]" />
                                <div className="ml-4 flex h-7 flex-1 items-center rounded-md bg-[#1a14100a] px-3 text-xs text-[#1a1410]/50 dark:bg-white/5 dark:text-white/40">
                                    linkerlee.com/links
                                </div>
                            </div>
                            {/* content */}
                            <div className="p-6">
                                <div className="mb-5 flex items-center justify-between">
                                    <div>
                                        <h3 className="text-sm font-semibold text-[#1a1410] dark:text-white">
                                            All links
                                        </h3>
                                        <p className="text-xs text-[#1a1410]/50 dark:text-white/40">
                                            247 saved · 12 collections
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <div className="hidden h-8 w-44 items-center rounded-md border border-[#1a141015] px-3 text-xs text-[#1a1410]/40 sm:flex dark:border-white/10 dark:text-white/40">
                                            Search…
                                        </div>
                                        <div className="inline-flex h-8 items-center rounded-md bg-[#fba115] px-3 text-xs font-medium text-[#1a1410]">
                                            + Add link
                                        </div>
                                    </div>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {mockLinks.map((l) => (
                                        <div
                                            key={l.title}
                                            className="rounded-lg border border-[#1a141010] bg-white p-4 transition hover:border-[#fba115]/40 hover:shadow-sm dark:border-white/10 dark:bg-[#161615]"
                                        >
                                            <div className="flex items-start gap-3">
                                                <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-[#fff8ec] text-sm font-semibold text-[#c97208] dark:bg-[#fba115]/15 dark:text-[#ffc266]">
                                                    {l.favicon}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <h4 className="truncate text-sm font-medium text-[#1a1410] dark:text-white">
                                                            {l.title}
                                                        </h4>
                                                        <span className="shrink-0 text-xs text-[#fba115]">
                                                            {'★'.repeat(
                                                                l.rating,
                                                            )}
                                                            <span className="text-[#1a141020] dark:text-white/15">
                                                                {'★'.repeat(
                                                                    5 -
                                                                        l.rating,
                                                                )}
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <p className="mt-0.5 truncate text-xs text-[#1a1410]/50 dark:text-white/40">
                                                        {l.domain}
                                                    </p>
                                                    <p className="mt-2 line-clamp-2 text-xs text-[#1a1410]/70 dark:text-white/60">
                                                        {l.snippet}
                                                    </p>
                                                    <div className="mt-3 flex flex-wrap gap-1.5">
                                                        {l.tags.map((t) => (
                                                            <span
                                                                key={t}
                                                                className="inline-flex items-center rounded-full bg-[#fff8ec] px-2 py-0.5 text-[10px] font-medium text-[#c97208] dark:bg-[#fba115]/15 dark:text-[#ffc266]"
                                                            >
                                                                #{t}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
