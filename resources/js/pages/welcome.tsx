import { Head, usePage } from '@inertiajs/react';
import { LandingFAQ } from '@/components/landing/landing-faq';
import { LandingFeatures } from '@/components/landing/landing-features';
import { LandingFooter } from '@/components/landing/landing-footer';
import { LandingHero } from '@/components/landing/landing-hero';
import { LandingNav } from '@/components/landing/landing-nav';
import { LandingPreview } from '@/components/landing/landing-preview';
import { LandingPricing } from '@/components/landing/landing-pricing';
import type { User } from '@/types';

interface PageProps {
    auth?: { user: User | null };
    [key: string]: unknown;
}

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage<PageProps>().props;
    const user = auth?.user ?? null;

    return (
        <>
            <Head title="Linkerlee — your bookmarks, finally organized">
                <meta
                    name="description"
                    content="A focused bookmarking tool: save any link, tag it, drop it in a smart group, share it. Linkerlee turns the chaos of saved tabs into a library you'll actually come back to."
                />
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
                <LandingNav user={user} canRegister={canRegister} />
                <main>
                    <LandingHero user={user} canRegister={canRegister} />
                    <LandingFeatures />
                    <LandingPreview />
                    <LandingPricing />
                    <LandingFAQ />
                </main>
                <LandingFooter />
            </div>
        </>
    );
}
