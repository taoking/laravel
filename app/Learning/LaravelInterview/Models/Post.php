<?php

namespace App\Learning\LaravelInterview\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use SoftDeletes;

    protected $table = 'interview_example_posts';

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'is_published',
        'published_at',
        'metadata',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        return $query->when($keyword, function (Builder $query, string $keyword): Builder {
            return $query->where(function (Builder $query) use ($keyword): void {
                $query
                    ->where('title', 'like', "%{$keyword}%")
                    ->orWhere('body', 'like', "%{$keyword}%");
            });
        });
    }

    protected function title(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::headline($value),
        );
    }

    protected function excerpt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes): string => $value
                ?: Str::limit(strip_tags((string) ($attributes['body'] ?? '')), 120),
        );
    }
}
