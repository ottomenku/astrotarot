@props(['feature'])

<span {{ $attributes->class('feature-control') }}>
    {{ $slot }}
    <x-feature-marks :feature="$feature" />
</span>
