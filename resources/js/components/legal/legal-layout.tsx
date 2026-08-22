import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { LandingFooter } from '@/components/landing/landing-footer';
import { LandingNav } from '@/components/landing/landing-nav';
import type { User } from '@/types';

interface PageProps {
    auth?: { user: User | null };
    [key: string]: unknown;
}

type Props = {
    title: string;
    description: string;
    lastUpdated: string;
    intro: ReactNode;
    /** Section headings, in order, rendered as the sticky table of contents. */
    sections: { id: string; title: string }[];
    canRegister?: boolean;
    children: ReactNode;
};

/**
 * The shared shell for /privacy and /terms: the landing chrome, a sticky table
 * of contents, and the prose column. The documents themselves live in the page
 * components so the text is reviewable as one file per document.
 */
export function LegalLayout({
    title,
    description,
    lastUpdated,
    intro,
    sections,
    canRegister = true,
    children,
}: Props) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title={title}>
                <meta name="description" content={description} />
                <link
                    rel="icon"
                    type="image/svg+xml"
                    href="/linkerlee-mark.svg"
                />
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-[#fff8ec] font-sans text-[#1a1410] antialiased dark:bg-[#0a0a0a] dark:text-white">
                <LandingNav
                    user={auth?.user ?? null}
                    canRegister={canRegister}
                />

                <main>
                    <header className="border-b border-[#1a141010] bg-white py-14 dark:border-white/10 dark:bg-[#0a0a0a]">
                        <div className="mx-auto w-full max-w-3xl px-6">
                            <p className="text-xs font-semibold tracking-wider text-[#1a1410]/50 uppercase dark:text-white/40">
                                Legal
                            </p>
                            <h1 className="mt-3 text-4xl font-semibold tracking-tight text-balance text-[#1a1410] dark:text-white">
                                {title}
                            </h1>
                            <p className="mt-4 text-pretty text-[#1a1410]/60 dark:text-white/60">
                                {intro}
                            </p>
                            <p className="mt-6 text-sm text-[#1a1410]/50 dark:text-white/40">
                                Last updated {lastUpdated}
                            </p>
                        </div>
                    </header>

                    <div className="mx-auto w-full max-w-6xl px-6 py-14">
                        <div className="lg:grid lg:grid-cols-[minmax(0,1fr)_14rem] lg:gap-12">
                            <article className="mx-auto w-full max-w-3xl">
                                {children}
                            </article>

                            <nav
                                aria-label="On this page"
                                className="hidden lg:sticky lg:top-24 lg:block lg:self-start"
                            >
                                <p className="text-xs font-semibold tracking-wider text-[#1a1410]/50 uppercase dark:text-white/40">
                                    On this page
                                </p>
                                <ul className="mt-4 space-y-2 text-sm">
                                    {sections.map((section) => (
                                        <li key={section.id}>
                                            <a
                                                href={`#${section.id}`}
                                                className="text-[#1a1410]/60 hover:text-[#c97208] dark:text-white/60 dark:hover:text-[#ffc266]"
                                            >
                                                {section.title}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </nav>
                        </div>
                    </div>
                </main>

                <LandingFooter />
            </div>
        </>
    );
}

/**
 * One numbered clause of a legal document. The id matches the table of contents
 * entry so the sidebar links resolve.
 */
export function LegalSection({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            className="scroll-mt-24 border-t border-[#1a141010] pt-10 first:border-t-0 first:pt-0 dark:border-white/10"
        >
            <h2 className="text-xl font-semibold tracking-tight text-[#1a1410] dark:text-white">
                {title}
            </h2>
            <div className="mt-4 space-y-4 text-sm leading-relaxed text-[#1a1410]/70 dark:text-white/70">
                {children}
            </div>
        </section>
    );
}

export function LegalList({ children }: { children: ReactNode }) {
    return (
        <ul className="list-disc space-y-2 pl-5 marker:text-[#fba115]">
            {children}
        </ul>
    );
}

export function LegalNote({ children }: { children: ReactNode }) {
    return (
        <p className="rounded-xl border border-[#fba115]/30 bg-[#fff8ec] px-5 py-4 text-sm leading-relaxed text-[#1a1410]/70 dark:border-[#fba115]/25 dark:bg-[#fba115]/10 dark:text-white/70">
            {children}
        </p>
    );
}

export function LegalLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    const external = href.startsWith('http') || href.startsWith('mailto:');

    return (
        <a
            href={href}
            target={href.startsWith('http') ? '_blank' : undefined}
            rel={external ? 'noreferrer' : undefined}
            className="text-[#c97208] underline-offset-4 hover:underline dark:text-[#ffc266]"
        >
            {children}
        </a>
    );
}
