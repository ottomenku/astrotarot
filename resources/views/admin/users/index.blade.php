@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <div class="top" style="margin-bottom:1rem">
            <h1 style="margin:0">{{ __('admin.users.title') }}</h1>
            <a class="btn btn-primary" href="{{ route('admin.users.create') }}">{{ __('admin.users.create') }}</a>
        </div>

        @if ($errors->any())
            <div class="error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('admin.users.index') }}" class="row" style="margin-bottom:1rem">
            <div class="field" style="margin:0">
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('admin.users.search') }}">
            </div>
            <div class="field" style="margin:0;align-self:end">
                <button type="submit">{{ __('admin.users.search') }}</button>
            </div>
        </form>

        <table class="admin-compact">
            <thead>
                <tr>
                    <th>{{ __('admin.users.name') }}</th>
                    <th>{{ __('admin.users.email') }}</th>
                    <th class="admin-col-icon"></th>
                    <th>{{ __('admin.users.credits') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr @class(['is-banned' => $user->isBanned()])>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td class="admin-col-icon">
                            <span class="admin-role-icon" title="{{ $user->role->label() }}">{!! $user->role->iconSvg() !!}</span>
                        </td>
                        <td>
                            <form class="admin-credit-form" method="POST" action="{{ route('admin.users.credits', $user) }}">
                                @csrf
                                <span>{{ $user->credits }}</span>
                                <input type="number" name="amount" min="1" max="100000" value="10" aria-label="{{ __('admin.users.credits_amount') }}">
                                <button class="admin-icon-btn" type="submit" title="{{ __('admin.users.credits_add') }}">{!! \App\Support\AdminIcons::plus() !!}</button>
                            </form>
                        </td>
                        <td class="admin-actions">
                            <a class="admin-icon-btn" href="{{ route('admin.users.edit', $user) }}" title="{{ __('admin.users.edit_action') }}">{!! \App\Support\AdminIcons::pencil() !!}</a>
                            @if ($user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.ban', $user) }}" onsubmit="return confirm(@json($user->isBanned() ? __('admin.users.unban_confirm') : __('admin.users.ban_confirm')))">
                                    @csrf
                                    <button class="admin-icon-btn {{ $user->isBanned() ? 'is-on' : 'is-danger' }}" type="submit" title="{{ $user->isBanned() ? __('admin.users.unban') : __('admin.users.ban') }}">{!! \App\Support\AdminIcons::ban() !!}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:1rem">{{ $users->links() }}</div>
    </main>
@endsection
