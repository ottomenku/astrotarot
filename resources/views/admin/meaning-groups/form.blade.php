@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    @php
        $entryRows = $entries ?? [];
    @endphp

    <main class="card">
        <h1>{{ $group->exists ? __('admin.meaning_groups.edit') : __('admin.meaning_groups.create') }}</h1>
        <p class="muted">{{ __('admin.meaning_groups.form_hint') }}</p>

        @if ($errors->any())
            <div class="error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $group->exists ? route('admin.meaning-groups.update', $group) : route('admin.meaning-groups.store') }}" data-meaning-form>
            @csrf
            @if ($group->exists)
                @method('PUT')
            @endif

            <div class="row">
                <div class="field">
                    <label for="name_hu">{{ __('admin.meaning_groups.name_hu') }}</label>
                    <input id="name_hu" name="name_hu" value="{{ old('name_hu', $group->name_hu) }}" required>
                </div>
                <div class="field">
                    <label for="name_en">{{ __('admin.meaning_groups.name_en') }}</label>
                    <input id="name_en" name="name_en" value="{{ old('name_en', $group->name_en) }}" required>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="slug">{{ __('admin.meaning_groups.slug') }}</label>
                    <input id="slug" name="slug" value="{{ old('slug', $group->slug) }}">
                    <p class="muted">{{ __('admin.meaning_groups.slug_hint') }}</p>
                </div>
                <div class="field">
                    <label for="type">{{ __('admin.meaning_groups.type') }}</label>
                    <select id="type" name="type" required>
                        <option value="tarot" @selected(old('type', $group->type) === 'tarot')>{{ __('admin.packs.type_tarot') }}</option>
                        <option value="oracle" @selected(old('type', $group->type) === 'oracle')>{{ __('admin.packs.type_oracle') }}</option>
                    </select>
                </div>
                <div class="field">
                    <label for="owner_id">{{ __('admin.meaning_groups.owner') }}</label>
                    <select id="owner_id" name="owner_id">
                        <option value="">—</option>
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}" @selected((string) old('owner_id', $group->owner_id) === (string) $owner->id)>{{ $owner->name }}</option>
                        @endforeach
                    </select>
                    <p class="muted">{{ __('admin.meaning_groups.owner_hint') }}</p>
                </div>
            </div>

            <div class="field">
                <label for="description_hu">{{ __('admin.meaning_groups.description_hu') }}</label>
                <textarea id="description_hu" name="description_hu" rows="2">{{ old('description_hu', $group->description_hu) }}</textarea>
            </div>
            <div class="field">
                <label for="description_en">{{ __('admin.meaning_groups.description_en') }}</label>
                <textarea id="description_en" name="description_en" rows="2">{{ old('description_en', $group->description_en) }}</textarea>
            </div>

            <h2>{{ __('admin.meaning_groups.entries') }}</h2>
            <p class="muted">{{ __('admin.meaning_groups.entries_hint') }}</p>
            <div data-entry-list>
                @forelse ($entryRows as $index => $entry)
                    @include('admin.meaning-groups.partials.entry-row', ['index' => $index, 'entry' => $entry])
                @empty
                    @include('admin.meaning-groups.partials.entry-row', ['index' => 0, 'entry' => []])
                @endforelse
            </div>
            <p>
                <button type="button" data-add-entry>{{ __('admin.meaning_groups.add_entry') }}</button>
            </p>

            <p style="display:flex;gap:.75rem;flex-wrap:wrap">
                <button type="submit">{{ __('admin.meaning_groups.save') }}</button>
                <a href="{{ route('admin.meaning-groups.index') }}">{{ __('admin.meaning_groups.back') }}</a>
            </p>
        </form>

        <template data-entry-template>
            @include('admin.meaning-groups.partials.entry-row', ['index' => '__INDEX__', 'entry' => []])
        </template>
    </main>

    <script>
        (() => {
            const list = document.querySelector('[data-entry-list]');
            const add = document.querySelector('[data-add-entry]');
            const template = document.querySelector('[data-entry-template]');
            const nextIndex = () => {
                const used = [...list.querySelectorAll('[data-entry-row]')].map((row) => Number(row.getAttribute('data-entry-row')) || 0);
                return (used.length ? Math.max(...used) : -1) + 1;
            };
            add?.addEventListener('click', () => {
                const index = nextIndex();
                const html = template.innerHTML.replaceAll('__INDEX__', String(index));
                list.insertAdjacentHTML('beforeend', html);
            });
            list?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-entry]');
                if (!button) return;
                button.closest('[data-entry-row]')?.remove();
            });
        })();
    </script>
@endsection
