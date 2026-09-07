<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.blog_title') }} · AstroTarot</title>
    <meta name="description" content="{{ __('messages.blog_lead') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="AstroTarot">
    <meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_US' : 'hu_HU' }}">
    <meta property="og:title" content="{{ __('messages.blog_title') }} · AstroTarot">
    <meta property="og:description" content="{{ __('messages.blog_lead') }}">
    <meta property="og:url" content="{{ route('blog.index') }}">
    <meta property="og:image" content="{{ asset('og/astrotarot-promo.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ route('blog.index') }}">
    @if (filled(config('services.facebook.client_id')))
        <meta property="fb:app_id" content="{{ config('services.facebook.client_id') }}">
    @endif
    <link rel="stylesheet" href="{{ $assetBase }}/app.css?v={{ filemtime(public_path('templates/art-deco/app.css')) }}">
</head>
<body>
<main class="app-shell">
    @include('blog.partials.topbar')
    <section class="blog-page">
        <p class="credits-kicker">AstroTarot</p>
        <h1>{{ __('messages.blog_title') }}</h1>
        <p class="credits-lead">{{ __('messages.blog_lead') }}</p>

        <div class="blog-grid">
            @forelse ($posts as $post)
                <article class="blog-card">
                    <a class="blog-card-media" href="{{ route('blog.show', $post) }}">
                        <img src="{{ $post->ogImageUrl() }}" alt="" width="1200" height="630">
                    </a>
                    <div class="blog-card-body">
                        <p class="blog-card-meta">{{ optional($post->published_at)->timezone(config('app.timezone'))->translatedFormat('Y. m. d.') }}</p>
                        <h2><a href="{{ route('blog.show', $post) }}">{{ $post->title() }}</a></h2>
                        <p>{{ $post->excerpt() }}</p>
                        <p class="blog-card-stats">{{ __('messages.blog_likes', ['count' => $post->likes_count]) }} · {{ __('messages.blog_comments', ['count' => $post->comments_count]) }}</p>
                    </div>
                </article>
            @empty
                <p class="blog-empty">{{ __('messages.blog_empty') }}</p>
            @endforelse
        </div>
        {{ $posts->links() }}
    </section>
    @include('templates.art-deco.partials.site-footer')
</main>
</body>
</html>
