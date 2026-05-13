<?php

namespace App\Learning\LaravelInterview\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Learning\LaravelInterview\Http\Requests\StorePostRequest;
use App\Learning\LaravelInterview\Http\Resources\PostResource;
use App\Learning\LaravelInterview\Models\Post;
use App\Learning\LaravelInterview\Services\OrderCheckoutService;
use App\Learning\LaravelInterview\Support\InterviewSampleData;
use App\Learning\LaravelInterview\Support\LaravelComponentCheatsheet;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewExamplesController extends Controller
{
    public function __construct(
        private readonly LaravelComponentCheatsheet $cheatsheet,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'topics' => [
                'routing_controller_request_resource',
                'service_container_provider',
                'eloquent_relationship_scope_cast',
                'query_builder_transaction_lock',
                'validation_policy_middleware',
                'cache_queue_event_listener',
                'mail_notification_storage_http_client',
            ],
            'sample_payload' => InterviewSampleData::postPayload(),
        ]);
    }

    public function collections(): JsonResponse
    {
        return response()->json($this->cheatsheet->collectionExamples());
    }

    public function show(Post $post): PostResource
    {
        return PostResource::make($post->loadMissing(['author', 'comments']));
    }

    public function store(StorePostRequest $request): PostResource
    {
        $post = Post::query()->create([
            ...$request->safe()->only([
                'title',
                'slug',
                'excerpt',
                'body',
                'is_published',
                'published_at',
                'metadata',
            ]),
            'user_id' => $request->user()?->getAuthIdentifier() ?: User::query()->value('id'),
        ]);

        return PostResource::make($post->loadMissing('author'));
    }

    public function checkout(Request $request, OrderCheckoutService $checkout): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price_cents' => ['required', 'integer', 'min:1'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $totalCents = collect($validated['items'])
            ->sum(fn (array $item): int => $item['quantity'] * $item['price_cents']);

        return response()->json($checkout->checkout($user, [
            'items' => $validated['items'],
            'total_cents' => $totalCents,
        ]));
    }

    public function dashboardStats(): JsonResponse
    {
        return response()->json($this->cheatsheet->cacheExamples());
    }
}
