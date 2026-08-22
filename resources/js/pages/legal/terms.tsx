import {
    LegalLayout,
    LegalLink,
    LegalList,
    LegalNote,
    LegalSection,
} from '@/components/legal/legal-layout';
import {
    APP_REPOSITORY_URL,
    EXTENSION_REPOSITORY_URL,
    LICENSE_URL,
} from '@/lib/repositories';
import { privacy } from '@/routes/legal';

const LAST_UPDATED = '22 August 2026';
const CONTACT_EMAIL = 'hello@linkerlee.com';

const sections = [
    { id: 'agreement', title: 'The agreement' },
    { id: 'eligibility', title: 'Eligibility' },
    { id: 'accounts', title: 'Your account' },
    { id: 'acceptable-use', title: 'Acceptable use' },
    { id: 'your-content', title: 'Your content' },
    { id: 'sharing', title: 'Public sharing' },
    { id: 'third-party', title: 'Pages you bookmark' },
    { id: 'plans', title: 'Price and plans' },
    { id: 'api', title: 'API and extensions' },
    { id: 'open-source', title: 'Open source' },
    { id: 'availability', title: 'Availability' },
    { id: 'liability', title: 'Liability' },
    { id: 'termination', title: 'Suspension and closure' },
    { id: 'changes', title: 'Changes' },
    { id: 'law', title: 'Governing law' },
    { id: 'contact', title: 'Contact' },
];

