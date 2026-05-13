<?php

namespace App\Learning\LaravelInterview\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at?->toISOString(),
            'comments_count' => $this->whenCounted('comments'),
            'author' => $this->whenLoaded('author', fn (): array => [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'email' => $this->author->email,
            ]),
            'comments' => $this->whenLoaded('comments', fn () => $this->comments->map(fn ($comment): array => [
                'id' => $comment->id,
                'body' => $comment->body,
                'is_approved' => $comment->is_approved,
            ])),
        ];
    }
}
