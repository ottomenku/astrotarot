<nav class="admin-nav">
    <a href="{{ route('admin.ephemeris.index') }}" class="{{ request()->routeIs('admin.ephemeris.*') ? 'active' : '' }}">{{ __('admin.nav.ephemeris') }}</a>
    <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">{{ __('admin.nav.settings') }}</a>
    <a href="{{ route('admin.test-mode.edit') }}" class="{{ request()->routeIs('admin.test-mode.*') ? 'active' : '' }}">{{ __('admin.nav.test_mode') }}</a>
    <a href="{{ route('admin.slots.edit') }}" class="{{ request()->routeIs('admin.slots.*') ? 'active' : '' }}">{{ __('admin.nav.slots') }}</a>
    <a href="{{ route('admin.prompts.edit') }}" class="{{ request()->routeIs('admin.prompts.*') ? 'active' : '' }}">{{ __('admin.nav.prompts') }}</a>
    <a href="{{ route('admin.readings.index') }}" class="{{ request()->routeIs('admin.readings.*') ? 'active' : '' }}">{{ __('admin.nav.readings') }}</a>
    <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">{{ __('admin.nav.users') }}</a>
    <a href="{{ route('admin.templates.index') }}" class="{{ request()->routeIs('admin.templates.*') ? 'active' : '' }}">{{ __('admin.nav.templates') }}</a>
</nav>
