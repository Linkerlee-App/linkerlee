import { Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import type { User } from '@/types';

type Props = {
    user: User | null;
    canRegister?: boolean;
};

export function LandingNav({ user, canRegister = true }: Props) {
    return (
        <header className="sticky top-0 z-40 border-b border-[#1a141014] bg-[#fff8ec]/80 backdrop-blur dark:border-white/10 dark:bg-[#0a0a0a]/80">
            <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-6">
                <Link href="/" aria-label="Linkerlee home" className="flex items-center">
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
                </Link>

                <nav className="flex items-center gap-2 text-sm">
                    <a
                        href="#features"
                        className="hidden rounded-md px-3 py-2 text-[#1a1410]/70 hover:text-[#1a1410] sm:inline-block dark:text-white/70 dark:hover:text-white"
                    >
                        Features
                    </a>
                    <a
                        href="#pricing"
                        className="hidden rounded-md px-3 py-2 text-[#1a1410]/70 hover:text-[#1a1410] sm:inline-block dark:text-white/70 dark:hover:text-white"
                    >
                        Pricing
                    </a>
                    <a
                        href="#faq"
                        className="hidden rounded-md px-3 py-2 text-[#1a1410]/70 hover:text-[#1a1410] sm:inline-block dark:text-white/70 dark:hover:text-white"
                    >
                        FAQ
                    </a>
                    <span className="mx-2 hidden h-5 w-px bg-[#1a141020] sm:inline-block dark:bg-white/15" />

                    {user ? (
                        <Link
                            href="/links"
                            className="inline-flex h-9 items-center rounded-md bg-[#fba115] px-4 text-sm font-medium text-[#1a1410] shadow-sm transition hover:bg-[#e8890a] hover:text-white"
                        >
                            Open app
                        </Link>
                    ) : (
                        <>
                            <Link
                                href={login()}
                                className="inline-flex h-9 items-center rounded-md px-3 text-[#1a1410] hover:bg-[#1a141008] dark:text-white dark:hover:bg-white/5"
                            >
                                Log in
                            </Link>
                            {canRegister && (
                                <Link
                                    href={register()}
                                    className="inline-flex h-9 items-center rounded-md bg-[#fba115] px-4 text-sm font-medium text-[#1a1410] shadow-sm transition hover:bg-[#e8890a] hover:text-white"
                                >
                                    Sign up free
                                </Link>
                            )}
                        </>
                    )}
                </nav>
            </div>
        </header>
    );
}
