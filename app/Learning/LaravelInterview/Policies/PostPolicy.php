<?php

namespace App\Learning\LaravelInterview\Policies;

use App\Learning\LaravelInterview\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->email === 'admin@example.com' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(?User $user, Post $post): bool
    {
        return $post->is_published || $user?->is($post->author);
    }

    public function create(User $user): bool
    {
        return $user->email_verified_at !== null;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->is($post->author);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->is($post->author) && ! $post->is_published;
    }
}
