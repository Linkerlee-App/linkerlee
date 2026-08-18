<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\User;
use App\Services\Models\LinkCreationService;
use App\Support\InboundEmailParser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MailgunInboundController extends Controller
{
    public function __construct(
        protected LinkCreationService $linkCreationService,
    ) {
        //
    }

    public function __invoke(Request $request): Response
    {
        $recipient = (string) $request->input('recipient', '');
        $sender = (string) $request->input('sender', '');
        $subject = $request->input('subject');
        $body = $request->input('stripped-text') ?? $request->input('body-plain');

        Log::debug('Mailgun inbound: received email.', [
            'recipient' => $recipient,
            'sender' => $sender,
            'subject' => $subject,
        ]);

        $user = $this->resolveUser($recipient, $sender);

        if ($user === null) {
            Log::warning('Mailgun inbound: no user matched.', [
                'recipient' => $recipient,
                'sender' => $sender,
            ]);

            return response('No matching user', 406);
        }

        $url = InboundEmailParser::extractUrl($body, $subject);

        if ($url === null) {
            Log::info('Mailgun inbound: no URL found in message.', [
                'user_id' => $user->id,
            ]);

            return response('No URL found', 406);
        }

        if (mb_strlen($url) > Link::MAX_URL_LENGTH) {
            Log::info('Mailgun inbound: URL is longer than the column allows.', [
                'user_id' => $user->id,
                'length' => mb_strlen($url),
            ]);

            return response('URL too long', 406);
        }

        $title = InboundEmailParser::extractTitle($subject, $url);

        $this->linkCreationService->create($user, $url, $title);

        return response('Link saved', 200);
    }

    private function resolveUser(string $recipient, string $sender): ?User
    {
        $local = strtolower(strstr($recipient, '@', true) ?: '');

        if (preg_match('/^inbox-([A-Za-z0-9]{24})$/i', $local, $matches) === 1) {
            $user = User::forInboxToken($matches[1])->first();

            if ($user !== null) {
                return $user;
            }
        }

        if ($sender !== '') {
            return User::where('email', $sender)->first();
        }

        return null;
    }
}
