<?php

/**
 * The landing page is client-rendered, so these guard the open-source
 * messaging and the repository links at the source level.
 */
function landingSource(string $relativePath): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/'.$relativePath);
}

test('the repository constants point at the public github repositories', function () {
    expect(landingSource('js/lib/repositories.ts'))
        ->toContain("'https://github.com/linkerlee-app/linkerlee'")
        ->toContain("'https://github.com/linkerlee-app/linkerlee-browser-extension'")
        ->toContain('LICENSE');
});

test('the welcome page renders the open source section', function () {
    expect(landingSource('js/pages/welcome.tsx'))
        ->toContain('LandingOpenSource')
        ->toContain('open-source bookmarking tool');
});

test('the open source section links both repositories', function () {
    expect(landingSource('js/components/landing/landing-open-source.tsx'))
        ->toContain('id="open-source"')
        ->toContain('Linkerlee is open source.')
        ->toContain('APP_REPOSITORY_URL')
        ->toContain('EXTENSION_REPOSITORY_URL');
});

test('the nav points people at the source', function () {
    expect(landingSource('js/components/landing/landing-nav.tsx'))
        ->toContain('APP_REPOSITORY_URL')
        ->toContain('GithubMark');
});

test('the footer links both repositories and the licence', function () {
    expect(landingSource('js/components/landing/landing-footer.tsx'))
        ->toContain('APP_REPOSITORY_URL')
        ->toContain('EXTENSION_REPOSITORY_URL')
        ->toContain('LICENSE_URL');
});

test('the faq answers whether linkerlee is open source', function () {
    expect(landingSource('js/components/landing/landing-faq.tsx'))
        ->toContain('Is Linkerlee open source?')
        ->toContain('APP_REPOSITORY_URL')
        ->toContain('EXTENSION_REPOSITORY_URL');
});
