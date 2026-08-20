const faqs = [
    {
        q: 'Is Linkerlee free?',
        a: 'Yes. The Free plan gives you unlimited bookmarks, tags, and groups, plus one public collection. Pro and Team add power-user features for a few dollars a month.',
    },
    {
        q: 'Can I import bookmarks from my browser?',
        a: 'Yes. Export your bookmarks from Chrome, Firefox, Safari, or Arc as an HTML file and drop it into Linkerlee. We&apos;ll parse the structure, keep your folders as groups, and pull in titles and URLs.',
    },
    {
        q: 'How does public sharing work?',
        a: 'Any group can be turned into a public, read-only page with one click. You get a clean URL you can share anywhere — no signup required for visitors.',
    },
    {
        q: 'Is there an API?',
        a: 'Yes. Generate a personal API token from your settings and read or write to your library from any tool that speaks HTTP. Full API reference lives in the docs.',
    },
    {
        q: 'Where is my data stored?',
        a: 'On our own infrastructure, encrypted at rest. You own your data — export the full set as JSON or HTML any time, no questions asked.',
    },
    {
        q: 'Can I self-host?',
        a: 'Linkerlee is built on Laravel and ships with sensible defaults. Self-hosting docs are on the roadmap; for now, get in touch if you want to run your own instance.',
    },
];

export function LandingFAQ() {
    return (
        <section id="faq" className="bg-[#fff8ec] py-20 dark:bg-[#0a0a0a]">
            <div className="mx-auto w-full max-w-3xl px-6">
                <div className="text-center">
                    <h2 className="text-3xl font-semibold tracking-tight text-balance text-[#1a1410] sm:text-4xl dark:text-white">
                        Questions, answered.
                    </h2>
                    <p className="mt-4 text-[#1a1410]/60 dark:text-white/60">
                        Still wondering about something? Email{' '}
                        <a
                            className="text-[#c97208] underline-offset-4 hover:underline dark:text-[#ffc266]"
                            href="mailto:hello@linkerlee.com"
                        >
                            hello@linkerlee.com
                        </a>
                        .
                    </p>
                </div>

                <div className="mt-12 divide-y divide-[#1a141015] overflow-hidden rounded-2xl border border-[#1a141015] bg-white dark:divide-white/10 dark:border-white/10 dark:bg-[#0f0f0f]">
                    {faqs.map((f) => (
                        <details
                            key={f.q}
                            className="group px-6 py-5 [&_summary::-webkit-details-marker]:hidden"
                        >
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-left">
                                <span className="text-sm font-medium text-[#1a1410] dark:text-white">
                                    {f.q}
                                </span>
                                <svg
                                    className="size-4 shrink-0 text-[#1a1410]/40 transition group-open:rotate-180 dark:text-white/40"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    <polyline points="6 9 12 15 18 9" />
                                </svg>
                            </summary>
                            <p
                                className="mt-3 text-sm leading-relaxed text-[#1a1410]/60 dark:text-white/60"
                                dangerouslySetInnerHTML={{ __html: f.a }}
                            />
                        </details>
                    ))}
                </div>
            </div>
        </section>
    );
}
