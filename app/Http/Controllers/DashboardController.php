<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Link;
use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $userId = Auth::id();

        $totalLinks = Link::where('user_id', $userId)->count();
        $totalGroups = Group::where('user_id', $userId)->count();
        $favoritedLinks = Link::where('user_id', $userId)->where('is_favorite', true)->count();

        $userLinkIds = Link::where('user_id', $userId)->select('id');

        $tagCounts = DB::table('taggables')
            ->select('tag_id', DB::raw('COUNT(*) as count'))
            ->where('taggable_type', Link::class)
            ->whereIn('taggable_id', $userLinkIds)
            ->groupBy('tag_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $tagModels = Tag::whereIn('id', $tagCounts->pluck('tag_id'))->get()->keyBy('id');

        $popularTags = $tagCounts->map(fn ($row) => [
            'name' => $tagModels[$row->tag_id]->name ?? '',
            'count' => $row->count,
        ])->toArray();

        $recentLinks = Link::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Link $link) => [
                'id' => $link->id,
                'title' => $link->title,
                'link' => $link->link,
                'created_at' => $link->created_at->format('d.m.Y'),
            ])->toArray();

        $linksPerDay = Link::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'count' => $row->count,
            ])->toArray();

        return Inertia::render('dashboard', [
            'stats' => [
                'totalLinks' => $totalLinks,
                'totalGroups' => $totalGroups,
                'favoritedLinks' => $favoritedLinks,
            ],
            'popularTags' => $popularTags,
            'recentLinks' => $recentLinks,
            'linksPerDay' => $linksPerDay,
        ]);
    }
}
