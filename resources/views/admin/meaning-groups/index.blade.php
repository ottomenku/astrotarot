@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <div class="row" style="align-items:center;margin-bottom:1rem">
            <h1 style="margin:0">{{ __('admin.meaning_groups.title') }}</h1>
            <a class="btn btn-primary" href="{{ route('admin.meaning-groups.create') }}">{{ __('admin.meaning_groups.create') }}</a>
        </div>
        <p class="muted">{{ __('admin.meaning_groups.hint') }}</p>

        <table class="admin-compact">
            <thead>
                <tr>
                    <th>{{ __('admin.meaning_groups.name') }}</th>
                    <th>{{ __('admin.meaning_groups.type') }}</th>
                    <th>{{ __('admin.meaning_groups.cards') }}</th>
                    <th>{{ __('admin.meaning_groups.owner') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr>
                        <td>
                            {{ $group->name() }}
                            <div class="muted">{{ $group->slug }}</div>
                        </td>
                        <td>{{ __('admin.packs.type_'.$group->type) }}</td>
                        <td>{{ $group->entries_count }}</td>
                        <td>{{ $group->owner?->name ?? '—' }}</td>
                        <td class="admin-actions">
                            <a class="admin-icon-btn" href="{{ route('admin.meaning-groups.edit', $group) }}" title="{{ __('admin.meaning_groups.edit') }}">{!! \App\Support\AdminIcons::pencil() !!}</a>
                            <form method="POST" action="{{ route('admin.meaning-groups.copy', $group) }}">
                                @csrf
                                <button class="admin-icon-btn" type="submit" title="{{ __('admin.meaning_groups.copy') }}">{!! \App\Support\AdminIcons::plus() !!}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.meaning-groups.destroy', $group) }}" onsubmit="return confirm(@json(__('admin.meaning_groups.delete_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button class="admin-icon-btn is-danger" type="submit" title="{{ __('admin.meaning_groups.delete') }}">{!! \App\Support\AdminIcons::ban() !!}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">{{ __('admin.meaning_groups.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>
@endsection
