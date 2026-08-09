<nav class="admin-nav">
    <a href="{{ route('admin.ephemeris.index') }}" class="{{ request()->routeIs('admin.ephemeris.*') ? 'active' : '' }}">{{ __('admin.nav.ephemeris') }}</a>
    <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">{{ __('admin.nav.users') }}</a>
    <a href="{{ route('admin.templates.index') }}" class="{{ request()->routeIs('admin.templates.*') ? 'active' : '' }}">{{ __('admin.nav.templates') }}</a>
</nav>