export default function Terms({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    return (
        <LegalLayout
            title="Terms of Service"
            description="The agreement between you and Linkerlee: what you can expect from the service, and what it expects from you."
            lastUpdated={LAST_UPDATED}
            canRegister={canRegister}
            sections={sections}
            intro={
                <>
                    Plain terms for a small service. You keep your bookmarks and
                    can take them with you at any time; in return, don&apos;t
                    use Linkerlee to break the law or wreck it for anyone else.
                </>
            }
        >
            <LegalSection id="agreement" title="1. The agreement">
                <p>
                    These terms are a contract between you and the operator of
                    Linkerlee (&ldquo;Linkerlee&rdquo;, &ldquo;we&rdquo;)
                    covering the web application at{' '}
                    <LegalLink href="https://linkerlee.com">
                        linkerlee.com
                    </LegalLink>
                    , its REST API, and the official browser extension. By
                    creating an account or using the service you accept them.
                </p>
                <p>
                    The{' '}
                    <LegalLink href={privacy().url}>Privacy Policy</LegalLink>{' '}
                    forms part of this agreement and describes what we do with
                    the data you entrust to us.
                </p>
            </LegalSection>

            <LegalSection id="eligibility" title="2. Eligibility">
                <p>
                    You must be at least 16 years old, or older if your country
                    sets a higher age for entering this kind of agreement. If
                    you open an account for an organisation, you confirm that
                    you are allowed to bind it to these terms.
                </p>
            </LegalSection>

            <LegalSection id="accounts" title="3. Your account">
                <p>
                    One person, one account. Keep your password to yourself and
                    treat your API tokens and your private save-by-email address
                    as credentials — anyone holding a token can act on your
                    library through the API, and anyone holding the email
                    address can add bookmarks to it.
                </p>
                <p>
                    You are responsible for what happens under your account.
                    Tell us promptly at{' '}
                    <LegalLink href={`mailto:${CONTACT_EMAIL}`}>
                        {CONTACT_EMAIL}
                    </LegalLink>{' '}
                    if you think it has been compromised. Turning on two-factor
                    authentication in your settings is strongly recommended.
                </p>
            </LegalSection>

            <LegalSection id="acceptable-use" title="4. Acceptable use">
                <p>Don&apos;t use Linkerlee to:</p>
                <LegalList>
                    <li>
                        store or share material that is illegal where you or we
                        are, including content that sexually exploits children;
                    </li>
                    <li>
                        distribute malware, run phishing pages, or use share
                        pages to host content designed to deceive people;
                    </li>
                    <li>
                        infringe someone else&apos;s copyright, trade marks, or
                        privacy;
                    </li>
                    <li>
                        send unsolicited bulk mail through the save-by-email
                        address, or point it at anything other than your own
                        bookmarking;
                    </li>
                    <li>
                        probe, scrape, overload, or attempt to break the service
                        or the accounts of other users;
                    </li>
                    <li>
                        automate the API in a way that degrades the service for
                        everyone else. Reasonable personal automation is exactly
                        what the API is for; hammering it is not.
                    </li>
                </LegalList>
            </LegalSection>

            <LegalSection id="your-content" title="5. Your content">
                <p>
                    Your bookmarks, titles, descriptions, tags, and groups
                    remain yours. We claim no ownership of them.
                </p>
                <p>
                    To run the service we need your permission to do the obvious
                    technical things with that content: store it, back it up,
                    index it so search works, transmit it to your own devices,
                    and display it publicly on any page you choose to share.
                    That permission is limited to operating Linkerlee, is
                    non-exclusive, and ends when you delete the content or your
                    account. We do not use your content to train
                    machine-learning models or for any advertising purpose.
                </p>
                <p>
                    You are responsible for what you save, and you confirm you
                    have the right to save it.
                </p>
                <LegalNote>
                    Export your entire library at any time, as JSON or as a
                    standard HTML bookmarks file. There is no lock-in and no
                    export limit — that is deliberate.
                </LegalNote>
            </LegalSection>

            <LegalSection id="sharing" title="6. Public sharing">
                <p>
                    Turning a link or a group into a share page publishes it:
                    the page is readable by anyone who has the URL, without
                    signing in, and may be indexed by search engines that come
                    across it. Only share what you are content to make public.
                </p>
                <p>
                    You may take a share page down at any time by deleting the
                    share. We may remove a share page without notice if it
                    breaches section 4, and we will tell you why when we do.
                </p>
            </LegalSection>

            <LegalSection id="third-party" title="7. Pages you bookmark">
                <p>
                    When you save a link, our servers fetch that page once to
                    collect its title, description, icon, preview image, and
                    text for search. We do not control those sites, do not
                    endorse them, and are not responsible for what they contain
                    or for what happens when you visit them.
                </p>
                <p>
                    Metadata we cache is a copy of what the page published to
                    anonymous visitors at the moment you saved it. If you are
                    the owner of a page and want cached metadata removed, write
                    to us.
                </p>
            </LegalSection>

            <LegalSection id="plans" title="8. Price and plans">
                <p>
                    Linkerlee is currently free to use. The paid Pro and Team
                    plans described on the site are not yet available, and
                    nothing on this site is an offer to sell them today.
                </p>
                <p>
                    If we introduce paid plans, we will publish the pricing and
                    billing terms before charging anyone, and the free tier
                    described at launch will keep working — we will not put your
                    existing bookmarks behind a paywall.
                </p>
            </LegalSection>

            <LegalSection id="api" title="9. API and extensions">
                <p>
                    The REST API is offered as-is for personal and third-party
                    integrations, documented in the repository. We may apply
                    rate limits, and we may change endpoints as the product
                    evolves; we will avoid breaking changes where we reasonably
                    can and announce them in the repository when we cannot.
                </p>
                <p>
                    The official browser extension is distributed through the
                    browser vendors&apos; stores. Your install and updates are
                    also governed by the store&apos;s own terms, which are
                    between you and Mozilla, Google, or whoever else operates
                    it. Unofficial clients built on the API are not ours and are
                    not covered by these terms.
                </p>
            </LegalSection>

            <LegalSection id="open-source" title="10. Open source">
                <p>
                    The Linkerlee{' '}
                    <LegalLink href={APP_REPOSITORY_URL}>web app</LegalLink> and{' '}
                    <LegalLink href={EXTENSION_REPOSITORY_URL}>
                        browser extension
                    </LegalLink>{' '}
                    are published under the{' '}
                    <LegalLink href={LICENSE_URL}>MIT licence</LegalLink>. That
                    licence governs the software: you may read it, fork it, and
                    run your own instance under its terms.
                </p>
                <p>
                    These terms govern the hosted service at linkerlee.com,
                    which is a separate thing from the code. Running your own
                    copy puts you outside this agreement entirely — and makes
                    you responsible for your own instance.
                </p>
            </LegalSection>

            <LegalSection id="availability" title="11. Availability">
                <p>
                    We work to keep Linkerlee up, but it is provided &ldquo;as
                    is&rdquo; and &ldquo;as available&rdquo;, without any
                    warranty. There is no uptime guarantee, features may change
                    or be withdrawn, and background jobs such as metadata
                    fetching may occasionally fail or lag.
                </p>
                <p>
                    Keep your own backups. Exporting your library takes a few
                    seconds, and it is the surest protection against anything
                    going wrong on our side.
                </p>
            </LegalSection>

            <LegalSection id="liability" title="12. Liability">
                <p>
                    To the fullest extent the law allows, we are not liable for
                    indirect or consequential loss, lost profits, or lost data
                    arising from your use of the service. Where liability cannot
                    be excluded, it is limited to the amount you paid us in the
                    twelve months before the claim — which, while the service is
                    free, is nothing.
                </p>
                <p>
                    Nothing here limits liability for death or personal injury
                    caused by negligence, for fraud, or for anything else that
                    cannot lawfully be limited. If you are a consumer, your
                    mandatory statutory rights are unaffected by these terms.
                </p>
            </LegalSection>

            <LegalSection id="termination" title="13. Suspension and closure">
                <p>
                    You can close your account whenever you like, from Settings
                    → Profile. Deleting it removes your bookmarks, tags, groups,
                    tokens, and share pages.
                </p>
                <p>
                    We may suspend or close an account that breaches section 4,
                    that puts the service or other users at risk, or that we are
                    legally required to act against. Except where the breach is
                    serious or urgent, we will warn you first and give you a
                    chance to export your data.
                </p>
                <p>
                    If we ever discontinue the hosted service, we will give
                    account holders reasonable notice — at least thirty days —
                    so there is time to export everything.
                </p>
            </LegalSection>

            <LegalSection id="changes" title="14. Changes">
                <p>
                    We may update these terms as the product changes. The date
                    at the top reflects the current version, and every revision
                    is visible in the public commit history. For material
                    changes we will email account holders before they take
                    effect; continuing to use Linkerlee afterwards means you
                    accept the new terms.
                </p>
            </LegalSection>

            <LegalSection id="law" title="15. Governing law">
                <p>
                    Linkerlee is operated from the European Union and these
                    terms are governed by the law of the operator&apos;s country
                    of establishment, without regard to its conflict-of-law
                    rules.
                </p>
                <p>
                    If you are a consumer resident in the EU, this does not
                    deprive you of the protection of the mandatory consumer law
                    of your own country, and you may bring proceedings in the
                    courts there. If any provision of these terms is held
                    unenforceable, the rest stays in force.
                </p>
            </LegalSection>

            <LegalSection id="contact" title="16. Contact">
                <p>
                    Questions about these terms, abuse reports, and takedown
                    requests:{' '}
                    <LegalLink href={`mailto:${CONTACT_EMAIL}`}>
                        {CONTACT_EMAIL}
                    </LegalLink>
                    .
                </p>
                <p>
                    See also the{' '}
                    <LegalLink href={privacy().url}>Privacy Policy</LegalLink>.
                </p>
            </LegalSection>
        </LegalLayout>
    );
}
