@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <div class="top" style="margin-bottom:1rem">
            <h1 style="margin:0">{{ __('admin.users.title') }}</h1>
            <a class="btn btn-primary" href="{{ route('admin.users.create') }}">{{ __('admin.users.create') }}</a>
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="row" style="margin-bottom:1rem">
            <div class="field" style="margin:0">
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('admin.users.search') }}">
            </div>
            <div class="field" style="margin:0;align-self:end">
                <button type="submit">{{ __('admin.users.search') }}</button>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>{{ __('admin.users.name') }}</th>
                    <th>{{ __('admin.users.email') }}</th>
                    <th>{{ __('admin.users.role') }}</th>
                    <th>{{ __('admin.users.template') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role->label() }}</td>
                        <td>{{ $user->template?->name ?? __('admin.users.none') }}</td>
                        <td><a href="{{ route('admin.users.edit', $user) }}">{{ __('admin.users.edit') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:1rem">{{ $users->links() }}</div>
    </main>
@endsection
