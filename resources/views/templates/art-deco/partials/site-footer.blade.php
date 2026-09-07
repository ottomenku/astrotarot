<footer class="site-footer">
    <nav class="site-footer-nav" aria-label="{{ __('messages.footer_nav') }}">
        <a href="{{ route('blog.index') }}" @class(['is-current' => request()->routeIs('blog.*')])>{{ __('messages.footer_blog') }}</a>
        <a href="{{ route('legal.contact') }}" @class(['is-current' => request()->routeIs('legal.contact')])>{{ __('messages.footer_contact') }}</a>
        <a href="{{ route('legal.terms') }}" @class(['is-current' => request()->routeIs('legal.terms')])>{{ __('messages.footer_terms') }}</a>
        <a href="{{ route('legal.privacy') }}" @class(['is-current' => request()->routeIs('legal.privacy')])>{{ __('messages.footer_privacy') }}</a>
    </nav>
    <p class="site-footer-mark"><span>✦</span> AstroTarot <i>·</i> Égi Orákulum + Tarot <span>✦</span></p>
</footer>
