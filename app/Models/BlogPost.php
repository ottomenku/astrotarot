<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'title_hu',
        'title_en',
        'excerpt_hu',
        'excerpt_en',
        'body_hu',
        'body_en',
        'cover_path',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(BlogLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class)->latest();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function title(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        if ($locale === 'en' && filled($this->title_en)) {
            return (string) $this->title_en;
        }

        return (string) $this->title_hu;
    }

    public function excerpt(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $text = $locale === 'en' && filled($this->excerpt_en)
            ? (string) $this->excerpt_en
            : (string) ($this->excerpt_hu ?: '');

        if ($text !== '') {
            return $text;
        }

        return Str::limit(trim(html_entity_decode(strip_tags($this->body($locale)), ENT_QUOTES, 'UTF-8')), 180);
    }

    public function body(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $html = $locale === 'en' && filled($this->body_en)
            ? (string) $this->body_en
            : (string) $this->body_hu;

        return HtmlSanitizer::articleBody($html);
    }

    public function coverUrl(): ?string
    {
        if (! is_string($this->cover_path) || $this->cover_path === '') {
            return null;
        }

        return asset($this->cover_path);
    }

    public function ogImageUrl(): string
    {
        return $this->coverUrl() ?: asset('og/astrotarot-promo.jpg');
    }

    public function likedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->relationLoaded('likes')) {
            return $this->likes->contains('user_id', $user->id);
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }

    /**
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
