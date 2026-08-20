(() => {
  const root = document.getElementById('astrotarot-app');
  if (!root) return;

  const assetBase = root.dataset.assetBase.replace(/\/$/, '');
  const localeSwitchUrl = root.dataset.localeSwitchUrl;
  const readingUrl = root.dataset.readingUrl;
  const exportPdfUrl = root.dataset.exportPdfUrl || '';
  const exportEmailUrl = root.dataset.exportEmailUrl || '';
  const shareCreateUrl = root.dataset.shareUrl || '';
  const userEmail = root.dataset.userEmail || '';
  const minQuestionChars = Math.max(1, Number.parseInt(root.dataset.minQuestionChars || '10', 10) || 10);
  const llmEnabled = root.dataset.llmEnabled !== '0';
  const isAuthenticated = root.dataset.authenticated === '1';
  const openAuthOnLoad = root.dataset.openAuth === '1';
  let creditsBalance = Number.parseInt(root.dataset.credits || '0', 10) || 0;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const initialLocale = root.dataset.locale === 'en' ? 'en' : 'hu';
  const drawProfile = (() => {
    try {
      return JSON.parse(root.dataset.drawProfile || '{}');
    } catch (error) {
      return {};
    }
  })();

  const copy = {
    hu: {
      navLabel: 'Navigáció megnyitása',
      selected: 'kiválasztva',
      title: 'Tedd fel a kérdésed, válassz Égi mestert, majd tarot figurát!',
      placeholder: 'Mi az, amire most igazán szeretnél választ kapni?',
      hint: 'Először az Égi Orákulumból, aztán a Tarot pakliból választasz.',
      reading: 'Választott lapjaid',
      waiting: 'Rád vár',
      reset: 'Új húzás',
      incomplete: 'Válassz 2 lapot és írd be a kérdésed',
      messageTitle: 'A lapok üzenete',
      messageKicker: 'AstroTarot értelmezés',
      messageLoading: 'Az LLM gondolkodik…',
      messageNeedQuestion: 'Írj legalább {n} karakteres kérdést, mielőtt lapot választasz.',
      messageError: 'Az értelmezés most nem sikerült. Próbáld újra egy pillanat múlva.',
      messageLlmDisabled: 'Az LLM értelmezés jelenleg ki van kapcsolva.',
      deckOracle: 'Égi Orákulum',
      deckTarot: 'Tarot pakli',
      slotTitles: ['Választott Égi mestered', 'Választott Figurád'],
      polarityLight: 'Light',
      polarityShadow: 'Shadow',
      chartAtQuestion: 'A kérdezés idején',
      house: 'ház',
      retrograde: 'retrográd',
      direct: 'direkt',
      aspects: 'Fényszögek',
      noAspects: 'Nincs jelentős fényszög.',
      planetsInSign: 'Ebben a jegyben most',
      ruler: 'Uralkodó',
      noChartData: 'A részletes égi adatok most nem elérhetők.',
      followupLabel: 'Pontosítást kérek…',
      followupPlaceholder: 'Pontosítást kérek…',
      followupSend: 'Elküld',
      followupExpand: 'Bővebb kifejtést kérek',
      followupNeedText: 'Írd be a pontosítást, mielőtt elküldöd.',
      guestPrompt: 'Kattints ide, hogy személyre szabott válaszokat kaphass!',
      messageNeedAuth: 'A személyre szabott válaszhoz jelentkezz be vagy regisztrálj.',
      messageNeedCredits: 'Nincs elég kredited. A főmenü „Kreditek / Csomagok” pontjában kérhetsz újabbakat.',
      shareLabel: 'Mentés / megosztás',
      sharePdf: 'PDF letöltés',
      shareEmail: 'E-mail',
      shareFacebook: 'Facebook',
      shareEmailPrompt: 'Milyen e-mail címre küldjük az olvasatot?',
      shareEmailSent: 'Az olvasatot elküldtük e-mailben.',
      shareBusy: 'Készül…',
      shareError: 'A megosztás most nem sikerült. Próbáld újra.',
      answerInitial: 'Olvasat',
      answerClarify: 'Pontosítás',
      answerExpand: 'Bővebb kifejtés',
    },
    en: {
      navLabel: 'Open navigation',
      selected: 'selected',
      title: 'Ask your question, choose a Celestial Master, then a tarot figure!',
      placeholder: 'What would you truly like clarity about right now?',
      hint: 'First pick from the Celestial Oracle, then from the Tarot deck.',
      reading: 'Your chosen cards',
      waiting: 'Waiting for you',
      reset: 'New reading',
      incomplete: 'Choose 2 cards and enter your question',
      messageTitle: 'The message of your cards',
      messageKicker: 'AstroTarot reading',
      messageLoading: 'The LLM is thinking…',
      messageNeedQuestion: 'Write a question of at least {n} characters before choosing a card.',
      messageError: 'The reading could not be created. Please try again in a moment.',
      messageLlmDisabled: 'LLM readings are currently turned off.',
      deckOracle: 'Celestial Oracle',
      deckTarot: 'Tarot deck',
      slotTitles: ['Your Celestial Master', 'Your Chosen Figure'],
      polarityLight: 'Light',
      polarityShadow: 'Shadow',
      chartAtQuestion: 'At the time of the question',
      house: 'house',
      retrograde: 'retrograde',
      direct: 'direct',
      aspects: 'Aspects',
      noAspects: 'No major aspects.',
      planetsInSign: 'Currently in this sign',
      ruler: 'Ruler',
      noChartData: 'Detailed sky data is unavailable right now.',
      followupLabel: 'I need a clarification…',
      followupPlaceholder: 'I need a clarification…',
      followupSend: 'Send',
      followupExpand: 'Ask for a deeper reading',
      followupNeedText: 'Write a clarification before sending.',
      guestPrompt: 'Click here to get personalized answers!',
      messageNeedAuth: 'Log in or sign up to receive personalized answers.',
      messageNeedCredits: 'You do not have enough credits. Request more under “Credits / Packages” in the main menu.',
      shareLabel: 'Save / share',
      sharePdf: 'Download PDF',
      shareEmail: 'Email',
      shareFacebook: 'Facebook',
      shareEmailPrompt: 'Which email should receive the reading?',
      shareEmailSent: 'The reading was sent by email.',
      shareBusy: 'Working…',
      shareError: 'Sharing failed. Please try again.',
      answerInitial: 'Reading',
      answerClarify: 'Clarification',
      answerExpand: 'Deeper reading',
    },
  };

  const SIGN_LABELS = {
    hu: {
      aries: 'Kos', taurus: 'Bika', gemini: 'Ikrek', cancer: 'Rák',
      leo: 'Oroszlán', virgo: 'Szűz', libra: 'Mérleg', scorpio: 'Skorpió',
      sagittarius: 'Nyilas', capricorn: 'Bak', aquarius: 'Vízöntő', pisces: 'Halak',
    },
    en: {
      aries: 'Aries', taurus: 'Taurus', gemini: 'Gemini', cancer: 'Cancer',
      leo: 'Leo', virgo: 'Virgo', libra: 'Libra', scorpio: 'Scorpio',
      sagittarius: 'Sagittarius', capricorn: 'Capricorn', aquarius: 'Aquarius', pisces: 'Pisces',
    },
  };

  const SIGN_RULERS = {
    aries: 'mars', taurus: 'venus', gemini: 'mercury', cancer: 'moon',
    leo: 'sun', virgo: 'mercury', libra: 'venus', scorpio: 'pluto',
    sagittarius: 'jupiter', capricorn: 'saturn', aquarius: 'uranus', pisces: 'neptune',
  };

  const ASPECT_LABELS = {
    hu: {
      együttállás: 'együttállás', szextil: 'szextil', kvadrát: 'kvadrát',
      trigon: 'trigon', kvinkunx: 'kvinkunx', oppozíció: 'oppozíció',
    },
    en: {
      együttállás: 'conjunction', szextil: 'sextile', kvadrát: 'square',
      trigon: 'trine', kvinkunx: 'quincunx', oppozíció: 'opposition',
    },
  };

  const romans = ['I', 'II'];
  const SLOT_COUNT = 2;

  const els = {
    menuButton: root.querySelector('[data-menu-open]'),
    menuClose: root.querySelector('[data-menu-close]'),
    menuBackdrop: root.querySelector('[data-menu-backdrop]'),
    sideMenu: root.querySelector('[data-side-menu]'),
    language: root.querySelector('[data-language]'),
    title: root.querySelector('[data-title]'),
    question: root.querySelector('[data-question]'),
    counter: root.querySelector('[data-counter]'),
    hint: root.querySelector('[data-hint]'),
    deckLabel: root.querySelector('[data-deck-label]'),
    pickStage: root.querySelector('[data-pick-stage]'),
    resultHeader: root.querySelector('[data-result-header]'),
    askedQuestion: root.querySelector('[data-asked-question]'),
    reset: root.querySelector('[data-reset]'),
    slider: root.querySelector('[data-deck-slider]'),
    chosen: root.querySelector('[data-chosen-grid]'),
    readingResult: root.querySelector('[data-reading-result]'),
    readingKicker: root.querySelector('[data-reading-kicker]'),
    readingTitle: root.querySelector('[data-reading-title]'),
    readingBody: root.querySelector('[data-reading-body]'),
    readingToast: root.querySelector('[data-reading-toast]'),
    readingFollowup: root.querySelector('[data-reading-followup]'),
    readingFollowupLabel: root.querySelector('[data-reading-followup-label]'),
    readingFollowupInput: root.querySelector('[data-reading-followup-input]'),
    readingFollowupSend: root.querySelector('[data-reading-followup-send]'),
    readingFollowupExpand: root.querySelector('[data-reading-followup-expand]'),
    readingShare: root.querySelector('[data-reading-share]'),
    readingShareLabel: root.querySelector('[data-reading-share-label]'),
    sharePdf: root.querySelector('[data-share-pdf]'),
    shareEmail: root.querySelector('[data-share-email]'),
    shareFacebook: root.querySelector('[data-share-facebook]'),
    authModal: root.querySelector('[data-auth-modal]'),
    creditsWelcome: root.querySelector('[data-credits-welcome]'),
  };

  let language = initialLocale;
  /** @type {{id:string, figures:any[], cardBack:string, backgrounds?: any}|null} */
  let oracleDeck = null;
  /** @type {{id:string, figures:any[], cardBack:string}|null} */
  let tarotDeck = null;
  /** @type {'oracle'|'tarot'} */
  let activeDeck = 'oracle';
  /** @type {{deck:string, backIndex:number, figureId:string, backgroundId:string, variant:string}[]} */
  let selected = [];
  let readingRequest = null;
  let lastReadingKey = '';
  let questionTimer = null;
  let renderedDeckKey = '';
  /** @type {{question:string, cards:any[], reading:string, answers: {mode:string, label:string, text:string}[]}|null} */
  let activeReading = null;
  let lockedQuestion = '';
  let shareBusy = false;

  const pick = (items) => items[Math.floor(Math.random() * items.length)];

  function t() {
    return copy[language];
  }

  function needQuestionMessage() {
    return t().messageNeedQuestion.replace('{n}', String(minQuestionChars));
  }

  function randomVariant() {
    return Math.random() < 0.5 ? 'shadow' : 'positive';
  }

  function currentDeck() {
    return activeDeck === 'tarot' ? tarotDeck : oracleDeck;
  }

  function figureMeta(deckKey, figureId) {
    const deck = deckKey === 'tarot' ? tarotDeck : oracleDeck;
    return deck?.figures?.find((figure) => figure.id === figureId) || null;
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function chartObjects() {
    return Array.isArray(drawProfile?.chart?.objects) ? drawProfile.chart.objects : [];
  }

  function chartAspects() {
    return Array.isArray(drawProfile?.chart?.aspects) ? drawProfile.chart.aspects : [];
  }

  function findChartObject(id) {
    return chartObjects().find((object) => object.id === id) || null;
  }

  function localizeSign(signKey, fallback = '') {
    if (!signKey) return fallback;
    return SIGN_LABELS[language]?.[signKey] || SIGN_LABELS.en[signKey] || fallback || signKey;
  }

  function localizeObjectName(object, fallback = '') {
    if (!object) return fallback;
    const oracle = oracleDeck?.figures?.find((figure) => figure.id === object.id);
    if (oracle) return oracle[language] || oracle.en || object.name || fallback;
    return object.name || fallback;
  }

  function localizeAspectType(type) {
    return ASPECT_LABELS[language]?.[type] || ASPECT_LABELS.en[type] || type;
  }

  function motionLabel(retrograde) {
    return retrograde ? t().retrograde : t().direct;
  }

  function objectPositionLine(object) {
    const text = t();
    const sign = localizeSign(object.sign, object.sign_label || '');
    const degree = Number.isFinite(object.degree) ? `${object.degree}°` : '';
    const house = object.house ? `${object.house}. ${text.house}` : '';
    const motion = motionLabel(Boolean(object.retrograde));
    return [sign && degree ? `${sign} ${degree}` : sign, house, motion].filter(Boolean).join(' · ');
  }

  function aspectsForObject(objectId, limit = 5) {
    return chartAspects()
      .filter((aspect) => aspect.a_id === objectId || aspect.b_id === objectId)
      .sort((a, b) => (a.orb ?? 99) - (b.orb ?? 99))
      .slice(0, limit)
      .map((aspect) => {
        const otherId = aspect.a_id === objectId ? aspect.b_id : aspect.a_id;
        const other = findChartObject(otherId);
        const otherName = localizeObjectName(other, aspect.a_id === objectId ? aspect.b : aspect.a);
        const orb = Number.isFinite(aspect.orb) ? `${aspect.orb.toFixed(1)}°` : '';
        return `${otherName} ${localizeAspectType(aspect.type)}${orb ? ` (${orb})` : ''}`;
      });
  }

  function oracleCaptionHtml(figureId) {
    const text = t();
    const meta = figureMeta('oracle', figureId);
    const title = meta ? (meta[language] || meta.en || figureId) : figureId;
    const objects = chartObjects();
    const isZodiac = Boolean(meta?.kind === 'zodiac' || SIGN_RULERS[figureId]);

    if (!objects.length) {
      return `
        <h3>${escapeHtml(title)}</h3>
        <p class="astro-detail">${escapeHtml(text.noChartData)}</p>
      `;
    }

    if (!isZodiac) {
      const object = findChartObject(figureId);
      if (!object) {
        return `
          <h3>${escapeHtml(title)}</h3>
          <p class="astro-detail">${escapeHtml(text.noChartData)}</p>
        `;
      }
      const aspects = aspectsForObject(object.id);
      return `
        <h3>${escapeHtml(title)}</h3>
        <p class="astro-heading">${escapeHtml(text.chartAtQuestion)}</p>
        <p class="astro-detail">${escapeHtml(objectPositionLine(object))}</p>
        <p class="astro-heading">${escapeHtml(text.aspects)}</p>
        <p class="astro-detail">${escapeHtml(aspects.length ? aspects.join(' · ') : text.noAspects)}</p>
      `;
    }

    const inSign = objects.filter((object) => object.sign === figureId && object.kind === 'planet');
    const rulerId = SIGN_RULERS[figureId];
    const ruler = rulerId ? findChartObject(rulerId) : null;
    const inSignLine = inSign.length
      ? inSign.map((object) => {
        const name = localizeObjectName(object);
        const house = object.house ? `${object.house}. ${text.house}` : '';
        const motion = motionLabel(Boolean(object.retrograde));
        return [name, house, motion].filter(Boolean).join(' · ');
      }).join('; ')
      : (language === 'hu' ? 'Nincs bolygó ebben a jegyben.' : 'No planets currently in this sign.');

    const rulerBlock = ruler
      ? `<p class="astro-heading">${escapeHtml(text.ruler)}: ${escapeHtml(localizeObjectName(ruler))}</p>
         <p class="astro-detail">${escapeHtml(objectPositionLine(ruler))}</p>
         <p class="astro-heading">${escapeHtml(text.aspects)}</p>
         <p class="astro-detail">${escapeHtml((aspectsForObject(ruler.id).join(' · ')) || text.noAspects)}</p>`
      : '';

    return `
      <h3>${escapeHtml(title)}</h3>
      <p class="astro-heading">${escapeHtml(text.chartAtQuestion)}</p>
      <p class="astro-heading">${escapeHtml(text.planetsInSign)}</p>
      <p class="astro-detail">${escapeHtml(inSignLine)}</p>
      ${rulerBlock}
    `;
  }

  function tarotCaptionHtml(figureId) {
    const meta = figureMeta('tarot', figureId);
    const title = meta ? (meta[language] || meta.en || figureId) : figureId;
    const blurb = meta?.blurb?.[language] || meta?.blurb?.en || meta?.keyword?.[language] || '';
    return `
      <h3>${escapeHtml(title)}</h3>
      ${blurb ? `<p class="figure-blurb">${escapeHtml(blurb)}</p>` : ''}
    `;
  }

  function slotCaptionText(slot, figureId) {
    if (slot === 0) {
      const tmp = document.createElement('div');
      tmp.innerHTML = oracleCaptionHtml(figureId);
      return tmp.textContent.replace(/\s+/g, ' ').trim();
    }
    const meta = figureMeta('tarot', figureId);
    const title = meta ? (meta[language] || meta.en || figureId) : figureId;
    const blurb = meta?.blurb?.[language] || meta?.blurb?.en || '';
    return [title, blurb].filter(Boolean).join(' — ');
  }

  function cardBackSrc(deckKey) {
    const deck = deckKey === 'tarot' ? tarotDeck : oracleDeck;
    const relative = deck?.cardBack || 'card-back/card-back.png';
    const folder = deckKey === 'tarot' ? 'tarot-major' : 'egi-orakulum';
    return `${assetBase}/assets/${folder}/${relative}`;
  }

  function backgroundSrc(variant) {
    const tone = variant === 'shadow' ? 'shadow' : 'light';
    return `${assetBase}/assets/egi-orakulum/backgrounds/shared-${tone}.png`;
  }

  function figureSrc(deckKey, figureId) {
    const folder = deckKey === 'tarot' ? 'tarot-major' : 'egi-orakulum';
    if (deckKey === 'tarot') {
      if (figureId === 'significator') {
        return `${assetBase}/assets/${folder}/figures/significator.png`;
      }
      if (/^(wands|cups|swords|pentacles)-/.test(figureId)) {
        return `${assetBase}/assets/${folder}/figures/minors/${figureId}.png`;
      }
    }
    return `${assetBase}/assets/${folder}/figures/${figureId}.png`;
  }

  function setMenuOpen(open) {
    els.menuBackdrop?.classList.toggle('is-open', open);
    els.sideMenu?.classList.toggle('is-open', open);
    els.sideMenu?.setAttribute('aria-hidden', open ? 'false' : 'true');
    els.menuButton?.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.style.overflow = open ? 'hidden' : '';
  }

  function setAuthModalOpen(open) {
    if (!els.authModal) return;
    els.authModal.hidden = !open;
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
      setMenuOpen(false);
    }
  }

  function switchAuthTab(tab) {
    if (!els.authModal) return;
    const next = tab === 'register' ? 'register' : 'login';
    els.authModal.querySelectorAll('[data-auth-tab]').forEach((button) => {
      const active = button.dataset.authTab === next;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    els.authModal.querySelectorAll('[data-auth-panel]').forEach((panel) => {
      const active = panel.dataset.authPanel === next;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
  }

  function requireAuth(event) {
    if (isAuthenticated) return false;
    event?.preventDefault?.();
    event?.stopPropagation?.();
    els.question?.blur();
    setAuthModalOpen(true);
    return true;
  }

  function applyCopy() {
    const text = t();
    if (els.menuButton) els.menuButton.setAttribute('aria-label', text.navLabel);
    if (els.title) els.title.textContent = text.title;
    if (els.question) {
      els.question.placeholder = isAuthenticated ? text.placeholder : text.guestPrompt;
    }
    if (els.hint) els.hint.innerHTML = `<span>☾</span>${text.hint}`;
    if (els.reset) els.reset.textContent = `↺ ${text.reset}`;
    if (els.readingKicker) els.readingKicker.textContent = text.messageKicker;
    if (els.readingTitle) els.readingTitle.textContent = text.messageTitle;
    if (els.readingFollowupLabel) els.readingFollowupLabel.textContent = text.followupLabel;
    if (els.readingFollowupInput) els.readingFollowupInput.placeholder = text.followupPlaceholder;
    if (els.readingFollowupSend) els.readingFollowupSend.textContent = text.followupSend;
    if (els.readingFollowupExpand) els.readingFollowupExpand.textContent = text.followupExpand;
    if (els.readingShareLabel) els.readingShareLabel.textContent = text.shareLabel;
    if (els.sharePdf) els.sharePdf.textContent = text.sharePdf;
    if (els.shareEmail) els.shareEmail.textContent = text.shareEmail;
    if (els.shareFacebook) els.shareFacebook.textContent = text.shareFacebook;
    if (els.counter) els.counter.innerHTML = `<b>${selected.length}</b>/${SLOT_COUNT} ${text.selected}`;
    if (els.deckLabel) {
      els.deckLabel.textContent = activeDeck === 'tarot' ? text.deckTarot : text.deckOracle;
    }
    if (els.askedQuestion && lockedQuestion) {
      els.askedQuestion.textContent = lockedQuestion;
    }
    renderChosen();
    syncCompleteView();
  }

  function polarityLabel(variant) {
    const text = t();
    return variant === 'shadow' ? text.polarityShadow : text.polarityLight;
  }

  function polarityClass(variant) {
    return variant === 'shadow' ? 'is-shadow' : 'is-light';
  }

  function syncDeckSelection() {
    [...els.slider.children].forEach((button) => {
      const index = Number.parseInt(button.dataset.backIndex || '-1', 10);
      const chosenIndex = selected.findIndex(
        (item) => item.deck === activeDeck && item.backIndex === index
      );
      const isSelected = chosenIndex >= 0;
      button.classList.toggle('is-selected', isSelected);
      button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');

      let badge = button.querySelector('.selected-badge');
      if (isSelected) {
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'selected-badge';
          button.appendChild(badge);
        }
        badge.textContent = String(chosenIndex + 1);
      } else if (badge) {
        badge.remove();
      }
    });
  }

  function renderDeck(force = false) {
    if (!els.slider) return;
    const deck = currentDeck();
    if (!deck) return;

    const deckKey = `${activeDeck}:${deck.figures.length}`;
    if (!force && renderedDeckKey === deckKey && els.slider.children.length === deck.figures.length) {
      syncDeckSelection();
      return;
    }

    const backSrc = cardBackSrc(activeDeck);
    els.slider.innerHTML = '';
    renderedDeckKey = deckKey;

    for (let index = 0; index < deck.figures.length; index += 1) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'deck-card';
      button.setAttribute('role', 'listitem');
      button.setAttribute('aria-pressed', 'false');
      button.setAttribute('aria-label', `${String(index + 1).padStart(2, '0')}. kártya`);
      button.dataset.backIndex = String(index);
      button.innerHTML = `
        <img src="${backSrc}" alt="" draggable="false" decoding="async">
        <span class="card-number">${String(index + 1).padStart(2, '0')}</span>
      `;
      button.addEventListener('click', () => toggleBack(index));
      els.slider.appendChild(button);
    }

    els.slider.scrollLeft = 0;
    syncDeckSelection();
  }

  function renderChosen() {
    if (!els.chosen) return;
    const text = t();
    els.chosen.innerHTML = '';

    for (let slot = 0; slot < SLOT_COUNT; slot += 1) {
      const draw = selected[slot];
      const article = document.createElement('article');
      article.className = `chosen-card${draw ? ' has-card' : ' is-placeholder'}`;
      const deckKey = slot === 0 ? 'oracle' : 'tarot';

      if (!draw) {
        article.innerHTML = `
          <h3 class="slot-title">${text.slotTitles[slot]}</h3>
          <p class="polarity-label is-light">—</p>
          <div class="card-art placeholder-art">
            <img class="card-back" src="${cardBackSrc(deckKey)}" alt="" decoding="async">
            <span class="roman">${romans[slot]}</span>
          </div>
          <div class="card-caption">
            <h3>${text.waiting}</h3>
          </div>
        `;
      } else {
        const caption = slot === 0
          ? oracleCaptionHtml(draw.figureId)
          : tarotCaptionHtml(draw.figureId);
        article.innerHTML = `
          <h3 class="slot-title">${text.slotTitles[slot]}</h3>
          <p class="polarity-label ${polarityClass(draw.variant)}">${polarityLabel(draw.variant)}</p>
          <div class="card-art">
            <img class="card-background" src="${backgroundSrc(draw.variant)}" alt="" decoding="async">
            <img class="card-figure" src="${figureSrc(draw.deck, draw.figureId)}" alt="" decoding="async">
            <span class="roman">${romans[slot]}</span>
          </div>
          <div class="card-caption">
            ${caption}
          </div>
        `;
      }

      els.chosen.appendChild(article);
    }

    syncCompleteView();
  }

  function syncCompleteView() {
    const complete = selected.length === SLOT_COUNT;
    root.classList.toggle('is-complete', complete);

    if (complete && !lockedQuestion) {
      lockedQuestion = questionText();
    }

    if (els.resultHeader) {
      els.resultHeader.hidden = !complete;
    }
    if (els.askedQuestion) {
      els.askedQuestion.textContent = lockedQuestion || questionText();
      els.askedQuestion.hidden = !(lockedQuestion || questionText());
    }
    if (els.reset) {
      els.reset.hidden = !complete;
    }

    if (!complete) {
      clearReadingResult();
    }
  }

  function toggleBack(backIndex) {
    if (requireAuth()) return;
    if (selected.length >= SLOT_COUNT) return;
    if (selected.some((item) => item.deck === activeDeck && item.backIndex === backIndex)) {
      return;
    }

    if (selected.length === 0 && questionText().length < minQuestionChars) {
      els.question?.focus();
      showToast(needQuestionMessage(), false);
      return;
    }

    const deckKey = activeDeck;
    const deck = currentDeck();
    if (!deck) return;

    const used = selected.filter((item) => item.deck === deckKey).map((item) => item.figureId);
    const available = deck.figures.filter((figure) => !used.includes(figure.id));
    const pool = available.length ? available : deck.figures;
    const figure = pick(pool);

    selected.push({
      deck: deckKey,
      backIndex,
      figureId: figure.id,
      backgroundId: 'shared',
      variant: randomVariant(),
    });

    if (els.counter) els.counter.innerHTML = `<b>${selected.length}</b>/${SLOT_COUNT} ${t().selected}`;

    if (selected.length === 1) {
      activeDeck = 'tarot';
      applyCopy();
      renderDeck(true);
    } else {
      renderDeck();
    }

    renderChosen();
    maybeRequestReading();
  }

  function reset() {
    selected = [];
    activeDeck = 'oracle';
    lastReadingKey = '';
    activeReading = null;
    lockedQuestion = '';
    if (readingRequest) {
      readingRequest.abort();
      readingRequest = null;
    }
    clearReadingResult();
    hideToast();
    if (els.question) {
      els.question.value = '';
      if (els.question.nextElementSibling) {
        els.question.nextElementSibling.textContent = '0/280';
      }
    }
    applyCopy();
    renderDeck(true);
    els.slider?.scrollTo({ left: 0, behavior: 'smooth' });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function questionText() {
    if (lockedQuestion) return lockedQuestion;
    return (els.question?.value || '').trim();
  }

  function readingPayloadKey() {
    return JSON.stringify({
      q: questionText(),
      cards: selected.map((item) => [item.deck, item.backIndex, item.figureId, item.variant]),
      lang: language,
    });
  }

  function showToast(message, loading = true) {
    if (!els.readingToast) return;
    els.readingToast.hidden = false;
    els.readingToast.classList.toggle('is-loading', loading);
    els.readingToast.classList.toggle('is-error', !loading && Boolean(message));
    const textNode = els.readingToast.querySelector('[data-reading-toast-text]');
    if (textNode) textNode.textContent = message;
  }

  function hideToast() {
    if (!els.readingToast) return;
    els.readingToast.hidden = true;
    els.readingToast.classList.remove('is-loading', 'is-error');
  }

  function answerLabel(mode) {
    const text = t();
    if (mode === 'clarify') return text.answerClarify;
    if (mode === 'expand') return text.answerExpand;
    return text.answerInitial;
  }

  function showShareActions(show) {
    if (!els.readingShare) return;
    els.readingShare.hidden = !show;
  }

  function setShareBusy(busy) {
    shareBusy = busy;
    [els.sharePdf, els.shareEmail, els.shareFacebook].forEach((button) => {
      if (!button) return;
      button.disabled = busy;
    });
  }

  function exportPayload() {
    if (!activeReading?.answers?.length) return null;
    return {
      question: activeReading.question || questionText(),
      locale: language,
      cards: activeReading.cards || buildCardsPayload(),
      answers: activeReading.answers,
    };
  }

  async function postJson(url, body) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.error || t().shareError);
    }
    return data;
  }

  async function downloadPdf() {
    const payload = exportPayload();
    if (!payload || !exportPdfUrl || shareBusy) return;
    setShareBusy(true);
    showToast(t().shareBusy, true);
    try {
      const response = await fetch(exportPdfUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/pdf',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      });
      if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(data.error || t().shareError);
      }
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'astrotarot-olvasat.pdf';
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      hideToast();
    } catch (error) {
      showToast(error?.message || t().shareError, false);
    } finally {
      setShareBusy(false);
    }
  }

  async function sendEmail() {
    const payload = exportPayload();
    if (!payload || !exportEmailUrl || shareBusy) return;
    const email = window.prompt(t().shareEmailPrompt, userEmail || '');
    if (email === null) return;
    const trimmed = email.trim();
    if (!trimmed) {
      showToast(t().shareError, false);
      return;
    }
    setShareBusy(true);
    showToast(t().shareBusy, true);
    try {
      const data = await postJson(exportEmailUrl, { ...payload, email: trimmed });
      showToast(data.message || t().shareEmailSent, false);
    } catch (error) {
      showToast(error?.message || t().shareError, false);
    } finally {
      setShareBusy(false);
    }
  }

  async function shareFacebook() {
    const payload = exportPayload();
    if (!payload || !shareCreateUrl || shareBusy) return;
    setShareBusy(true);
    showToast(t().shareBusy, true);
    try {
      const data = await postJson(shareCreateUrl, payload);
      hideToast();
      const fbUrl = data.facebook_url || (
        data.url ? `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(data.url)}` : ''
      );
      if (!fbUrl) throw new Error(t().shareError);
      window.open(fbUrl, '_blank', 'noopener,noreferrer,width=640,height=720');
    } catch (error) {
      showToast(error?.message || t().shareError, false);
    } finally {
      setShareBusy(false);
    }
  }

  function showReadingResult({ body, error = false, showFollowup = false }) {
    if (!els.readingResult || !els.readingBody) return;
    const text = t();
    hideToast();
    if (els.readingKicker) els.readingKicker.textContent = text.messageKicker;
    if (els.readingTitle) els.readingTitle.textContent = text.messageTitle;
    els.readingBody.classList.remove('is-loading');
    els.readingBody.classList.toggle('is-error', error);
    els.readingBody.textContent = body;
    els.readingResult.hidden = false;
    if (els.readingFollowup) {
      els.readingFollowup.hidden = !showFollowup || error;
      if (els.readingFollowupInput && showFollowup) {
        els.readingFollowupInput.value = '';
      }
      setFollowupBusy(false);
    }
    showShareActions(!error && Boolean(body));
    els.readingResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function clearReadingResult() {
    if (els.readingResult) els.readingResult.hidden = true;
    if (els.readingBody) {
      els.readingBody.textContent = '';
      els.readingBody.classList.remove('is-loading', 'is-error');
    }
    if (els.readingFollowup) els.readingFollowup.hidden = true;
    showShareActions(false);
  }

  function setFollowupBusy(busy) {
    if (els.readingFollowupSend) els.readingFollowupSend.disabled = busy;
    if (els.readingFollowupExpand) els.readingFollowupExpand.disabled = busy;
    if (els.readingFollowupInput) els.readingFollowupInput.disabled = busy;
  }

  function appendReadingText(extra, mode = 'expand') {
    if (!els.readingBody || !extra) return;
    const current = els.readingBody.textContent || '';
    els.readingBody.textContent = current
      ? `${current}\n\n———\n\n${extra}`
      : extra;
    if (activeReading) {
      activeReading.reading = els.readingBody.textContent;
      activeReading.answers = activeReading.answers || [];
      activeReading.answers.push({
        mode,
        label: answerLabel(mode),
        text: extra,
      });
    }
    showShareActions(true);
  }

  function buildCardsPayload() {
    return selected.map((draw, slot) => {
      const meta = figureMeta(draw.deck, draw.figureId);
      const polarity = draw.variant === 'shadow' ? 'shadow' : 'positive';
      return {
        figure_id: draw.figureId,
        figure_name: meta ? meta[language] : draw.figureId,
        keyword: meta?.keyword ? meta.keyword[language] : '',
        blurb: meta?.blurb ? (meta.blurb[language] || meta.blurb.en || '') : '',
        background_id: `${draw.backgroundId}-${draw.variant === 'shadow' ? 'shadow' : 'light'}`,
        background: draw.backgroundId,
        variant: draw.variant,
        polarity,
        deck: draw.deck,
        slot_title: t().slotTitles[slot],
        astro_caption: slotCaptionText(slot, draw.figureId),
      };
    });
  }

  function updateCreditsBalance(next) {
    if (typeof next !== 'number' || Number.isNaN(next)) return;
    creditsBalance = Math.max(0, next);
    root.dataset.credits = String(creditsBalance);
    root.querySelectorAll('[data-credits-balance]').forEach((node) => {
      node.textContent = String(creditsBalance);
    });
  }

  function hideCreditsWelcome() {
    if (!els.creditsWelcome) return;
    els.creditsWelcome.hidden = true;
    els.creditsWelcome.remove();
    els.creditsWelcome = null;
  }

  function readingAstroPayload() {
    return {
      sun_sign: drawProfile.sun_sign || null,
      element: drawProfile.element || null,
      modality: drawProfile.modality || null,
      tones: drawProfile.tones || [],
      variants: drawProfile.variants || [],
      datetime_utc: drawProfile.datetime_utc || null,
      chart_mode: drawProfile.chart_mode || null,
    };
  }

  async function requestReading({ mode = 'initial', followUp = '' } = {}) {
    if (requireAuth()) return;
    if (!readingUrl || selected.length !== SLOT_COUNT) return;

    if (mode === 'initial' && creditsBalance < 1) {
      showToast(t().messageNeedCredits, false);
      return;
    }

    const question = questionText();
    if (question.length < minQuestionChars) {
      showToast(needQuestionMessage(), false);
      els.question?.focus();
      return;
    }

    if (!llmEnabled) {
      showToast(t().messageLlmDisabled, false);
      return;
    }

    if (mode === 'clarify' && !followUp.trim()) {
      showToast(t().followupNeedText, false);
      els.readingFollowupInput?.focus();
      return;
    }

    if ((mode === 'clarify' || mode === 'expand') && !activeReading?.reading) {
      showToast(t().messageError, false);
      return;
    }

    if (mode === 'initial') {
      const key = readingPayloadKey();
      if (key === lastReadingKey || readingRequest) return;
      lastReadingKey = key;
      showToast(t().messageLoading, true);
    } else {
      if (readingRequest) return;
      setFollowupBusy(true);
      showToast(t().messageLoading, true);
    }

    const controller = new AbortController();
    readingRequest = controller;
    const cards = mode === 'initial' ? buildCardsPayload() : (activeReading?.cards || buildCardsPayload());

    try {
      const response = await fetch(readingUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        signal: controller.signal,
        body: JSON.stringify({
          question,
          locale: language,
          mode,
          follow_up: followUp || undefined,
          previous_reading: mode === 'initial' ? undefined : activeReading?.reading,
          cards,
          astro: readingAstroPayload(),
        }),
      });

      const data = await response.json().catch(() => ({}));
      if (typeof data.credits === 'number') {
        updateCreditsBalance(data.credits);
      }
      if (!response.ok) {
        if (response.status === 401) {
          requireAuth();
          throw new Error(data.error || t().messageNeedAuth);
        }
        if (response.status === 402) {
          throw new Error(data.error || t().messageNeedCredits);
        }
        throw new Error(data.error || t().messageError);
      }

      if (!data.reading) {
        throw new Error(t().messageError);
      }

      if (mode === 'initial') {
        activeReading = {
          question,
          cards,
          reading: data.reading,
          answers: [{
            mode: 'initial',
            label: answerLabel('initial'),
            text: data.reading,
          }],
        };
        syncCompleteView();
        showReadingResult({
          body: data.reading,
          error: false,
          showFollowup: true,
        });
        hideToast();
      } else {
        appendReadingText(data.reading, mode);
        if (els.readingFollowupInput) els.readingFollowupInput.value = '';
        hideToast();
      }
    } catch (error) {
      if (error?.name === 'AbortError') return;
      if (mode === 'initial') lastReadingKey = '';
      showToast(error?.message || t().messageError, false);
    } finally {
      if (readingRequest === controller) readingRequest = null;
      setFollowupBusy(false);
    }
  }

  async function maybeRequestReading() {
    return requestReading({ mode: 'initial' });
  }

  function bindUiEvents() {
    els.menuButton?.addEventListener('click', () => setMenuOpen(true));
    els.menuClose?.addEventListener('click', () => setMenuOpen(false));
    els.menuBackdrop?.addEventListener('click', () => setMenuOpen(false));
    window.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        setMenuOpen(false);
        setAuthModalOpen(false);
        hideCreditsWelcome();
        hideToast();
      }
    });

    els.readingToast?.querySelector('[data-reading-toast-close]')?.addEventListener('click', hideToast);
    root.querySelector('[data-credits-welcome-close]')?.addEventListener('click', hideCreditsWelcome);

    if (!isAuthenticated && els.question) {
      const openGate = (event) => {
        requireAuth(event);
      };
      els.question.addEventListener('focus', openGate);
      els.question.addEventListener('pointerdown', openGate);
      els.question.addEventListener('click', openGate);
      els.question.addEventListener('keydown', openGate);
    }

    root.querySelectorAll('[data-auth-open]').forEach((node) => {
      node.addEventListener('click', () => {
        setMenuOpen(false);
        setAuthModalOpen(true);
      });
    });
    root.querySelectorAll('[data-auth-close]').forEach((node) => {
      node.addEventListener('click', () => setAuthModalOpen(false));
    });
    els.authModal?.querySelectorAll('[data-auth-tab]').forEach((button) => {
      button.addEventListener('click', () => switchAuthTab(button.dataset.authTab));
    });

    if (openAuthOnLoad) {
      const registerName = els.authModal?.querySelector('[data-auth-panel="register"] input[name="name"]');
      if (registerName && registerName.value) {
        switchAuthTab('register');
      }
      setAuthModalOpen(true);
    }

    els.readingFollowupSend?.addEventListener('click', () => {
      requestReading({
        mode: 'clarify',
        followUp: (els.readingFollowupInput?.value || '').trim(),
      });
    });
    els.readingFollowupExpand?.addEventListener('click', () => {
      requestReading({
        mode: 'expand',
        followUp: (els.readingFollowupInput?.value || '').trim(),
      });
    });

    els.sharePdf?.addEventListener('click', () => { downloadPdf(); });
    els.shareEmail?.addEventListener('click', () => { sendEmail(); });
    els.shareFacebook?.addEventListener('click', () => { shareFacebook(); });

    root.querySelector('[data-scroll-left]')?.addEventListener('click', () => {
      els.slider.scrollBy({ left: -270, behavior: 'smooth' });
    });
    root.querySelector('[data-scroll-right]')?.addEventListener('click', () => {
      els.slider.scrollBy({ left: 270, behavior: 'smooth' });
    });

    els.language?.addEventListener('change', () => {
      language = els.language.value === 'en' ? 'en' : 'hu';
      applyCopy();
      if (localeSwitchUrl) {
        const url = localeSwitchUrl.replace('__LOCALE__', language);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(() => {});
      }
    });

    els.question?.addEventListener('input', () => {
      if (!isAuthenticated) {
        els.question.value = '';
        requireAuth();
        return;
      }
      els.question.value = els.question.value.slice(0, 280);
      if (els.question.nextElementSibling) {
        els.question.nextElementSibling.textContent = `${els.question.value.length}/280`;
      }
      clearTimeout(questionTimer);
      questionTimer = setTimeout(() => {
        if (selected.length === SLOT_COUNT) maybeRequestReading();
      }, 600);
    });

    els.reset?.addEventListener('click', reset);
  }

  async function boot() {
    if (els.language) els.language.value = language;
    applyCopy();
    bindUiEvents();

    try {
      const [oracleRes, tarotRes] = await Promise.all([
        fetch(`${assetBase}/assets/egi-orakulum/deck.json`),
        fetch(`${assetBase}/assets/tarot-major/deck.json`),
      ]);
      if (!oracleRes.ok || !tarotRes.ok) {
        throw new Error('Deck assets unavailable');
      }
      oracleDeck = await oracleRes.json();
      tarotDeck = await tarotRes.json();
      renderDeck(true);
      renderChosen();
    } catch (error) {
      console.error(error);
      showToast(language === 'en'
        ? 'Card decks could not be loaded. Please refresh.'
        : 'A paklik nem töltődtek be. Frissítsd az oldalt.', false);
    }
  }

  boot();
})();
