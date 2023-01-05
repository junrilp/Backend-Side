<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Http\Requests\PremiumAccountRequest;
use App\Repository\Converation\ConversationRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MessageController extends Controller
{
    /**
     * @var ConversationRepository
     */
    private $conversationRepository;

    public function __construct(ConversationRepository $conversationRepository)
    {
        $this->conversationRepository = $conversationRepository;
    }

    /**
     * Render account message page
     * @return \Inertia\Response
     */
    public function messages(PremiumAccountRequest $request, $conversationId = 0)
    {
        if ($userId = $request->get('send_message_to') && $request->user()->canAccessMessaging()) {
            $this->conversationRepository
                ->getOrCreateForUser($userId, $request->user()->id)
                ->touch();
        }

        return Inertia::render('Account/Messages', ['title' => 'Account Messages', 'conversationId' => $conversationId]);
    }
}
