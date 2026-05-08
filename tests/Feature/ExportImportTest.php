<?php

use App\Models\Group;
use App\Models\Link;
use App\Models\User;
use App\Services\ExportService;
use App\Services\HtmlBookmarkExportService;
use App\Services\HtmlBookmarkImportService;
use App\Services\ImportService;

test('export service returns links tags and groups', function () {
    $user = User::factory()->create();
    $link = Link::factory()->create(['user_id' => $user->id]);
    Group::factory()->create(['user_id' => $user->id]);

    $export = app(ExportService::class)->exportUserData($user);

    expect($export)->toHaveKey('links')
        ->and($export)->toHaveKey('tags')
        ->and($export)->toHaveKey('groups')
        ->and($export['links'])->toHaveCount(1)
        ->and($export['links'][0]['link'])->toBe($link->link);
});

test('html bookmark export service creates valid netscape html', function () {
    $export = ['links' => [['title' => 'Example', 'link' => 'https://example.com', 'tags' => []]]];
    $html = app(HtmlBookmarkExportService::class)->createHtmlExport($export);

    expect($html)->toContain('NETSCAPE-Bookmark-file-1')
        ->and($html)->toContain('https://example.com')
        ->and($html)->toContain('Example');
});

test('html bookmark import service extracts links from netscape html', function () {
    $html = '<!DOCTYPE NETSCAPE-Bookmark-file-1><DL><p><DT><A HREF="https://example.com">Example</A></DL>';

    $data = app(HtmlBookmarkImportService::class)->extractData($html);

    expect($data)->toHaveKey('links')
        ->and($data['links'])->toHaveCount(1)
        ->and($data['links'][0]['link'])->toBe('https://example.com')
        ->and($data['links'][0]['title'])->toBe('Example');
});

test('import service creates links for user', function () {
    $user = User::factory()->create();
    $data = [
        'links' => [
            ['title' => 'Test', 'link' => 'https://test.com', 'tags' => []],
        ],
    ];

    app(ImportService::class)->importUserData($data, ['links'], $user);

    expect(Link::where('user_id', $user->id)->where('link', 'https://test.com')->exists())->toBeTrue();
});

test('import service skips duplicate links', function () {
    $user = User::factory()->create();
    Link::factory()->create(['user_id' => $user->id, 'link' => 'https://dupe.com']);
    $data = [
        'links' => [
            ['title' => 'Dupe', 'link' => 'https://dupe.com', 'tags' => []],
        ],
    ];

    app(ImportService::class)->importUserData($data, ['links'], $user);

    expect(Link::where('user_id', $user->id)->where('link', 'https://dupe.com')->count())->toBe(1);
});

test('export import round trip preserves links', function () {
    $user = User::factory()->create();
    Link::factory()->create(['user_id' => $user->id, 'title' => 'Round Trip', 'link' => 'https://roundtrip.com']);

    $export = app(ExportService::class)->exportUserData($user);

    $otherUser = User::factory()->create();
    app(ImportService::class)->importUserData($export, ['links', 'groups'], $otherUser);

    expect(Link::where('user_id', $otherUser->id)->where('link', 'https://roundtrip.com')->exists())->toBeTrue();
});
