@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <div class="row" style="align-items:center;margin-bottom:1rem">
            <h1 style="margin:0">{{ __('admin.blog.title') }}</h1>
            <a class="btn btn-primary" href="{{ route('admin.blog.create') }}">{{ __('admin.blog.create') }}</a>
        </div>
        <p class="muted">{{ __('admin.blog.hint') }}</p>

        <table>
            <thead>
                <tr>
                    <th>{{ __('admin.blog.title_col') }}</th>
                    <th>{{ __('admin.blog.status') }}</th>
                    <th>{{ __('admin.blog.likes') }}</th>
                    <th>{{ __('admin.blog.comments') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    <tr>
                        <td>
                            {{ $post->title_hu }}
                            <div class="muted">{{ $post->slug }}</div>
                        </td>
                        <td>{{ $post->isPublished() ? __('admin.blog.published') : __('admin.blog.draft') }}</td>
                        <td>{{ $post->likes_count }}</td>
                        <td>{{ $post->comments_count }}</td>
                        <td>
                            <a href="{{ route('blog.show', $post) }}" target="_blank" rel="noopener">{{ __('admin.blog.view') }}</a>
                            ·
                            <a href="{{ route('admin.blog.edit', $post) }}">{{ __('admin.blog.edit') }}</a>
                            <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" style="display:inline;margin-left:.5rem" onsubmit="return confirm(@json(__('admin.blog.delete_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit">{{ __('admin.blog.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">{{ __('admin.blog.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $posts->links() }}
    </main>
@endsection
