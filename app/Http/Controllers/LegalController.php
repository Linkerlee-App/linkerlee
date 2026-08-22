<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * The public legal pages. They carry no per-user data, so they stay outside the
 * auth middleware and render the same document for guests and signed-in users.
 * They share the landing chrome, which needs to know whether signup is open.
 */
class LegalController extends Controller
{
    public function privacy(): Response
    {
        return Inertia::render('legal/privacy', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }

    public function terms(): Response
    {
        return Inertia::render('legal/terms', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }
}
