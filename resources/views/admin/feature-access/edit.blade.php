@extends('layouts.app')

@section('content')
    @include('admin.partials.nav')

    <main class="card" style="overflow-x:auto">
        <style>.wrap { max-width: 1280px; }</style>
        <h1>{{ __('admin.feature_access.title') }}</h1>
        <p class="muted">{{ __('admin.feature_access.hint') }}</p>
        <p class="muted">{{ __('admin.feature_access.logic_hint') }}</p>

        <form method="POST" action="{{ route('admin.feature-access.update') }}">
            @csrf
            @method('PUT')

            <table>
                <thead>
                    <tr>
                        <th>{{ __('admin.feature_access.feature') }}</th>
                        <th>{{ __('admin.feature_access.card_name') }}</th>
                        <th>{{ __('admin.feature_access.packages') }}</th>
                        <th>{{ __('admin.feature_access.credits') }}</th>
                        <th>{{ __('admin.feature_access.logic') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($catalog as $key => $meta)
                        @php
                            $rule = $rules->get($key);
                            $selected = old('features.'.$key.'.package_ids', $rule?->packages->pluck('id')->all() ?? []);
                            $selected = array_map('intval', is_array($selected) ? $selected : []);
                        @endphp
                        <tr>
                            <td>
                                <div>{{ \App\Support\FeatureCatalog::help($key) }}</div>
                                <div class="muted" style="margin-top:.35rem">
                                    {{ $meta['charges'] ? __('admin.feature_access.charges_yes') : __('admin.feature_access.charges_no') }}
                                </div>
                                @error('features.'.$key.'.credit_cost')
                                    <p class="muted" style="color:#b33">{{ $message }}</p>
                                @enderror
                            </td>
                            <td style="min-width:11rem">
                                <label class="muted" style="margin:0">{{ __('admin.feature_access.label_hu') }}</label>
                                <input
                                    name="features[{{ $key }}][label_hu]"
                                    value="{{ old('features.'.$key.'.label_hu', $rule?->label('hu')) }}"
                                    maxlength="80"
                                    required
                                >
                                <label class="muted" style="margin:.45rem 0 0">{{ __('admin.feature_access.label_en') }}</label>
                                <input
                                    name="features[{{ $key }}][label_en]"
                                    value="{{ old('features.'.$key.'.label_en', $rule?->label('en')) }}"
                                    maxlength="80"
                                    required
                                >
                                <p class="muted" style="margin:.35rem 0 0">{{ __('admin.feature_access.label_hint') }}</p>
                            </td>
                            <td>
                                <div style="display:flex;flex-wrap:wrap;gap:.35rem .85rem">
                                    @foreach ($packages as $package)
                                        <label style="display:flex;align-items:center;gap:.35rem;margin:0;color:inherit">
                                            <input
                                                type="checkbox"
                                                name="features[{{ $key }}][package_ids][]"
                                                value="{{ $package->id }}"
                                                style="width:auto"
                                                @checked(in_array((int) $package->id, $selected, true))
                                            >
                                            @if ($package->iconSvg() !== '')
                                                <span class="feature-marks">{!! $package->iconSvg() !!}</span>
                                            @endif
                                            {{ $package->name_hu }}
                                        </label>
                                    @endforeach
                                </div>
                                <p class="muted" style="margin:.4rem 0 0">{{ __('admin.feature_access.all_packages_hint') }}</p>
                            </td>
                            <td style="width:7rem">
                                <input
                                    type="number"
                                    min="0"
                                    max="100000"
                                    name="features[{{ $key }}][credit_cost]"
                                    value="{{ old('features.'.$key.'.credit_cost', $rule->credit_cost ?? 0) }}"
                                    required
                                >
                            </td>
                            <td style="width:12rem">
                                <select name="features[{{ $key }}][logic]" required>
                                    <option value="and" @selected(old('features.'.$key.'.logic', $rule->logic ?? 'and') === 'and')>{{ __('admin.feature_access.logic_and') }}</option>
                                    <option value="or" @selected(old('features.'.$key.'.logic', $rule->logic ?? 'and') === 'or')>{{ __('admin.feature_access.logic_or') }}</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p>
                <button type="submit">{{ __('admin.feature_access.save') }}</button>
                <a href="{{ route('admin.packages.index') }}">{{ __('admin.packages.back') }}</a>
            </p>
        </form>
    </main>
@endsection
