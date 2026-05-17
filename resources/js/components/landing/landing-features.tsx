type Feature = {
    title: string;
    body: string;
    icon: React.ReactNode;
};

const features: Feature[] = [
    {
        title: 'Save anything, fast',
        body: 'Drop a URL, get title, description, and metadata fetched automatically. Add notes if you want to remember why.',
        icon: (
            <path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1 1M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1-1" />
        ),
    },
    {
        title: 'Tag & filter, your way',
        body: 'Tag links however you think. Filter by tag with and/or/not logic to find exactly the link you saved three months ago.',
        icon: (
            <>
                <path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                <line x1="7" y1="7" x2="7.01" y2="7" />
            </>
        ),
    },
    {
        title: 'Smart groups',
        body: 'Build collections that fill themselves. Define tag rules; new matching links land in the right place automatically.',
        icon: (
            <>
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
            </>
        ),
    },
    {
        title: 'Favorites & ratings',
        body: 'Star the best stuff. Five-star ratings make it easy to surface the links that earned a second read.',
        icon: (
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
        ),
    },
    {
        title: 'Public sharing',
        body: 'Turn any collection into a clean, public read-only page with one click. Share a reading list, a research dump, anything.',
        icon: (
            <>
                <circle cx="18" cy="5" r="3" />
                <circle cx="6" cy="12" r="3" />
                <circle cx="18" cy="19" r="3" />
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" />
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" />
            </>
        ),
    },
    {
        title: 'Import, export, API',
        body: 'Bring in browser bookmarks. Export anytime. Use the API to wire Linkerlee into the tools you already love.',
        icon: (
            <>
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
            </>
        ),
    },
];

export function LandingFeatures() {
    return (
        <section
            id="features"
            className="border-t border-[#1a141010] bg-white py-20 dark:border-white/10 dark:bg-[#0a0a0a]"
        >
            <div className="mx-auto w-full max-w-6xl px-6">
                <div className="mx-auto max-w-2xl text-center">
                    <h2 className="text-balance text-3xl font-semibold tracking-tight text-[#1a1410] sm:text-4xl dark:text-white">
                        Everything you need. Nothing you don&apos;t.
                    </h2>
                    <p className="mt-4 text-[#1a1410]/60 dark:text-white/60">
                        Linkerlee is a focused bookmarking tool. No social feed, no
                        recommendations, no algorithm. Just your links, organized the way
                        you think.
                    </p>
                </div>

                <div className="mt-14 grid gap-px overflow-hidden rounded-2xl border border-[#1a141010] bg-[#1a141010] sm:grid-cols-2 lg:grid-cols-3 dark:border-white/10 dark:bg-white/10">
                    {features.map((f) => (
                        <div
                            key={f.title}
                            className="group bg-white p-7 transition hover:bg-[#fff8ec] dark:bg-[#0f0f0f] dark:hover:bg-[#161510]"
                        >
                            <div className="flex size-10 items-center justify-center rounded-lg bg-[#fff8ec] text-[#c97208] transition group-hover:bg-[#fba115] group-hover:text-white dark:bg-[#fba115]/15 dark:text-[#ffc266]">
                                <svg
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                >
                                    {f.icon}
                                </svg>
                            </div>
                            <h3 className="mt-5 text-base font-semibold text-[#1a1410] dark:text-white">
                                {f.title}
                            </h3>
                            <p className="mt-2 text-sm leading-relaxed text-[#1a1410]/60 dark:text-white/60">
                                {f.body}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
