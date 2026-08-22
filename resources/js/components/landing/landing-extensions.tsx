import { Puzzle } from 'lucide-react';
import { GithubMark } from '@/components/landing/github-mark';
import { EXTENSION_STORES } from '@/lib/extensions';
import { EXTENSION_REPOSITORY_URL } from '@/lib/repositories';

export function LandingExtensions() {
    return (
        <section
            id="extensions"
            className="border-t border-[#1a141010] bg-white py-20 dark:border-white/10 dark:bg-[#0a0a0a]"
        >
            <div className="mx-auto w-full max-w-6xl px-6">
                <div className="mx-auto max-w-2xl text-center">
                    <span className="inline-flex items-center gap-2 rounded-full border border-[#fba115]/40 bg-[#fff8ec] px-3 py-1 text-xs font-medium text-[#c97208] dark:bg-[#fba115]/10 dark:text-[#ffc266]">
                        <Puzzle className="size-3.5" />
                        Browser extension
                    </span>
                    <h2 className="mt-6 text-3xl font-semibold tracking-tight text-balance text-[#1a1410] sm:text-4xl dark:text-white">
                        Save the tab you&apos;re on, in one click.
                    </h2>
                    <p className="mt-4 text-pretty text-[#1a1410]/60 dark:text-white/60">
                        The Linkerlee Bookmarker pre-fills the URL and title,
                        suggests tags that match the page, and tells you when
                        you&apos;ve already saved something. Connect it once
                        with an API token from your settings.
                    </p>
                </div>

                <div className="mt-12 grid gap-4 md:grid-cols-2">
                    {EXTENSION_STORES.map((store) => {
                        const card = (
                            <>
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="text-base font-semibold text-[#1a1410] dark:text-white">
                                        {store.name}
                                    </h3>
                                    {store.href === null && (
                                        <span className="rounded-full bg-[#1a141010] px-2.5 py-1 text-[11px] font-medium text-[#1a1410]/50 dark:bg-white/10 dark:text-white/50">
                                            Coming soon
                                        </span>
                                    )}
                                </div>
                                <p className="mt-2 text-sm text-[#1a1410]/60 dark:text-white/60">
                                    {store.browsers}
                                </p>
                                <p className="mt-4 text-sm leading-relaxed text-[#1a1410]/50 dark:text-white/50">
                                    {store.note}
                                </p>
                                {store.href !== null && (
                                    <span className="mt-5 inline-flex items-center gap-1 text-sm font-medium text-[#c97208] dark:text-[#ffc266]">
                                        Install for {store.shortName}
                                        <span
                                            aria-hidden
                                            className="transition group-hover:translate-x-0.5"
                                        >
                                            →
                                        </span>
                                    </span>
                                )}
                            </>
                        );

                        return store.href !== null ? (
                            <a
                                key={store.id}
                                href={store.href}
                                target="_blank"
                                rel="noreferrer"
                                className="group rounded-2xl border border-[#1a141015] bg-[#fff8ec] p-7 transition hover:border-[#fba115] hover:shadow-sm dark:border-white/10 dark:bg-[#0f0f0f] dark:hover:border-[#fba115]"
                            >
                                {card}
                            </a>
                        ) : (
                            <div
                                key={store.id}
                                className="rounded-2xl border border-dashed border-[#1a141020] bg-transparent p-7 dark:border-white/15"
                            >
                                {card}
                            </div>
                        );
                    })}
                </div>

                <p className="mt-8 text-center text-sm text-[#1a1410]/50 dark:text-white/50">
                    The extension is open source too —{' '}
                    <a
                        href={EXTENSION_REPOSITORY_URL}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1.5 text-[#c97208] underline-offset-4 hover:underline dark:text-[#ffc266]"
                    >
                        <GithubMark className="size-3.5" />
                        read the code or build it yourself
                    </a>
                    .
                </p>
            </div>
        </section>
    );
}
