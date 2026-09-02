@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <div class="row" style="align-items:center;margin-bottom:1rem">
            <h1 style="margin:0">{{ __('admin.packs.title') }}</h1>
            <span style="display:flex;gap:.5rem;flex-wrap:wrap">
                <a class="btn btn-primary" href="{{ route('admin.packs.create', ['type' => 'tarot']) }}">{{ __('admin.packs.create_tarot') }}</a>
                <a class="btn" href="{{ route('admin.packs.create', ['type' => 'oracle']) }}">{{ __('admin.packs.create_oracle') }}</a>
                <a class="btn" href="{{ route('admin.packs.create', ['type' => 'card_back']) }}">{{ __('admin.packs.create_back') }}</a>
                <a class="btn" href="{{ route('admin.packs.create', ['type' => 'background']) }}">{{ __('admin.packs.create_background') }}</a>
            </span>
        </div>
        <p class="muted">{{ __('admin.packs.hint') }}</p>

        @if (session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        @foreach ($types as $type)
            <h2>{{ __('admin.packs.type_'.$type) }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('admin.packs.name') }}</th>
                        <th>{{ __('admin.packs.cards') }}</th>
                        <th>{{ __('admin.packs.style') }}</th>
                        <th>{{ __('admin.packs.owner') }}</th>
                        <th>{{ __('admin.packs.default') }}</th>
                        <th>{{ __('admin.packs.active') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packs[$type] ?? [] as $pack)
                        <tr>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:.5rem">
                                    @if ($pack->previewUrl())
                                        <img src="{{ $pack->previewUrl() }}" alt="" width="36" height="48" style="object-fit:cover;border-radius:.25rem">
                                    @endif
                                    {{ $pack->name_hu }}
                                </span>
                                <div class="muted">{{ $pack->slug }}@if ($pack->polarity) · {{ $pack->polarity }}@endif</div>
                            </td>
                            <td>{{ $pack->card_count }}</td>
                            <td>{{ $pack->style ?: '—' }}</td>
                            <td>{{ $pack->owner ?: '—' }}</td>
                            <td>{{ $pack->is_default ? '✓' : '—' }}</td>
                            <td>{{ $pack->is_active ? '✓' : '—' }}</td>
                            <td>
                                <a href="{{ route('admin.packs.edit', $pack) }}">{{ __('admin.packs.edit') }}</a>
                                @unless ($pack->is_default)
                                    <form method="POST" action="{{ route('admin.packs.default', $pack) }}" style="display:inline;margin-left:.5rem">
                                        @csrf
                                        <button type="submit">{{ __('admin.packs.make_default') }}</button>
                                    </form>
                                @endunless
                                <form method="POST" action="{{ route('admin.packs.toggle', $pack) }}" style="display:inline;margin-left:.5rem">
                                    @csrf
                                    <button type="submit">{{ $pack->is_active ? __('admin.packs.deactivate') : __('admin.packs.activate') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">{{ __('admin.packs.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
    </main>
@endsection
