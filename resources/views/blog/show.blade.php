@php
    $ogTitle = $post->title();
    $ogDescription = $post->excerpt();
    $ogImage = $post->ogImageUrl();
    $shareUrl = $shareUrl ?? route('blog.show', $post);
    $fbShare = 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($shareUrl);
    $locale = app()->getLocale() === 'en' ? 'en_US' : 'hu_HU';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ogTitle }} · AstroTarot</title>
    <meta name="description" content="{{ $ogDescription }}">
    <link rel="canonical" href="{{ $shareUrl }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="AstroTarot">
    <meta property="og:locale" content="{{ $locale }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $ogTitle }}">
    @if ($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    @if (filled(config('services.facebook.client_id')))
        <meta property="fb:app_id" content="{{ config('services.facebook.client_id') }}">
    @endif
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $ogTitle,
        'description' => $ogDescription,
        'image' => [$ogImage],
        'datePublished' => optional($post->published_at)?->toIso8601String(),
        'dateModified' => optional($post->updated_at)?->toIso8601String(),
        'author' => ['@type' => 'Person', 'name' => $post->author->name ?? 'AstroTarot'],
        'publisher' => ['@type' => 'Organization', 'name' => 'AstroTarot', 'logo' => ['@type' => 'ImageObject', 'url' => asset('og/astrotarot-promo.jpg')]],
        'mainEntityOfPage' => $shareUrl,
        'url' => $shareUrl,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <link rel="stylesheet" href="{{ $assetBase }}/app.css?v={{ filemtime(public_path('templates/art-deco/app.css')) }}">
</head>
<body>
<main class="app-shell">
    @include('blog.partials.topbar')
    <article class="blog-article">
        <p class="credits-kicker"><a href="{{ route('blog.index') }}">{{ __('messages.blog_title') }}</a></p>
        @unless ($post->isPublished())
            <p class="blog-draft-banner">{{ __('messages.blog_draft_preview') }}</p>
        @endunless
        <h1>{{ $ogTitle }}</h1>
        <p class="blog-article-meta">
            {{ optional($post->published_at ?? $post->created_at)->timezone(config('app.timezone'))->translatedFormat('Y. m. d.') }}
            @if ($post->author)
                · {{ $post->author->name }}
            @endif
        </p>
        @if ($post->coverUrl())
            <img class="blog-cover" src="{{ $post->coverUrl() }}" alt="" width="1200" height="630">
        @endif
        <p class="blog-excerpt">{{ $ogDescription }}</p>
        <div class="blog-body">{!! $post->body() !!}</div>

        <section class="blog-engage" aria-label="{{ __('messages.blog_engage') }}">
            <div class="blog-engage-row">
                @auth
                    <form method="POST" action="{{ route('blog.like', $post) }}">
                        @csrf
                        <button class="blog-like-btn {{ $liked ? 'is-on' : '' }}" type="submit">
                            {{ $liked ? __('messages.blog_unlike') : __('messages.blog_like') }}
                            <span>{{ $post->likes_count }}</span>
                        </button>
                    </form>
                @else
                    <a class="blog-like-btn" href="{{ route('login') }}">{{ __('messages.blog_like_login') }} · {{ $post->likes_count }}</a>
                @endauth
                <a class="reading-share-btn reading-share-fb" href="{{ $fbShare }}" target="_blank" rel="noopener noreferrer">{{ __('messages.blog_share_facebook') }}</a>
                <button class="reading-share-btn" type="button" data-copy-link data-copy="{{ $shareUrl }}">{{ __('messages.blog_copy_link') }}</button>
            </div>
        </section>

        <section class="blog-comments" id="comments">
            <h2>{{ __('messages.blog_comments', ['count' => $post->comments_count]) }}</h2>
            @if (session('status'))
                <p class="blog-flash">{{ session('status') }}</p>
            @endif
            @auth
                <form class="blog-comment-form" method="POST" action="{{ route('blog.comments.store', $post) }}">
                    @csrf
                    <label for="comment-body">{{ __('messages.blog_comment_label') }}</label>
                    <textarea id="comment-body" name="body" rows="4" required minlength="2" maxlength="2000">{{ old('body') }}</textarea>
                    @error('body')
                        <p class="blog-error">{{ $message }}</p>
                    @enderror
                    <button type="submit">{{ __('messages.blog_comment_submit') }}</button>
                </form>
            @else
                <p class="blog-comment-login"><a href="{{ route('login') }}">{{ __('messages.blog_comment_login') }}</a></p>
            @endauth

            <ul class="blog-comment-list">
                @forelse ($post->comments as $comment)
                    <li>
                        <p class="blog-comment-meta"><strong>{{ $comment->user->name ?? __('messages.account_none') }}</strong> · {{ $comment->created_at->timezone(config('app.timezone'))->format('Y. m. d. H:i') }}</p>
                        <p>{{ $comment->body }}</p>
                        @if ($comment->canDelete(auth()->user()))
                            <form method="POST" action="{{ route('blog.comments.destroy', [$post, $comment]) }}" onsubmit="return confirm(@json(__('messages.blog_comment_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit">{{ __('messages.blog_comment_delete') }}</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="muted">{{ __('messages.blog_comments_empty') }}</li>
                @endforelse
            </ul>
        </section>
    </article>
    @include('templates.art-deco.partials.site-footer')
</main>
<script>
(() => {
  const button = document.querySelector('[data-copy-link]');
  if (!button) return;
  button.addEventListener('click', async () => {
    const url = button.getAttribute('data-copy') || window.location.href;
    try {
      await navigator.clipboard.writeText(url);
      button.textContent = @json(__('messages.blog_link_copied'));
    } catch (error) {
      window.prompt(@json(__('messages.blog_copy_link')), url);
    }
  });
})();
</script>
</body>
</html>
