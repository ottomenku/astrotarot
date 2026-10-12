<nav class="admin-nav">
    <a href="{{ route('admin.ephemeris.index') }}" class="{{ request()->routeIs('admin.ephemeris.*') ? 'active' : '' }}">{{ __('admin.nav.ephemeris') }}</a>
    <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">{{ __('admin.nav.settings') }}</a>
    <a href="{{ route('admin.prompts.edit') }}" class="{{ request()->routeIs('admin.prompts.*') ? 'active' : '' }}">{{ __('admin.nav.prompts') }}</a>
    <a href="{{ route('admin.legal.edit') }}" class="{{ request()->routeIs('admin.legal.*') ? 'active' : '' }}">{{ __('admin.nav.legal') }}</a>
    <a href="{{ route('admin.packages.index') }}" class="{{ request()->routeIs('admin.packages.*') ? 'active' : '' }}">{{ __('admin.nav.packages') }}</a>
    <a href="{{ route('admin.games.index') }}" class="{{ request()->routeIs('admin.games.*') ? 'active' : '' }}">{{ __('admin.nav.games') }}</a>
    <a href="{{ route('admin.packs.index') }}" class="{{ request()->routeIs('admin.packs.*') ? 'active' : '' }}">{{ __('admin.nav.packs') }}</a>
    <a href="{{ route('admin.meaning-groups.index') }}" class="{{ request()->routeIs('admin.meaning-groups.*') ? 'active' : '' }}">{{ __('admin.nav.meaning_groups') }}</a>
    <a href="{{ route('admin.blog.index') }}" class="{{ request()->routeIs('admin.blog.*') ? 'active' : '' }}">{{ __('admin.nav.blog') }}</a>
    <a href="{{ route('admin.feature-access.edit') }}" class="{{ request()->routeIs('admin.feature-access.*') ? 'active' : '' }}">{{ __('admin.nav.feature_access') }}</a>
    <a href="{{ route('admin.readings.index') }}" class="{{ request()->routeIs('admin.readings.*') ? 'active' : '' }}">{{ __('admin.nav.readings') }}</a>
    <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">{{ __('admin.nav.users') }}</a>
    <a href="{{ route('admin.deletion-requests.index') }}" class="{{ request()->routeIs('admin.deletion-requests.*') ? 'active' : '' }}">{{ __('admin.nav.deletion_requests') }}</a>
    <a href="{{ route('admin.templates.index') }}" class="{{ request()->routeIs('admin.templates.*') ? 'active' : '' }}">{{ __('admin.nav.templates') }}</a>
</nav>
