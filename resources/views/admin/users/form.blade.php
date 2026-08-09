@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card" style="max-width:640px">
        <h1>{{ $user->exists ? __('admin.users.edit') : __('admin.users.create') }}</h1>

        @if ($errors->any())
            <div class="error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf
            @if ($user->exists)
                @method('PUT')
            @endif

            <div class="field">
                <label for="name">{{ __('admin.users.name') }}</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="field">
                <label for="email">{{ __('admin.users.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </div>
            <div class="field">
                <label for="password">{{ __('admin.users.password') }}</label>
                <input id="password" type="password" name="password" @required(! $user->exists)>
            </div>
            <div class="field">
                <label for="role">{{ __('admin.users.role') }}</label>
                <select id="role" name="role" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="template_id">{{ __('admin.users.template') }}</label>
                <select id="template_id" name="template_id">
                    <option value="">{{ __('admin.users.none') }}</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}" @selected((string) old('template_id', $user->template_id) === (string) $template->id)>{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit">{{ __('admin.users.save') }}</button>
        </form>
    </main>
@endsection
