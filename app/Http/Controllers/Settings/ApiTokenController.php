<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $tokens = $request->user()->tokens()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']);

        return Inertia::render('settings/api-tokens', [
            'tokens' => $tokens,
            'plainTextToken' => $request->session()->get('plainTextToken'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->user()->createToken($validated['name'], ['create']);

        return back()->with('plainTextToken', $token->plainTextToken);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back();
    }
}
