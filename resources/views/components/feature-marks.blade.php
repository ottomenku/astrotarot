@props(['feature'])

@php
    $marks = app(\App\Services\FeatureAccessService::class)->marks($feature);
    $packages = $marks['packages'];
    $creditCost = (int) $marks['credit_cost'];
@endphp

@if ($packages !== [] || $creditCost > 0)
    <span {{ $attributes->class('feature-marks') }}>
        @foreach ($packages as $package)
            <span class="feature-mark" title="{{ $package['name'] }}">{!! \App\Support\PackageIcons::svg($package['icon'], $package['slug']) !!}</span>
        @endforeach
        @if ($creditCost > 0)
            <span class="feature-mark-credits" title="{{ __('messages.credits_balance', ['count' => $creditCost]) }}">✦{{ $creditCost }}</span>
        @endif
    </span>
@endif
