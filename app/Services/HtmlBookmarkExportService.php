<?php

namespace App\Services;

class HtmlBookmarkExportService
{
    public function createHtmlExport(array $export): string
    {
        $links = $export['links'] ?? [];

        $items = '';
        foreach ($links as $link) {
            $title = htmlspecialchars($link['title'] ?? $link['link'], ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($link['link'], ENT_QUOTES, 'UTF-8');
            $items .= "    <DT><A HREF=\"{$url}\">{$title}</A>\n";
        }

        return "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n"
            ."<!-- This is an automatically generated file.\n"
            ."     It will be read and overwritten.\n"
            ."     DO NOT EDIT! -->\n"
            ."<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n"
            ."<TITLE>Bookmarks</TITLE>\n"
            ."<H1>Bookmarks</H1>\n"
            ."<DL><p>\n"
            ."{$items}"
            .'</DL>';
    }
}
