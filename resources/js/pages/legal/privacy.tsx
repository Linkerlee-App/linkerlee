import {
    LegalLayout,
    LegalLink,
    LegalList,
    LegalNote,
    LegalSection,
} from '@/components/legal/legal-layout';
import { EXTENSION_STORES } from '@/lib/extensions';
import { EXTENSION_REPOSITORY_URL } from '@/lib/repositories';
import { terms } from '@/routes/legal';

const LAST_UPDATED = '22 August 2026';
const CONTACT_EMAIL = 'hello@linkerlee.com';

const sections = [
    { id: 'scope', title: 'Scope' },
    { id: 'what-we-collect', title: 'What we collect' },
    { id: 'what-we-dont', title: 'What we never do' },
    { id: 'page-metadata', title: 'Pages we fetch' },
    { id: 'save-by-email', title: 'Save by email' },
    { id: 'extension', title: 'Browser extension' },
    { id: 'sharing', title: 'Public share pages' },
    { id: 'cookies', title: 'Cookies and storage' },
    { id: 'processors', title: 'Who else sees data' },
    { id: 'retention', title: 'Retention and deletion' },
    { id: 'rights', title: 'Your rights' },
    { id: 'security', title: 'Security' },
    { id: 'children', title: 'Children' },
    { id: 'changes', title: 'Changes' },
    { id: 'contact', title: 'Contact' },
];

