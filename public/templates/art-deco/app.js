(() => {
  const root = document.getElementById('astrotarot-app');
  if (!root) return;

  const assetBase = root.dataset.assetBase.replace(/\/$/, '');
  const localeSwitchUrl = root.dataset.localeSwitchUrl;
  const readingUrl = root.dataset.readingUrl;
  const minQuestionChars = Math.max(1, Number.parseInt(root.dataset.minQuestionChars || '10', 10) || 10);
  const llmEnabled = root.dataset.llmEnabled !== '0';
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
      title: 'Tedd fel a kérdésed és válassz 3 kártyát!',
      placeholder: 'Mi az, amire most igazán szeretnél választ kapni?',
      hint: 'Fogalmazz úgy, mintha a bölcsebb énedhez beszélnél.',
      reading: 'Felfedett lapjaid',
      waiting: 'Rád vár',
      reset: 'Új húzás',
      button: 'Mutasd az üzenetet',
      incomplete: 'Válassz 3 lapot és írd be a kérdésed',
      messageTitle: 'A lapok üzenete',
      messageKicker: 'AstroTarot értelmezés',
      messageLoading: 'A lapok üzenete készül…',
      messageNeedQuestion: 'Írj legalább {n} karakteres kérdést, mielőtt lapot választasz.',
      messageError: 'Az értelmezés most nem sikerült. Próbáld újra egy pillanat múlva.',
      messageLlmDisabled: 'Az LLM értelmezés jelenleg ki van kapcsolva.',
      message: '',
      astroBackground: 'Astro háttér',
      sunSign: 'napjegy',
      elementDominance: 'elem dominancia',
      modalityDominance: 'minőség dominancia',
      slotTitles: ['Ez van', 'Segítség / Akadály', 'Tedd / Ne tedd'],
      polarityLight: 'Light',
      polarityShadow: 'Shadow',
    },
    en: {
      navLabel: 'Open navigation',
      selected: 'selected',
      title: 'Ask your question and choose 3 cards!',
      placeholder: 'What would you truly like clarity about right now?',
      hint: 'Write as if you were speaking to your wiser self.',
      reading: 'Your revealed cards',
      waiting: 'Waiting for you',
      reset: 'New reading',
      button: 'Reveal the message',
      incomplete: 'Choose 3 cards and enter your question',
      messageTitle: 'The message of your cards',
      messageKicker: 'AstroTarot reading',
      messageLoading: 'Your reading is being written…',
      messageNeedQuestion: 'Write a question of at least {n} characters before choosing a card.',
      messageError: 'The reading could not be created. Please try again in a moment.',
      messageLlmDisabled: 'LLM readings are currently turned off.',
      message: '',
      astroBackground: 'Astro background',
      sunSign: 'sun sign',
      elementDominance: 'element dominance',
      modalityDominance: 'modality dominance',
      slotTitles: ['What is', 'Help / Obstacle', 'Do / Don’t'],
      polarityLight: 'Light',
      polarityShadow: 'Shadow',
    },
  };

  const labels = {
    signs: {
      aries: { hu: 'Kos', en: 'Aries' },
      taurus: { hu: 'Bika', en: 'Taurus' },
      gemini: { hu: 'Ikrek', en: 'Gemini' },
      cancer: { hu: 'Rák', en: 'Cancer' },
      leo: { hu: 'Oroszlán', en: 'Leo' },
      virgo: { hu: 'Szűz', en: 'Virgo' },
      libra: { hu: 'Mérleg', en: 'Libra' },
      scorpio: { hu: 'Skorpió', en: 'Scorpio' },
      sagittarius: { hu: 'Nyilas', en: 'Sagittarius' },
      capricorn: { hu: 'Bak', en: 'Capricorn' },
      aquarius: { hu: 'Vízöntő', en: 'Aquarius' },
      pisces: { hu: 'Halak', en: 'Pisces' },
    },
    elements: {
      'tűz': { hu: 'tűz', en: 'fire' },
      'víz': { hu: 'víz', en: 'water' },
      'föld': { hu: 'föld', en: 'earth' },
      'levegő': { hu: 'levegő', en: 'air' },
    },
    modalities: {
      'fő': { hu: 'fő', en: 'cardinal' },
      'szilárd': { hu: 'szilárd', en: 'fixed' },
      'változó': { hu: 'változó', en: 'mutable' },
    },
  };

  const romans = ['I', 'II', 'III'];
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
    reading: root.querySelector('[data-reading]'),
    reset: root.querySelector('[data-reset]'),
    slider: root.querySelector('[data-deck-slider]'),
    chosen: root.querySelector('[data-chosen-grid]'),
    readingModal: root.querySelector('[data-reading-modal]'),
    readingKicker: root.querySelector('[data-reading-kicker]'),
    readingTitle: root.querySelector('[data-reading-title]'),
    readingQuestion: root.querySelector('[data-reading-question]'),
    readingBody: root.querySelector('[data-reading-body]'),
  };

  let language = initialLocale;
  let deckData = null;
  /** @type {{backIndex:number, figureId:string, backgroundId:string, variant:string}[]} */
  let selected = [];
  let readingRequest = null;
  let lastReadingKey = '';
  let questionTimer = null;

  const pick = (items) => items[Math.floor(Math.random() * items.length)];

  function toneVariant(slotIndex) {
    const variants = Array.isArray(drawProfile.variants) ? drawProfile.variants : [];
    if (variants[slotIndex] === 'shadow' || variants[slotIndex] === 'positive') {
      return variants[slotIndex];
    }
    return drawProfile.variant === 'shadow' ? 'shadow' : 'positive';
  }

  function slotBackground(slotIndex) {
    const slots = Array.isArray(drawProfile.slots) ? drawProfile.slots : [];
    const preferred = slots[slotIndex];
    if (typeof preferred === 'string' && preferred.length > 0) {
      return preferred;
    }
    const backgrounds = deckData?.backgrounds || [];
    return backgrounds[slotIndex] || backgrounds[0] || 'leo';
  }

  function randomFigure(usedFigureIds = []) {
    const available = deckData.figures.filter((figure) => !usedFigureIds.includes(figure.id));
    const pool = available.length ? available : deckData.figures;
    return pick(pool).id;
  }

  function drawForSlot(slotIndex, usedFigureIds = []) {
    return {
      figureId: randomFigure(usedFigureIds),
      backgroundId: slotBackground(slotIndex),
      variant: toneVariant(slotIndex),
    };
  }

  function t() {
    return copy[language];
  }

  function needQuestionMessage() {
    return t().messageNeedQuestion.replace('{n}', String(minQuestionChars));
  }

  function setMenuOpen(open) {
    els.menuBackdrop.classList.toggle('is-open', open);
    els.sideMenu.classList.toggle('is-open', open);
    els.sideMenu.setAttribute('aria-hidden', open ? 'false' : 'true');
    els.menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function applyCopy() {
    const text = t();
    if (els.menuButton) els.menuButton.setAttribute('aria-label', text.navLabel);
    if (els.title) els.title.textContent = text.title;
    if (els.question) els.question.placeholder = text.placeholder;
    if (els.hint) els.hint.innerHTML = `<span>☾</span>${text.hint}`;
    if (els.reading) els.reading.textContent = text.reading;
    if (els.reset) els.reset.textContent = `↺ ${text.reset}`;
    if (els.readingKicker) els.readingKicker.textContent = text.messageKicker;
    if (els.readingTitle) els.readingTitle.textContent = text.messageTitle;
    if (els.counter) els.counter.innerHTML = `<b>${selected.length}</b>/3 ${text.selected}`;
    renderChosen();
  }

  function polarityLabel(slotIndex, variant = null) {
    const text = t();
    const resolved = variant || toneVariant(slotIndex);
    return resolved === 'shadow' ? text.polarityShadow : text.polarityLight;
  }

  function polarityClass(slotIndex, variant = null) {
    const resolved = variant || toneVariant(slotIndex);
    return resolved === 'shadow' ? 'is-shadow' : 'is-light';
  }

  function figureMeta(figureId) {
    return deckData.figures.find((figure) => figure.id === figureId);
  }

  function localize(map, key, fallback = key) {
    return map[key]?.[language] || fallback;
  }

  function astroBackgroundCaption(slotIndex) {
    const text = t();
    const kinds = Array.isArray(drawProfile.slot_kinds) ? drawProfile.slot_kinds : null;
    const values = Array.isArray(drawProfile.slot_values) ? drawProfile.slot_values : null;
    const kind = kinds?.[slotIndex]
      || (slotIndex === 0 ? 'sun_sign' : slotIndex === 1 ? 'element' : 'modality');
    const rawValue = values?.[slotIndex]
      || (kind === 'sun_sign'
        ? drawProfile.sun_sign
        : kind === 'element'
          ? drawProfile.element
          : drawProfile.modality);

    if (kind === 'sun_sign') {
      return `${text.sunSign}: ${localize(labels.signs, rawValue, rawValue || '—')}`;
    }
    if (kind === 'element') {
      return `${text.elementDominance}: ${localize(labels.elements, rawValue, rawValue || '—')}`;
    }
    return `${text.modalityDominance}: ${localize(labels.modalities, rawValue, rawValue || '—')}`;
  }

  function cardBackSrc() {
    return `${assetBase}/assets/card-back/card-back.webp`;
  }

  function syncDeckSelection() {
    [...els.slider.children].forEach((button, index) => {
      const chosenIndex = selected.findIndex((item) => item.backIndex === index);
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

  function renderDeck() {
    if (!els.slider || !deckData) return;

    // Build once so mobile browsers do not reload 22 card images on every tap.
    if (els.slider.children.length !== deckData.backs) {
      const backSrc = cardBackSrc();
      const scrollLeft = els.slider.scrollLeft;
      els.slider.innerHTML = '';

      for (let index = 0; index < deckData.backs; index += 1) {
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

      els.slider.scrollLeft = scrollLeft;
    }

    syncDeckSelection();
  }

  function renderChosen() {
    if (!els.chosen) return;
    const text = t();
    els.chosen.innerHTML = '';
    if (els.reset) els.reset.hidden = selected.length === 0;

    for (let slot = 0; slot < 3; slot += 1) {
      const draw = selected[slot];
      const article = document.createElement('article');
      article.className = `chosen-card${draw ? ' has-card' : ' is-placeholder'}`;
      const astroLine = astroBackgroundCaption(slot);

      if (!draw) {
        article.innerHTML = `
          <h3 class="slot-title">${text.slotTitles[slot]}</h3>
          <p class="polarity-label ${polarityClass(slot)}">${polarityLabel(slot)}</p>
          <div class="card-art placeholder-art">
            <img class="card-back" src="${cardBackSrc()}" alt="" decoding="async">
            <span class="roman">${romans[slot]}</span>
          </div>
          <div class="card-caption">
            <h3>${text.waiting}</h3>
            <p class="astro-heading">${text.astroBackground}</p>
            <p class="astro-detail">${astroLine}</p>
          </div>
        `;
      } else {
        const meta = figureMeta(draw.figureId);
        const background = `${assetBase}/assets/backgrounds/${draw.backgroundId}-${draw.variant}.webp`;
        const figure = `${assetBase}/assets/figures/${draw.figureId}-${draw.variant}.webp`;
        article.innerHTML = `
          <h3 class="slot-title">${text.slotTitles[slot]}</h3>
          <p class="polarity-label ${polarityClass(slot, draw.variant)}">${polarityLabel(slot, draw.variant)}</p>
          <div class="card-art">
            <img class="card-background" src="${background}" alt="" decoding="async">
            <img class="card-figure" src="${figure}" alt="" decoding="async">
            <span class="roman">${romans[slot]}</span>
          </div>
          <div class="card-caption">
            <h3>${meta ? meta[language] : draw.figureId}</h3>
            <p class="card-keyword">${meta ? meta.keyword[language] : ''}</p>
            <p class="astro-heading">${text.astroBackground}</p>
            <p class="astro-detail">${astroLine}</p>
          </div>
        `;
      }

      els.chosen.appendChild(article);
    }
  }

  function toggleBack(backIndex) {
    // Selection is one-way: already chosen cards cannot be deselected by tap.
    if (selected.some((item) => item.backIndex === backIndex) || selected.length >= 3) {
      return;
    }

    if (selected.length === 0 && questionText().length < minQuestionChars) {
      els.question?.focus();
      openReadingModal({
        body: needQuestionMessage(),
        loading: false,
        error: false,
        question: '',
      });
      return;
    }

    const used = selected.map((item) => item.figureId);
    const slot = selected.length;
    selected.push({ backIndex, ...drawForSlot(slot, used) });

    if (els.counter) els.counter.innerHTML = `<b>${selected.length}</b>/3 ${t().selected}`;
    renderDeck();
    renderChosen();
    maybeRequestReading();
  }

  function reset() {
    selected = [];
    lastReadingKey = '';
    readingRequest = null;
    closeReadingModal();
    if (els.question) {
      els.question.value = '';
      if (els.question.nextElementSibling) {
        els.question.nextElementSibling.textContent = '0/280';
      }
    }
    applyCopy();
    renderDeck();
    els.slider?.scrollTo({ left: 0, behavior: 'smooth' });
  }

  function questionText() {
    return (els.question?.value || '').trim();
  }

  function readingPayloadKey() {
    return JSON.stringify({
      q: questionText(),
      cards: selected.map((item) => [item.backIndex, item.figureId, item.backgroundId, item.variant]),
      lang: language,
    });
  }

  function openReadingModal({ body, loading = false, error = false, question = '' }) {
    if (!els.readingModal || !els.readingBody) return;
    const text = t();
    if (els.readingKicker) els.readingKicker.textContent = text.messageKicker;
    if (els.readingTitle) els.readingTitle.textContent = text.messageTitle;
    if (els.readingQuestion) {
      els.readingQuestion.textContent = question || questionText();
      els.readingQuestion.hidden = !(question || questionText());
    }
    els.readingBody.classList.toggle('is-loading', loading);
    els.readingBody.classList.toggle('is-error', error);
    els.readingBody.textContent = body;
    els.readingModal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeReadingModal() {
    if (!els.readingModal) return;
    els.readingModal.hidden = true;
    document.body.style.overflow = '';
  }

  function buildCardsPayload() {
    return selected.map((draw, slot) => {
      const meta = figureMeta(draw.figureId);
      const polarity = draw.variant === 'shadow' ? 'shadow' : 'positive';
      return {
        figure_id: draw.figureId,
        figure_name: meta ? meta[language] : draw.figureId,
        keyword: meta ? meta.keyword[language] : '',
        background_id: `${draw.backgroundId}-${draw.variant}`,
        background: draw.backgroundId,
        variant: draw.variant,
        polarity,
        astro_caption: astroBackgroundCaption(slot),
      };
    });
  }

  async function maybeRequestReading() {
    if (!readingUrl || selected.length !== 3) return;

    const question = questionText();
    if (question.length < minQuestionChars) {
      openReadingModal({
        body: needQuestionMessage(),
        loading: false,
        error: false,
        question: '',
      });
      els.question?.focus();
      return;
    }

    if (!llmEnabled) {
      openReadingModal({
        body: t().messageLlmDisabled,
        loading: false,
        error: true,
        question,
      });
      return;
    }

    const key = readingPayloadKey();
    if (key === lastReadingKey || readingRequest) return;

    lastReadingKey = key;
    openReadingModal({
      body: t().messageLoading,
      loading: true,
      error: false,
      question,
    });

    const controller = new AbortController();
    readingRequest = controller;

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
          cards: buildCardsPayload(),
          astro: {
            sun_sign: drawProfile.sun_sign || null,
            element: drawProfile.element || null,
            modality: drawProfile.modality || null,
            tones: drawProfile.tones || [],
            variants: drawProfile.variants || [],
          },
        }),
      });

      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data.error || t().messageError);
      }

      openReadingModal({
        body: data.reading || t().messageError,
        loading: false,
        error: !data.reading,
        question,
      });
    } catch (error) {
      if (error?.name === 'AbortError') return;
      lastReadingKey = '';
      openReadingModal({
        body: error?.message || t().messageError,
        loading: false,
        error: true,
        question,
      });
    } finally {
      if (readingRequest === controller) readingRequest = null;
    }
  }

  async function boot() {
    const response = await fetch(`${assetBase}/deck.json`);
    deckData = await response.json();

    if (els.language) els.language.value = language;
    applyCopy();
    renderDeck();
    renderChosen();

    els.menuButton?.addEventListener('click', () => setMenuOpen(true));
    els.menuClose?.addEventListener('click', () => setMenuOpen(false));
    els.menuBackdrop?.addEventListener('click', () => setMenuOpen(false));
    window.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        setMenuOpen(false);
        closeReadingModal();
      }
    });

    root.querySelectorAll('[data-reading-close]').forEach((node) => {
      node.addEventListener('click', closeReadingModal);
    });

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
      els.question.value = els.question.value.slice(0, 280);
      if (els.question.nextElementSibling) {
        els.question.nextElementSibling.textContent = `${els.question.value.length}/280`;
      }
      clearTimeout(questionTimer);
      questionTimer = setTimeout(() => {
        if (selected.length === 3) maybeRequestReading();
      }, 600);
    });

    els.reset?.addEventListener('click', reset);
  }

  boot();
})();
