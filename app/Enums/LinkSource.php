<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * How a link found its way into the account.
 *
 * The value is decided by the entry point that created the row, never by the
 * user, so it stays a record of what actually happened rather than a label
 * anyone can set. Links saved before the column existed have no source at all.
 */
enum LinkSource: string
{
    case Web = 'web';
    case Extension = 'extension';
    case Api = 'api';
    case Email = 'email';
    case Import = 'import';

    /**
     * The URL schemes a browser gives an extension's own pages and workers.
     */
    private const EXTENSION_ORIGIN_SCHEMES = [
        'chrome-extension://',
        'moz-extension://',
        'safari-web-extension://',
    ];

    /**
     * Which client a token-authenticated capture came from.
     *
     * Browser extensions send their own scheme in the `Origin` header, which
     * separates the official extension from any other API consumer without
     * either having to say so — the extension already shipped, and this keeps
     * working for the copies users are running today. A client may also declare
     * itself, but only as one of the two client sources: nothing arriving over
     * the API gets to claim it came from an email or an import.
     */
    public static function fromApiClient(?string $origin, ?string $declared = null): self
    {
        $declaredSource = $declared === null ? null : self::tryFrom($declared);

        if ($declaredSource !== null && in_array($declaredSource, self::apiClientSources(), true)) {
            return $declaredSource;
        }

        if ($origin !== null && Str::startsWith($origin, self::EXTENSION_ORIGIN_SCHEMES)) {
            return self::Extension;
        }

        return self::Api;
    }

    /**
     * The sources an API client is allowed to claim for itself.
     *
     * @return array<int, self>
     */
    public static function apiClientSources(): array
    {
        return [self::Extension, self::Api];
    }

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web app',
            self::Extension => 'Browser extension',
            self::Api => 'API',
            self::Email => 'Email',
            self::Import => 'Import',
        };
    }
}