export default function Privacy({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    return (
        <LegalLayout
            title="Privacy Policy"
            description="What Linkerlee stores, what it never collects, and how to get your data out or delete it."
            lastUpdated={LAST_UPDATED}
            canRegister={canRegister}
            sections={sections}
            intro={
                <>
                    Linkerlee is a bookmarking service. It stores the links you
                    save so you can find them again — nothing more. There are no
                    analytics scripts, no advertising, and no third-party
                    trackers anywhere in the product.
                </>
            }
        >
            <LegalSection id="scope" title="1. Scope">
                <p>
                    This policy covers the Linkerlee web application at{' '}
                    <LegalLink href="https://linkerlee.com">
                        linkerlee.com
                    </LegalLink>
                    , its REST API, and the official Linkerlee browser
                    extension. Linkerlee is operated from the European Union and
                    processes personal data under the GDPR; the operator is the
                    data controller for the accounts it hosts.
                </p>
                <p>
                    Linkerlee is open source. If you run your own copy of the
                    code, you are the controller of that instance and this
                    policy does not apply to it — only to the hosted service at
                    linkerlee.com.
                </p>
            </LegalSection>

            <LegalSection id="what-we-collect" title="2. What we collect">
                <p>
                    <strong>Your account.</strong> A name, an email address, and
                    a password. The password is stored only as a bcrypt hash —
                    it is never stored or transmitted in a readable form and
                    cannot be recovered, only reset. We also store the moment
                    you verified your email address.
                </p>
                <p>
                    <strong>
                        Two-factor authentication, if you enable it.
                    </strong>{' '}
                    A TOTP secret and a set of recovery codes, both encrypted at
                    rest, plus the moment you confirmed the second factor.
                </p>
                <p>
                    <strong>The links you save.</strong> For each bookmark: the
                    URL, a title, an optional description, the tags and groups
                    you file it under, whether you marked it a favourite, any
                    1–5 rating, whether you have read it, how it was captured
                    (web app, browser extension, email, or an imported bookmarks
                    file), and the times it was created and last changed.
                </p>
                <p>
                    <strong>Metadata fetched from the page.</strong> See{' '}
                    <LegalLink href="#page-metadata">section 4</LegalLink>.
                </p>
                <p>
                    <strong>Access credentials you create.</strong> Personal API
                    tokens, stored hashed and shown to you only once when
                    generated. A private, random inbox address token used for
                    save-by-email. A session record while you are signed in, and
                    a long-lived token if you tick &ldquo;remember me&rdquo;.
                </p>
                <p>
                    <strong>Ordinary server logs.</strong> Web server and
                    application logs may record an IP address, a browser user
                    agent, a request path, and a timestamp. They exist to keep
                    the service running and to investigate abuse and errors, and
                    they are rotated and discarded on a short cycle.
                </p>
            </LegalSection>

            <LegalSection id="what-we-dont" title="3. What we never do">
                <LegalList>
                    <li>
                        We do not load analytics, tracking pixels, session
                        recorders, or advertising scripts. There are none in the
                        codebase, and you can verify that yourself — it is
                        public.
                    </li>
                    <li>
                        We do not sell, rent, or trade personal data, and we do
                        not share it with advertisers or data brokers.
                    </li>
                    <li>
                        We do not build advertising or interest profiles from
                        your bookmarks, and we do not use your content to train
                        machine-learning models.
                    </li>
                    <li>
                        We do not read your library except where you ask us to
                        act on it, or where we are legally compelled to, or in
                        the narrow case of investigating abuse reported to us.
                    </li>
                </LegalList>
            </LegalSection>

            <LegalSection id="page-metadata" title="4. Pages we fetch">
                <p>
                    When you save a link, our server requests that page once, in
                    the background, to enrich the bookmark. From the response we
                    store the page title and description (only where you left
                    those blank), the favicon URL, a preview image URL, and the
                    page&apos;s visible text, which is what makes full-text
                    search across your library work.
                </p>
                <p>
                    That request comes from our servers, not from your browser.
                    It carries none of your cookies, credentials, or identity,
                    so the site you bookmarked cannot tell which Linkerlee user
                    saved it. The extracted text is stored against your bookmark
                    and is visible only to you and to anyone you deliberately
                    share that link with.
                </p>
                <LegalNote>
                    Bookmarking a page behind a login will not capture anything
                    private: our server sees only what an anonymous visitor
                    sees.
                </LegalNote>
            </LegalSection>

            <LegalSection id="save-by-email" title="5. Save by email">
                <p>
                    Every account gets a private inbox address containing a
                    random 24-character token. Forward or send a message to it
                    and Linkerlee turns it into a bookmark.
                </p>
                <p>
                    We take the first URL we find in the message, and a title
                    derived from the subject line. The message body is not kept:
                    once the URL and title have been extracted, the rest is
                    discarded. Our inbound mail provider processes the message
                    in transit and applies its own retention to delivery logs.
                </p>
                <p>
                    Treat the inbox address like a password. Anyone who learns
                    it can add bookmarks to your account — they cannot read,
                    change, or delete anything. If it leaks, contact us and we
                    will rotate it.
                </p>
            </LegalSection>

            <LegalSection id="extension" title="6. Browser extension">
                <p>
                    The official Linkerlee Bookmarker extension is published on{' '}
                    {EXTENSION_STORES.filter((store) => store.href !== null)
                        .map((store) => store.name)
                        .join(' and ')}
                    , and its source is public at{' '}
                    <LegalLink href={EXTENSION_REPOSITORY_URL}>
                        linkerlee-app/linkerlee-browser-extension
                    </LegalLink>
                    . It is a client for your own account, and it holds no
                    account of its own.
                </p>
                <p>
                    <strong>What it stores on your device.</strong> Two
                    settings, in your browser&apos;s extension storage: the
                    address of the Linkerlee instance it talks to, and the
                    personal API token you paste in. Neither is sent anywhere
                    except to that instance. Nothing else is persisted locally.
                </p>
                <p>
                    <strong>What permissions it uses, and why.</strong>
                </p>
                <LegalList>
                    <li>
                        <strong>Access browser tabs</strong> — to read the URL
                        and title of the tab you are looking at, so the save
                        form arrives pre-filled and the toolbar badge can tell
                        you whether you already saved this page.
                    </li>
                    <li>
                        <strong>Page content</strong> — to read the text of the
                        page you are actively saving, so it can suggest tags
                        that match what the page is about.
                    </li>
                    <li>
                        <strong>Access to linkerlee.com</strong> (or the host of
                        your self-hosted instance) — to send that bookmark to
                        your account over the API.
                    </li>
                </LegalList>
                <p>
                    <strong>When data leaves your browser.</strong> The
                    extension sends the current URL to your Linkerlee instance
                    to check whether it is already saved, and sends page text
                    when you open the save form and it asks for tag suggestions.
                    It sends the bookmark itself when you click save. It does
                    not stream your browsing history, does not run on pages you
                    are not saving, and contains no analytics or telemetry of
                    any kind.
                </p>
                <p>
                    <strong>Turning it off.</strong> Uninstalling the extension
                    removes the stored token and URL from your browser. Revoke
                    the token itself under Settings → API tokens in the web app;
                    once revoked it stops working everywhere, immediately.
                </p>
                <p>
                    The extension stores are separate companies. Mozilla, Google
                    and the other vendors apply their own privacy policies to
                    the install itself — download counts, ratings, update checks
                    — and we receive only aggregate statistics from them, never
                    anything that identifies an individual user.
                </p>
            </LegalSection>

            <LegalSection id="sharing" title="7. Public share pages">
                <p>
                    You can turn a link or a group into a public, read-only
                    page. Doing so is a deliberate act, and it is the one way
                    your content becomes visible to people who are not signed
                    in.
                </p>
                <p>
                    A share page is reachable by anyone who has its URL. The URL
                    is unguessable, but it is not secret: it can be forwarded,
                    posted, or indexed by a search engine that encounters it.
                    Anything on a shared page — titles, descriptions, tags,
                    saved URLs — should be treated as public. Deleting the share
                    takes the page down; copies other people already made are
                    beyond our reach.
                </p>
            </LegalSection>

            <LegalSection id="cookies" title="8. Cookies and storage">
                <p>
                    Linkerlee sets no advertising or tracking cookies. What it
                    does set is strictly functional:
                </p>
                <LegalList>
                    <li>
                        <strong>Session cookie</strong> — keeps you signed in
                        for the length of a session.
                    </li>
                    <li>
                        <strong>CSRF token cookie</strong> — protects forms
                        against cross-site request forgery.
                    </li>
                    <li>
                        <strong>&ldquo;Remember me&rdquo; cookie</strong> — only
                        if you ask for it, so you stay signed in between visits.
                    </li>
                    <li>
                        <strong>Appearance and sidebar preferences</strong> —
                        remembers light or dark mode and whether the sidebar is
                        collapsed. Also mirrored in your browser&apos;s local
                        storage.
                    </li>
                </LegalList>
                <p>
                    Because none of these are used for tracking or advertising,
                    no consent banner is required for them under the ePrivacy
                    rules. Web fonts are served from a privacy-focused font CDN
                    that sets no cookies and keeps no visitor logs.
                </p>
            </LegalSection>

            <LegalSection id="processors" title="9. Who else sees data">
                <p>
                    We keep the list of third parties as short as the service
                    allows. Today it is:
                </p>
                <LegalList>
                    <li>
                        <strong>Our hosting and database provider</strong>,
                        running on servers in the European Union. They hold the
                        application data at rest on our behalf and have no right
                        to use it.
                    </li>
                    <li>
                        <strong>Our email provider</strong> (Mailgun, EU
                        region), which delivers transactional email —
                        verification, password resets — and receives the inbound
                        messages you send to your private save-by-email address.
                    </li>
                    <li>
                        <strong>A web font CDN</strong>, which serves the
                        typefaces the interface uses without setting cookies or
                        logging visitors.
                    </li>
                </LegalList>
                <p>
                    Each acts as a processor under contract, on our
                    instructions, and none of them may use your data for their
                    own purposes. If we ever add or change a processor, this
                    section changes with it.
                </p>
            </LegalSection>

            <LegalSection id="retention" title="10. Retention and deletion">
                <p>
                    We keep your account data for as long as your account
                    exists. Deleting a link moves it to Trash, where it stays
                    until you restore it or delete it permanently — an emptied
                    Trash is gone from the database, not merely hidden.
                </p>
                <p>
                    You can delete your account at any time from Settings →
                    Profile. Doing so removes your profile, every bookmark you
                    saved, your tags and groups, your API tokens, and any share
                    pages you published. Backups roll off on their own schedule,
                    within thirty days, after which the deletion is complete
                    everywhere.
                </p>
            </LegalSection>

            <LegalSection id="rights" title="11. Your rights">
                <p>
                    Under the GDPR you have the right to access your data, to
                    correct it, to erase it, to restrict or object to its
                    processing, and to take it elsewhere in a portable format.
                    Most of these you can exercise yourself without asking us:
                </p>
                <LegalList>
                    <li>
                        <strong>Access and portability</strong> — export your
                        whole library at any time, as JSON or as an HTML
                        bookmarks file in the standard format every browser
                        reads. The REST API gives you the same data
                        programmatically.
                    </li>
                    <li>
                        <strong>Correction</strong> — edit any bookmark, tag,
                        group, or profile field directly.
                    </li>
                    <li>
                        <strong>Erasure</strong> — delete individual bookmarks,
                        empty the Trash to erase them permanently, or delete the
                        whole account from settings.
                    </li>
                </LegalList>
                <p>
                    For anything the interface does not cover, write to{' '}
                    <LegalLink href={`mailto:${CONTACT_EMAIL}`}>
                        {CONTACT_EMAIL}
                    </LegalLink>{' '}
                    and we will respond within one month. You also have the
                    right to complain to the data protection authority in the EU
                    country where you live.
                </p>
            </LegalSection>

            <LegalSection id="security" title="12. Security">
                <p>
                    All traffic is served over HTTPS. Passwords are bcrypt
                    hashed, two-factor secrets and recovery codes are encrypted
                    at rest, and API tokens are stored hashed. Optional TOTP
                    two-factor authentication is available on every account, and
                    turning it on is the single most effective thing you can do
                    to protect your library.
                </p>
                <p>
                    No system is perfectly secure. If you find a vulnerability,
                    please report it privately to{' '}
                    <LegalLink href={`mailto:${CONTACT_EMAIL}`}>
                        {CONTACT_EMAIL}
                    </LegalLink>{' '}
                    rather than opening a public issue, and give us a reasonable
                    window to fix it. If a breach ever affects your personal
                    data, we will notify you and the relevant supervisory
                    authority as the GDPR requires.
                </p>
            </LegalSection>

            <LegalSection id="children" title="13. Children">
                <p>
                    Linkerlee is not directed at children. You must be at least
                    16 years old to create an account, or older where your
                    country sets a higher age for consenting to data processing.
                    If we learn that we hold data about a child below that age,
                    we will delete it.
                </p>
            </LegalSection>

            <LegalSection id="changes" title="14. Changes">
                <p>
                    We will update this policy when the product changes. The
                    date at the top always reflects the current version, and
                    because the site is open source, every revision is visible
                    in the public commit history. For changes that materially
                    affect how we handle your data, we will email account
                    holders before the change takes effect.
                </p>
            </LegalSection>

            <LegalSection id="contact" title="15. Contact">
                <p>
                    Privacy questions, data requests, and security reports:{' '}
                    <LegalLink href={`mailto:${CONTACT_EMAIL}`}>
                        {CONTACT_EMAIL}
                    </LegalLink>
                    . Anything else:{' '}
                    <LegalLink href="mailto:hello@linkerlee.com">
                        hello@linkerlee.com
                    </LegalLink>
                    .
                </p>
                <p>
                    See also the{' '}
                    <LegalLink href={terms().url}>Terms of Service</LegalLink>.
                </p>
            </LegalSection>
        </LegalLayout>
    );
}
