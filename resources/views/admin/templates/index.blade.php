@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card">
        <h1>{{ __('admin.templates.title') }}</h1>

        <table style="margin-bottom:1.5rem">
            <thead>
                <tr>
                    <th>{{ __('admin.templates.name') }}</th>
                    <th>{{ __('admin.templates.slug') }}</th>
                    <th>{{ __('admin.templates.description') }}</th>
                    <th>{{ __('admin.templates.active') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr>
                        <td>{{ $template->name }}</td>
                        <td>{{ $template->slug }}</td>
                        <td>{{ $template->description }}</td>
                        <td>{{ $template->is_active ? '✓' : '' }}</td>
                        <td>
                            @unless ($template->is_active)
                                <form method="POST" action="{{ route('admin.templates.activate', $template) }}">
                                    @csrf
                                    <button type="submit">{{ __('admin.templates.activate') }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">—</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <h2>{{ __('admin.templates.create') }}</h2>
        <form method="POST" action="{{ route('admin.templates.store') }}">
            @csrf
            <div class="row">
                <div class="field">
                    <label for="name">{{ __('admin.templates.name') }}</label>
                    <input id="name" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="field">
                    <label for="slug">{{ __('admin.templates.slug') }}</label>
                    <input id="slug" name="slug" value="{{ old('slug') }}">
                </div>
            </div>
            <div class="field">
                <label for="description">{{ __('admin.templates.description') }}</label>
                <textarea id="description" name="description" rows="3">{{ old('description') }}</textarea>
            </div>
            <button type="submit">{{ __('admin.templates.save') }}</button>
        </form>
    </main>
@endsection
