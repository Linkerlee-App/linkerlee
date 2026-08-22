import { GithubMark } from '@/components/landing/github-mark';
import {
    APP_REPOSITORY_URL,
    EXTENSION_REPOSITORY_URL,
    LICENSE_URL,
} from '@/lib/repositories';

type Repository = {
    name: string;
    description: string;
    stack: string;
    href: string;
};

const repositories: Repository[] = [
    {
        name: 'linkerlee-app/linkerlee',
        description:
            'The web app: links, tags, smart groups, sharing, the REST API — all of it.',
        stack: 'Laravel 12 · React 19 · TypeScript',
        href: APP_REPOSITORY_URL,
    },
    {
        name: 'linkerlee-app/linkerlee-browser-extension',
        description:
            'Save the page you are on in one click, from Chrome, Firefox, or Edge.',
        stack: 'TypeScript · WebExtensions API',
        href: EXTENSION_REPOSITORY_URL,
    },
];

export function LandingOpenSource() {
    return (
        <section
            id="open-source"
            className="border-t border-[#1a141010] bg-[#fff8ec] py-20 dark:border-white/10 dark:bg-[#0a0a0a]"
        >
            <div className="mx-auto w-full max-w-6xl px-6">
                <div className="overflow-hidden rounded-2xl border border-[#1a141015] bg-[#1a1410] px-6 py-12 sm:px-12 dark:border-white/10">
                    <div className="mx-auto max-w-2xl text-center">
                        <span className="inline-flex items-center gap-2 rounded-full border border-[#fba115]/40 bg-white/5 px-3 py-1 text-xs font-medium text-[#ffc266]">
                            <GithubMark className="size-3.5" />
                            Open source · MIT licensed
                        </span>
                        <h2 className="mt-6 text-3xl font-semibold tracking-tight text-balance text-white sm:text-4xl">
                            Linkerlee is open source.
                        </h2>
                        <p className="mt-4 text-pretty text-white/70">
                            Every line of Linkerlee is public on GitHub. Read
                            the code that handles your bookmarks, open an issue,
                            send a pull request, or fork it and run your own
                            copy — it&apos;s all yours under the{' '}
                            <a
                                href={LICENSE_URL}
                                target="_blank"
                                rel="noreferrer"
                                className="text-[#ffc266] underline-offset-4 hover:underline"
                            >
                                MIT licence
                            </a>
                            .
                        </p>
                    </div>

                    <div className="mt-10 grid gap-4 md:grid-cols-2">
                        {repositories.map((repo) => (
                            <a
                                key={repo.name}
                                href={repo.href}
                                target="_blank"
                                rel="noreferrer"
                                className="group rounded-xl border border-white/10 bg-white/5 p-6 transition hover:border-[#fba115]/50 hover:bg-white/10"
                            >
                                <div className="flex items-center gap-3">
                                    <GithubMark className="size-5 shrink-0 text-white/70 transition group-hover:text-[#ffc266]" />
                                    <h3 className="min-w-0 text-sm font-medium break-words text-white">
                                        {repo.name}
                                    </h3>
                                </div>
                                <p className="mt-3 text-sm leading-relaxed text-white/60">
                                    {repo.description}
                                </p>
                                <p className="mt-4 text-xs tracking-wide text-white/40">
                                    {repo.stack}
                                </p>
                                <span className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-[#ffc266]">
                                    View on GitHub
                                    <span
                                        aria-hidden
                                        className="transition group-hover:translate-x-0.5"
                                    >
                                        →
                                    </span>
                                </span>
                            </a>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
