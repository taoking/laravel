<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\LiveRoom;
use Illuminate\Http\JsonResponse;

class ChatMessageController extends Controller
{
    public function store(StoreChatMessageRequest $request, LiveRoom $room): JsonResponse
    {
        $nickname = $this->resolveNickname($request);

        $message = $room->messages()->create([
            'user_id' => $request->user()?->id,
            'nickname' => $nickname,
            'content' => trim((string) $request->validated('content')),
        ]);

        $message->load('user');

        $event = new MessageSent($message);

        broadcast($event);

        return response()->json([
            'message' => $event->broadcastWith(),
        ], 201);
    }

    private function resolveNickname(StoreChatMessageRequest $request): string
    {
        if ($request->user()?->name) {
            return $request->user()->name;
        }

        $nickname = trim((string) $request->validated('nickname', ''));

        if ($nickname === '') {
            $nickname = (string) $request->session()->get('live_chat_nickname', '');
        }

        if ($nickname === '') {
            $nickname = 'Guest '.substr($request->session()->getId(), 0, 6);
        }

        $request->session()->put('live_chat_nickname', $nickname);

        return $nickname;
    }
}
