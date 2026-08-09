@extends('layouts.app')

@section('content')
    <main class="card" style="max-width:420px;margin:0 auto;">
        <h1>{{ __('messages.login') }}</h1>
        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">{{ __('admin.users.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="field">
                <label for="password">{{ __('admin.users.password') }}</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field">
                <label><input type="checkbox" name="remember" value="1" style="width:auto"> Remember</label>
            </div>
            <button type="submit">{{ __('messages.login') }}</button>
        </form>
    </main>
@endsection
