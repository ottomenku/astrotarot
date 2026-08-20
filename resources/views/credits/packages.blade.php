<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.credits_packages_title') }} · AstroTarot</title>
    <link rel="stylesheet" href="{{ $assetBase }}/app.css?v={{ filemtime(public_path('templates/art-deco/app.css')) }}">
</head>
<body>
<main class="app-shell credits-page" id="credits-app"
    data-promo-url="{{ route('credits.promo') }}"
    data-csrf="{{ csrf_token() }}"
    data-share-url="{{ $shareUrl }}"
    data-locale="{{ $locale }}"
>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="{{ route('home') }}" aria-label="AstroTarot kezdőlap">
                <span class="brand-mark" aria-hidden="true"><span>✦</span></span>
                <span class="brand-name">Astro<span>Tarot</span></span>
            </a>
            <div class="header-actions">
                @if ($canViewHistory)
                    <a class="credits-back" href="{{ route('readings.history') }}">{{ __('messages.history_menu') }}</a>
                @endif
                <a class="credits-back" href="{{ route('home') }}">{{ __('messages.credits_back_home') }}</a>
            </div>
        </div>
    </header>

    <section class="credits-panel">
        <p class="credits-kicker">{{ __('messages.credits_label') }}</p>
        <h1>{{ __('messages.credits_packages_title') }}</h1>
        <p class="credits-lead">{{ __('messages.credits_packages_lead') }}</p>

        <p class="credits-balance-line">
            {{ __('messages.credits_your_balance') }}:
            <strong>{{ __('messages.credits_balance', ['count' => $balance]) }}</strong>
            · {{ __('messages.plan_current') }}:
            <strong>{{ strtoupper($currentPlan) }}</strong>
        </p>

        @if (session('status'))
            <div class="credits-flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="credits-error">{{ $errors->first() }}</div>
        @endif

        <h2 class="credits-section-title">{{ __('messages.plan_section_title') }}</h2>
        <div class="credits-plans">
            @foreach ($plans as $plan)
                @php
                    $label = $locale === 'en' ? $plan['label_en'] : $plan['label_hu'];
                    $features = $locale === 'en' ? $plan['features_en'] : $plan['features_hu'];
                    $isCurrent = $currentPlan === $plan['key'];
                    $price = $plan['price_huf'];
                @endphp
                <article class="credits-plan {{ $isCurrent ? 'is-current' : '' }}" data-plan-card="{{ $plan['key'] }}">
                    <p class="credits-plan-kicker">{{ $label }}</p>
                    <h3>
                        @if ($price === null)
                            {{ __('messages.plan_price_free') }}
                        @else
                            {{ number_format($price, 0, ',', ' ') }} Ft<span>/{{ __('messages.plan_per_month') }}</span>
                        @endif
                    </h3>
                    <ul>
                        @foreach ($features as $feature)
                            <li>{{ $feature }}</li>
                        @endforeach
                    </ul>
                    @if ($isCurrent)
                        <p class="credits-plan-badge">{{ __('messages.plan_active') }}</p>
                    @elseif ($plan['key'] === 'free')
                        <button type="button" class="credits-plan-btn" disabled>{{ __('messages.plan_default') }}</button>
                    @else
                        <button
                            type="button"
                            class="credits-plan-btn"
                            data-plan-select="{{ $plan['key'] }}"
                            data-plan-label="{{ $label }}"
                        >{{ __('messages.plan_choose') }}</button>
                    @endif
                </article>
            @endforeach
        </div>

        @if ($hasAstroMotto)
            <p class="credits-astromotto">
                {{ __('messages.plan_astromotto_access') }}
                <a href="{{ $astroMottoUrl }}" target="_blank" rel="noopener noreferrer">AstroMotto</a>
            </p>
        @endif

        <h2 class="credits-section-title">{{ __('messages.credits_request_section') }}</h2>
        <div class="credits-packages">
            @foreach ($creditPacks as $package)
                @php
                    $label = $locale === 'en' ? $package['label_en'] : $package['label_hu'];
                @endphp
                <form class="credits-package" method="POST" action="{{ route('credits.request') }}">
                    @csrf
                    <input type="hidden" name="package_key" value="{{ $package['key'] }}">
                    <div>
                        <h2>{{ $label }}</h2>
                        <p>{{ __('messages.credits_balance', ['count' => $package['credits']]) }}</p>
                    </div>
                    <label class="credits-note">
                        <span>{{ __('messages.credits_note_label') }}</span>
                        <textarea name="note" rows="2" maxlength="500" placeholder="…"></textarea>
                    </label>
                    <button type="submit">{{ __('messages.credits_request') }}</button>
                </form>
            @endforeach
        </div>
    </section>

    <div class="plan-promo-modal" data-plan-promo hidden>
        <div class="plan-promo-backdrop" data-plan-promo-close></div>
        <div class="plan-promo-panel" role="dialog" aria-modal="true" aria-labelledby="plan-promo-title">
            <button type="button" class="plan-promo-close" data-plan-promo-close aria-label="Bezárás">×</button>
            <p class="plan-promo-kicker">AstroTarot</p>
            <h2 id="plan-promo-title">{{ __('messages.plan_promo_title') }}</h2>
            <p class="plan-promo-body" data-plan-promo-body>{{ __('messages.plan_promo_body') }}</p>
            <a
                class="plan-promo-share"
                data-plan-promo-share
                href="#"
                target="_blank"
                rel="noopener noreferrer"
            >{{ __('messages.plan_promo_share') }}</a>
            <p class="plan-promo-hint">{{ __('messages.plan_promo_hint') }}</p>
        </div>
    </div>

    <footer><span>✦</span> AstroTarot <i>·</i> Égi Orákulum + Tarot <span>✦</span></footer>
</main>
<script>
(() => {
  const root = document.getElementById('credits-app');
  if (!root) return;
  const promoUrl = root.dataset.promoUrl;
  const csrf = root.dataset.csrf;
  const shareUrl = root.dataset.shareUrl || window.location.origin + '/';
  const modal = root.querySelector('[data-plan-promo]');
  const body = root.querySelector('[data-plan-promo-body]');
  const shareBtn = root.querySelector('[data-plan-promo-share]');
  let selectedPlan = null;

  function openPromo(plan, label) {
    selectedPlan = plan;
    if (body) {
      body.textContent = body.dataset.template
        || body.textContent;
    }
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closePromo() {
    modal.hidden = true;
    document.body.style.overflow = '';
    selectedPlan = null;
  }

  async function activateAfterShare() {
    if (!selectedPlan || !promoUrl) return;
    const plan = selectedPlan;
    try {
      const response = await fetch(promoUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ plan }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        alert(data.error || 'Error');
        return;
      }
      window.location.reload();
    } catch (e) {
      alert('Error');
    }
  }

  root.querySelectorAll('[data-plan-select]').forEach((button) => {
    button.addEventListener('click', () => {
      openPromo(button.dataset.planSelect, button.dataset.planLabel);
    });
  });

  root.querySelectorAll('[data-plan-promo-close]').forEach((node) => {
    node.addEventListener('click', closePromo);
  });

  shareBtn?.addEventListener('click', (event) => {
    event.preventDefault();
    if (!selectedPlan) return;
    const fb = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl);
    window.open(fb, '_blank', 'noopener,noreferrer,width=640,height=720');
    setTimeout(activateAfterShare, 1200);
  });

  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closePromo();
  });
})();
</script>
</body>
</html>
