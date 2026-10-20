@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ $post->exists ? __('admin.blog.edit') : __('admin.blog.create') }}</h1>
        <p class="muted">{{ __('admin.blog.form_hint') }}</p>

        <form method="POST" action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}" enctype="multipart/form-data" data-blog-form data-lang-scope data-active-lang="{{ app()->getLocale() === 'en' ? 'en' : 'hu' }}">
            @csrf
            @if ($post->exists)
                @method('PUT')
            @endif
            @include('admin.partials.lang-switch')

            <div class="row">
                <div class="field" data-lang="hu">
                    <label for="title_hu">{{ __('admin.blog.title_hu') }}</label>
                    <input id="title_hu" name="title_hu" value="{{ old('title_hu', $post->title_hu) }}" required>
                    @error('title_hu') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
                </div>
                <div class="field" data-lang="en">
                    <label for="title_en">{{ __('admin.blog.title_en') }}</label>
                    <input id="title_en" name="title_en" value="{{ old('title_en', $post->title_en) }}">
                </div>
            </div>

            <div class="field">
                <label for="slug">{{ __('admin.blog.slug') }}</label>
                <input id="slug" name="slug" value="{{ old('slug', $post->slug) }}" placeholder="holdfogyatkozas-jelentese">
                <p class="muted">{{ __('admin.blog.slug_hint') }}</p>
                @error('slug') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>

            <div class="row">
                <div class="field" data-lang="hu">
                    <label for="excerpt_hu">{{ __('admin.blog.excerpt_hu') }}</label>
                    <textarea id="excerpt_hu" name="excerpt_hu" rows="3" maxlength="300">{{ old('excerpt_hu', $post->excerpt_hu) }}</textarea>
                    <p class="muted">{{ __('admin.blog.excerpt_hint') }}</p>
                </div>
                <div class="field" data-lang="en">
                    <label for="excerpt_en">{{ __('admin.blog.excerpt_en') }}</label>
                    <textarea id="excerpt_en" name="excerpt_en" rows="3" maxlength="300">{{ old('excerpt_en', $post->excerpt_en) }}</textarea>
                </div>
            </div>

            <div class="field" data-lang="hu">
                <label for="body_hu">{{ __('admin.blog.body_hu') }}</label>
                <textarea id="body_hu" name="body_hu" rows="14" class="js-html-editor" data-editor-locale="hu" required>{{ old('body_hu', $post->body_hu) }}</textarea>
                @error('body_hu') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>
            <div class="field" data-lang="en">
                <label for="body_en">{{ __('admin.blog.body_en') }}</label>
                <textarea id="body_en" name="body_en" rows="10" class="js-html-editor" data-editor-locale="en">{{ old('body_en', $post->body_en) }}</textarea>
            </div>

            <div class="field">
                <label for="cover">{{ __('admin.blog.cover') }}</label>
                <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp">
                <p class="muted">{{ __('admin.blog.cover_hint') }}</p>
                @if ($post->coverUrl())
                    <p><img src="{{ $post->coverUrl() }}" alt="" style="max-width:320px;height:auto;border:1px solid var(--line)"></p>
                @endif
                @error('cover') <p class="muted" style="color:#b33">{{ $message }}</p> @enderror
            </div>

            <div class="row">
                <div class="field">
                    <label>
                        <input type="checkbox" name="publish" value="1" @checked(old('publish', $post->isPublished()))>
                        {{ __('admin.blog.publish') }}
                    </label>
                </div>
                <div class="field">
                    <label for="published_at">{{ __('admin.blog.published_at') }}</label>
                    <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', optional($post->published_at)?->format('Y-m-d\TH:i')) }}">
                </div>
            </div>

            <button type="submit">{{ __('admin.blog.save') }}</button>
            @if ($post->exists)
                <a href="{{ route('blog.show', $post) }}" target="_blank" rel="noopener" style="margin-left:1rem">{{ __('admin.blog.view') }}</a>
            @endif
        </form>

        @if ($post->exists && $post->comments->isNotEmpty())
            <h2 style="margin-top:2rem">{{ __('admin.blog.comments') }}</h2>
            <ul>
                @foreach ($post->comments as $comment)
                    <li style="margin-bottom:1rem">
                        <div class="muted">{{ $comment->user->name ?? '—' }} · {{ $comment->created_at }}</div>
                        <div>{{ $comment->body }}</div>
                        <form method="POST" action="{{ route('blog.comments.destroy', [$post, $comment]) }}" onsubmit="return confirm(@json(__('messages.blog_comment_confirm')))">
                            @csrf
                            @method('DELETE')
                            <button type="submit">{{ __('messages.blog_comment_delete') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </main>
@endsection

@push('scripts')
<script>
(() => {
  const form = document.querySelector('[data-blog-form]');
  if (!form) return;
  function bindEditor(textarea) {
    const wrap = document.createElement('div');
    wrap.className = 'html-editor';
    const toolbar = document.createElement('div');
    toolbar.className = 'html-editor-toolbar';
    const commands = [
      ['bold', 'B'],
      ['italic', 'I'],
      ['insertUnorderedList', '• lista'],
      ['formatBlock:h2', 'Címsor'],
      ['formatBlock:p', 'Bekezdés'],
    ];
    commands.forEach(([command, label]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = label;
      button.addEventListener('click', (event) => {
        event.preventDefault();
        surface.focus();
        const [name, value] = command.split(':');
        document.execCommand(name, false, value || null);
        sync();
      });
      toolbar.appendChild(button);
    });
    const surface = document.createElement('div');
    surface.className = 'html-editor-surface';
    surface.contentEditable = 'true';
    surface.innerHTML = textarea.value || '';
    textarea.classList.add('html-editor-source');
    textarea.parentNode.insertBefore(wrap, textarea);
    wrap.appendChild(toolbar);
    wrap.appendChild(surface);
    wrap.appendChild(textarea);
    function sync() { textarea.value = surface.innerHTML; }
    surface.addEventListener('input', sync);
    form.addEventListener('submit', sync);
  }
  form.querySelectorAll('.js-html-editor').forEach(bindEditor);
})();
</script>
@endpush
