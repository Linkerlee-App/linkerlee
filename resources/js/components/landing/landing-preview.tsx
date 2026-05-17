const popularTags = [
    { name: 'design', count: 42 },
    { name: 'laravel', count: 38 },
    { name: 'writing', count: 27 },
    { name: 'react', count: 24 },
    { name: 'tools', count: 19 },
    { name: 'ai', count: 16 },
];

const chartBars = [3, 5, 2, 4, 7, 6, 9, 5, 8, 11, 7, 6, 9, 12, 8, 6, 10, 14, 9, 7, 11, 13, 8, 10, 15, 12, 9, 11, 14, 17];

export function LandingPreview() {
    const max = Math.max(...chartBars);

    return (
        <section className="bg-[#fff8ec] py-20 dark:bg-[#0a0a0a]">
            <div className="mx-auto w-full max-w-6xl px-6">
                <div className="mx-auto mb-14 max-w-2xl text-center">
                    <span className="text-xs font-semibold uppercase tracking-wider text-[#c97208] dark:text-[#ffc266]">
                        Your dashboard
                    </span>
                    <h2 className="mt-3 text-balance text-3xl font-semibold tracking-tight text-[#1a1410] sm:text-4xl dark:text-white">
                        A weekly heartbeat for your library.
                    </h2>
                    <p className="mt-4 text-[#1a1410]/60 dark:text-white/60">
                        See what you&apos;ve saved, what you&apos;re reading about, and which
                        tags are heating up.
                    </p>
                </div>

                <div className="rounded-2xl border border-[#1a141015] bg-white p-6 shadow-[0_24px_60px_-20px_rgba(26,20,16,0.15)] sm:p-8 dark:border-white/10 dark:bg-[#0f0f0f]">
                    {/* stat row */}
                    <div className="grid gap-4 sm:grid-cols-3">
                        {[
                            { label: 'Total links', value: '247', sub: '+12 this week' },
                            { label: 'Collections', value: '12', sub: '3 public' },
                            { label: 'Favorited', value: '38', sub: '15% of all links' },
                        ].map((s) => (
                            <div
                                key={s.label}
                                className="rounded-xl border border-[#1a141010] bg-white p-5 dark:border-white/10 dark:bg-[#161615]"
                            >
                                <p className="text-xs font-medium text-[#1a1410]/50 dark:text-white/40">
                                    {s.label}
                                </p>
                                <p className="mt-2 text-3xl font-semibold tracking-tight text-[#1a1410] dark:text-white">
                                    {s.value}
                                </p>
                                <p className="mt-1 text-xs text-[#c97208] dark:text-[#ffc266]">
                                    {s.sub}
                                </p>
                            </div>
                        ))}
                    </div>

                    <div className="mt-6 grid gap-4 lg:grid-cols-3">
                        {/* chart */}
                        <div className="rounded-xl border border-[#1a141010] bg-white p-5 lg:col-span-2 dark:border-white/10 dark:bg-[#161615]">
                            <div className="flex items-baseline justify-between">
                                <h3 className="text-sm font-semibold text-[#1a1410] dark:text-white">
                                    Links added
                                </h3>
                                <span className="text-xs text-[#1a1410]/50 dark:text-white/40">
                                    Last 30 days
                                </span>
                            </div>
                            <div className="mt-5 flex h-32 items-end gap-1.5">
                                {chartBars.map((v, i) => (
                                    <div
                                        key={i}
                                        className="flex-1 rounded-t bg-gradient-to-t from-[#fba115] to-[#ffc266]"
                                        style={{ height: `${(v / max) * 100}%` }}
                                    />
                                ))}
                            </div>
                        </div>

                        {/* popular tags */}
                        <div className="rounded-xl border border-[#1a141010] bg-white p-5 dark:border-white/10 dark:bg-[#161615]">
                            <h3 className="text-sm font-semibold text-[#1a1410] dark:text-white">
                                Popular tags
                            </h3>
                            <ul className="mt-4 space-y-3">
                                {popularTags.map((t) => (
                                    <li
                                        key={t.name}
                                        className="flex items-center justify-between text-sm"
                                    >
                                        <span className="inline-flex items-center rounded-full bg-[#fff8ec] px-2.5 py-0.5 text-xs font-medium text-[#c97208] dark:bg-[#fba115]/15 dark:text-[#ffc266]">
                                            #{t.name}
                                        </span>
                                        <span className="text-xs tabular-nums text-[#1a1410]/50 dark:text-white/40">
                                            {t.count}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
