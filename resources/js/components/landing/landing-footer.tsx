import { Link } from '@inertiajs/react';
import { GithubMark } from '@/components/landing/github-mark';
import { EXTENSION_STORES } from '@/lib/extensions';
import {
    APP_REPOSITORY_URL,
    EXTENSION_REPOSITORY_URL,
    LICENSE_URL,
} from '@/lib/repositories';
import { login, register } from '@/routes';
import { privacy, terms } from '@/routes/legal';

export function LandingFooter() {
    return (
        <footer className="border-t border-[#1a141010] bg-white py-14 dark:border-white/10 dark:bg-[#0a0a0a]">
            <div className="mx-auto w-full max-w-6xl px-6">
                <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-[2fr_repeat(5,1fr)]">
                    <div>
                        <img
                            src="/linkerlee-logo-light.svg"
                            alt="Linkerlee"
                            className="h-8 w-auto dark:hidden"
                        />
                        <img
                            src="/linkerlee-logo-dark.svg"
                            alt="Linkerlee"
                            className="hidden h-8 w-auto dark:block"
                        />
                        <p className="mt-4 max-w-xs text-sm text-[#1a1410]/60 dark:text-white/60">
                            A focused bookmarking tool for people who save a lot
                            of links and want to find them again. Open source,
                            MIT licensed.
                        </p>
                        <a
                            href={APP_REPOSITORY_URL}
                            target="_blank"
                            rel="noreferrer"
                            className="mt-4 inline-flex items-center gap-2 rounded-md border border-[#1a141020] px-3 py-1.5 text-xs font-medium text-[#1a1410]/80 transition hover:border-[#fba115] hover:text-[#1a1410] dark:border-white/15 dark:text-white/70 dark:hover:border-[#fba115] dark:hover:text-white"
                        >
                            <GithubMark className="size-3.5" />
                            View source on GitHub
                        </a>
                    </div>

                    <FooterColumn
                        title="Product"
                        links={[
                            { label: 'Features', href: '/#features' },
                            { label: 'Pricing', href: '/#pricing' },
                            { label: 'FAQ', href: '/#faq' },
                        ]}
                    />
                    <FooterColumn
                        title="Extensions"
                        links={[
                            ...EXTENSION_STORES.map((store) => ({
                                label:
                                    store.href === null
                                        ? `${store.shortName} (soon)`
                                        : store.shortName,
                                href: store.href ?? '/#extensions',
                            })),
                            {
                                label: 'Extension source',
                                href: EXTENSION_REPOSITORY_URL,
                            },
                        ]}
                    />
                    <FooterColumn
                        title="Account"
                        links={[
                            {
                                label: 'Sign up',
                                href: register().url,
                                inertia: true,
                            },
                            {
                                label: 'Log in',
                                href: login().url,
                                inertia: true,
                            },
                        ]}
                    />
                    <FooterColumn
                        title="Open source"
                        links={[
                            {
                                label: 'Web app repo',
                                href: APP_REPOSITORY_URL,
                            },
                            {
                                label: 'Browser extension repo',
                                href: EXTENSION_REPOSITORY_URL,
                            },
                            { label: 'MIT licence', href: LICENSE_URL },
                        ]}
                    />
                    <FooterColumn
                        title="Legal"
                        links={[
                            {
                                label: 'Privacy',
                                href: privacy().url,
                                inertia: true,
                            },
                            {
                                label: 'Terms',
                                href: terms().url,
                                inertia: true,
                            },
                            {
                                label: 'Contact',
                                href: 'mailto:hello@linkerlee.com',
                            },
                        ]}
                    />
                </div>

                <div className="mt-12 flex flex-col items-start justify-between gap-4 border-t border-[#1a141010] pt-6 text-xs text-[#1a1410]/50 sm:flex-row sm:items-center dark:border-white/10 dark:text-white/40">
                    <p>
                        © {new Date().getFullYear()} Linkerlee. All rights
                        reserved.
                    </p>
                    <p>Made with care for people with too many tabs open.</p>
                </div>
            </div>
        </footer>
    );
}

type FooterLink = { label: string; href: string; inertia?: boolean };

function FooterColumn({
    title,
    links,
}: {
    title: string;
    links: FooterLink[];
}) {
    return (
        <div>
            <h4 className="text-xs font-semibold tracking-wider text-[#1a1410]/50 uppercase dark:text-white/40">
                {title}
            </h4>
            <ul className="mt-4 space-y-2.5 text-sm">
                {links.map((l) => (
                    <li key={l.label}>
                        {l.inertia ? (
                            <Link
                                href={l.href}
                                className="text-[#1a1410]/80 hover:text-[#fba115] dark:text-white/70 dark:hover:text-[#ffc266]"
                            >
                                {l.label}
                            </Link>
                        ) : (
                            <a
                                href={l.href}
                                target={
                                    l.href.startsWith('http')
                                        ? '_blank'
                                        : undefined
                                }
                                rel={
                                    l.href.startsWith('http')
                                        ? 'noreferrer'
                                        : undefined
                                }
                                className="text-[#1a1410]/80 hover:text-[#fba115] dark:text-white/70 dark:hover:text-[#ffc266]"
                            >
                                {l.label}
                            </a>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
