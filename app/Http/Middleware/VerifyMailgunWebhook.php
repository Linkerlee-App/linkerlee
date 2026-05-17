<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyMailgunWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $signingKey = config('services.mailgun.webhook_signing_key');

        if (empty($signingKey)) {
            Log::warning('Mailgun webhook signing key is not configured; rejecting inbound request.');

            return response('Webhook not configured', 406);
        }

        $timestamp = (string) $request->input('timestamp', '');
        $token = (string) $request->input('token', '');
        $signature = (string) $request->input('signature', '');

        if ($timestamp === '' || $token === '' || $signature === '') {
            return response('Missing signature fields', 406);
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return response('Stale request', 406);
        }

        $expected = hash_hmac('sha256', $timestamp.$token, $signingKey);

        if (! hash_equals($expected, $signature)) {
            return response('Invalid signature', 406);
        }

        return $next($request);
    }
}
