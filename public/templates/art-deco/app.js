(() => {
  const root = document.getElementById('astrotarot-app');
  if (!root) return;

  const assetBase = root.dataset.assetBase.replace(/\/$/, '');
  const localeSwitchUrl = root.dataset.localeSwitchUrl;
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
      message: 'A jelenlegi helyzetedben a tudatos teremtés, a belső hang és a nyitott szív együtt mutatja az irányt. Ne siettesd a választ: figyeld meg, melyik lap részlete szólít meg először.',
      astroBackground: 'Astro háttér',
      sunSign: 'napjegy',
      elementDominance: 'elem dominancia',
      modalityDominance: 'minőség dominancia',
      slotTitles: ['Ez van', 'segítség-Akadály', 'Tedd-ne tedd'],
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
      message: 'In your present situation, conscious creation, inner knowing and an open heart point in the same direction. Do not rush the answer: notice which detail calls to you first.',
      astroBackground: 'Astro background',
      sunSign: 'sun sign',
      elementDominance: 'element dominance',
      modalityDominance: 'modality dominance',
      slotTitles: ['What is', 'Help–Obstacle', 'Do–Don’t'],
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
    reveal: root.querySelector('[data-reveal]'),
    message: root.querySelector('[data-message]'),
    messageTitle: root.querySelector('[data-message-title]'),
    messageQuestion: root.querySelector('[data-message-question]'),
    messageBody: root.querySelector('[data-message-body]'),
  };

  let language = initialLocale;
  let deckData = null;
  /** @type {{backIndex:number, figureId:string, backgroundId:string, variant:string}[]} */
  let selected = [];
  let showMessage = false;

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

  function setMenuOpen(open) {
    els.menuBackdrop.classList.toggle('is-open', open);
    els.sideMenu.classList.toggle('is-open', open);
    els.sideMenu.setAttribute('aria-hidden', open ? 'false' : 'true');
    els.menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function applyCopy() {
    const text = t();
    els.menuButton.setAttribute('aria-label', text.navLabel);
    els.title.textContent = text.title;
    els.question.placeholder = text.placeholder;
    els.hint.innerHTML = `<span>☾</span>${text.hint}`;
    if (els.reading) {
      els.reading.textContent = text.reading;
    }
    els.reset.textContent = `↺ ${text.reset}`;
    els.messageTitle.textContent = text.messageTitle;
    els.messageBody.textContent = text.message;
    els.counter.innerHTML = `<b>${selected.length}</b>/3 ${text.selected}`;
    updateRevealButton();
    renderChosen();
  }

  function updateRevealButton() {
    const text = t();
    const ready = selected.length === 3 && els.question.value.trim().length > 2;
    els.reveal.disabled = !ready;
    els.reveal.querySelector('span').textContent = ready ? text.button : text.incomplete;
  }

  function figureMeta(figureId) {
    return deckData.figures.find((figure) => figure.id === figureId);
  }

  function localize(map, key, fallback = key) {
    return map[key]?.[language] || fallback;
  }

  function astroBackgroundCaption(slotIndex) {
    const text = t();
    if (slotIndex === 0) {
      const value = localize(labels.signs, drawProfile.sun_sign, drawProfile.sun_sign || '—');
      return `${text.sunSign}: ${value}`;
    }
    if (slotIndex === 1) {
      const value = localize(labels.elements, drawProfile.element, drawProfile.element || '—');
      return `${text.elementDominance}: ${value}`;
    }
    const value = localize(labels.modalities, drawProfile.modality, drawProfile.modality || '—');
    return `${text.modalityDominance}: ${value}`;
  }

  function cardBackSrc() {
    return `${assetBase}/assets/card-back/card-back.webp`;
  }

  function renderDeck() {
    const backSrc = cardBackSrc();
    els.slider.innerHTML = '';

    for (let index = 0; index < deckData.backs; index += 1) {
      const chosenIndex = selected.findIndex((item) => item.backIndex === index);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `deck-card${chosenIndex >= 0 ? ' is-selected' : ''}`;
      button.setAttribute('role', 'listitem');
      button.setAttribute('aria-pressed', chosenIndex >= 0 ? 'true' : 'false');
      button.setAttribute('aria-label', `${String(index + 1).padStart(2, '0')}. kártya`);
      button.innerHTML = `
        <img src="${backSrc}" alt="" draggable="false">
        <span class="card-number">${String(index + 1).padStart(2, '0')}</span>
        ${chosenIndex >= 0 ? `<span class="selected-badge">${chosenIndex + 1}</span>` : ''}
      `;
      button.addEventListener('click', () => toggleBack(index));
      els.slider.appendChild(button);
    }
  }

  function renderChosen() {
    const text = t();
    els.chosen.innerHTML = '';
    els.reset.hidden = selected.length === 0;

    for (let slot = 0; slot < 3; slot += 1) {
      const draw = selected[slot];
      const article = document.createElement('article');
      article.className = `chosen-card${draw ? ' has-card' : ' is-placeholder'}`;
      const astroLine = astroBackgroundCaption(slot);

      if (!draw) {
        article.innerHTML = `
          <h3 class="slot-title">${text.slotTitles[slot]}</h3>
          <div class="card-art placeholder-art">
            <img class="card-back" src="${cardBackSrc()}" alt="">
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
          <div class="card-art">
            <img class="card-background" src="${background}" alt="">
            <img class="card-figure" src="${figure}" alt="">
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
    showMessage = false;
    els.message.classList.remove('is-visible');

    const existing = selected.findIndex((item) => item.backIndex === backIndex);
    if (existing >= 0) {
      selected.splice(existing, 1);
      selected = selected.map((item, slot) => ({
        ...item,
        backgroundId: slotBackground(slot),
        variant: toneVariant(slot),
      }));
    } else if (selected.length < 3) {
      const used = selected.map((item) => item.figureId);
      const slot = selected.length;
      selected.push({ backIndex, ...drawForSlot(slot, used) });
    }

    els.counter.innerHTML = `<b>${selected.length}</b>/3 ${t().selected}`;
    renderDeck();
    renderChosen();
    updateRevealButton();
  }

  function reset() {
    selected = [];
    showMessage = false;
    els.question.value = '';
    els.message.classList.remove('is-visible');
    els.question.nextElementSibling.textContent = '0/280';
    applyCopy();
    renderDeck();
    els.slider.scrollTo({ left: 0, behavior: 'smooth' });
  }

  async function boot() {
    const response = await fetch(`${assetBase}/deck.json`);
    deckData = await response.json();

    els.language.value = language;
    applyCopy();
    renderDeck();
    renderChosen();

    els.menuButton.addEventListener('click', () => setMenuOpen(true));
    els.menuClose.addEventListener('click', () => setMenuOpen(false));
    els.menuBackdrop.addEventListener('click', () => setMenuOpen(false));
    window.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') setMenuOpen(false);
    });

    root.querySelector('[data-scroll-left]')?.addEventListener('click', () => {
      els.slider.scrollBy({ left: -270, behavior: 'smooth' });
    });
    root.querySelector('[data-scroll-right]')?.addEventListener('click', () => {
      els.slider.scrollBy({ left: 270, behavior: 'smooth' });
    });

    els.language.addEventListener('change', () => {
      language = els.language.value === 'en' ? 'en' : 'hu';
      applyCopy();
      if (localeSwitchUrl) {
        const url = localeSwitchUrl.replace('__LOCALE__', language);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(() => {});
      }
    });

    els.question.addEventListener('input', () => {
      els.question.value = els.question.value.slice(0, 280);
      els.question.nextElementSibling.textContent = `${els.question.value.length}/280`;
      showMessage = false;
      els.message.classList.remove('is-visible');
      updateRevealButton();
    });

    els.reset.addEventListener('click', reset);

    els.reveal.addEventListener('click', () => {
      if (els.reveal.disabled) return;
      showMessage = true;
      els.messageQuestion.textContent = els.question.value.trim();
      els.message.classList.add('is-visible');
    });
  }

  boot();
})();
