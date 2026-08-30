@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <div class="row" style="align-items:center;margin-bottom:1rem">
            <h1 style="margin:0">{{ __('admin.games.title') }}</h1>
            <a class="btn btn-primary" href="{{ route('admin.games.create') }}">{{ __('admin.games.create') }}</a>
        </div>
        <p class="muted">{{ __('admin.games.hint') }}</p>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        <table>
            <thead>
                <tr>
                    <th>{{ __('admin.games.name') }}</th>
                    <th>{{ __('admin.games.rules') }}</th>
                    <th>{{ __('admin.games.question') }}</th>
                    <th>{{ __('admin.games.default') }}</th>
                    <th>{{ __('admin.games.active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($games as $game)
                    <tr>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:.5rem">
                                @if ($game->iconUrl())
                                    <img src="{{ $game->iconUrl() }}" alt="" width="28" height="28" style="object-fit:cover;border-radius:.25rem">
                                @endif
                                {{ $game->name_hu }}
                            </span>
                            <div class="muted">{{ $game->slug }}</div>
                        </td>
                        <td>{{ $game->rules_count }}</td>
                        <td>{{ $game->has_question ? '✓' : '—' }}</td>
                        <td>{{ $game->is_default ? '✓' : '—' }}</td>
                        <td>{{ $game->is_active ? '✓' : '—' }}</td>
                        <td>
                            <a href="{{ route('admin.games.edit', $game) }}">{{ __('admin.games.edit') }}</a>
                            @unless ($game->is_default)
                                <form method="POST" action="{{ route('admin.games.default', $game) }}" style="display:inline;margin-left:.5rem">
                                    @csrf
                                    <button type="submit">{{ __('admin.games.make_default') }}</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('admin.games.toggle', $game) }}" style="display:inline;margin-left:.5rem">
                                @csrf
                                <button type="submit">{{ $game->is_active ? __('admin.games.deactivate') : __('admin.games.activate') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">{{ __('admin.games.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>
@endsection
