/**
 * Where the official Linkerlee browser extension can be installed from.
 *
 * A store with a null `href` has not published the extension yet; the UI renders
 * those as "coming soon" rather than as a dead link. The same list drives the
 * landing page section and the footer, so a new store is added in one place.
 */
export type ExtensionStore = {
    id: string;
    /** The store's own name, as it calls itself. */
    name: string;
    /** Short label for tight spaces such as the footer column. */
    shortName: string;
    browsers: string;
    href: string | null;
    note: string;
};

export const FIREFOX_ADDON_URL =
    'https://addons.mozilla.org/en-US/firefox/addon/linkerlee-bookmarker/';

export const EXTENSION_STORES: ExtensionStore[] = [
    {
        id: 'firefox',
        name: 'Firefox Add-ons',
        shortName: 'Firefox',
        browsers: 'Firefox · Firefox for Android',
        href: FIREFOX_ADDON_URL,
        note: 'Reviewed and published by Mozilla.',
    },
    {
        id: 'chrome',
        name: 'Chrome Web Store',
        shortName: 'Chrome',
        browsers: 'Chrome · Edge · Brave · Arc · Vivaldi',
        href: null,
        note: 'Coming soon. Until it lands, load the unpacked build from GitHub.',
    },
];
