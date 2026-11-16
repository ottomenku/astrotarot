(() => {
  const root = document.getElementById('astrotarot-app');
  if (!root) return;

  const assetBase = root.dataset.assetBase.replace(/\/$/, '');
  const localeSwitchUrl = root.dataset.localeSwitchUrl;
  const readingUrl = root.dataset.readingUrl;
  const classifyUrl = root.dataset.classifyUrl || '';
  const birthProfileUrl = root.dataset.birthProfileUrl || '';
  const feedbackUrl = root.dataset.readingFeedbackUrl || '';
  const exportPdfUrl = root.dataset.exportPdfUrl || '';
  const exportEmailUrl = root.dataset.exportEmailUrl || '';
  const shareCreateUrl = root.dataset.shareUrl || '';
  const userEmail = root.dataset.userEmail || '';
  let featureAccess = {};
  try {
    featureAccess = JSON.parse(root.dataset.featureAccess || '{}') || {};
  } catch {
    featureAccess = {};
  }
  const creditsPackagesUrl = root.dataset.creditsPackagesUrl || '/credits';
  const minQuestionChars = Math.max(1, Number.parseInt(root.dataset.minQuestionChars || '10', 10) || 10);
  const llmEnabled = root.dataset.llmEnabled !== '0';
  const isAuthenticated = root.dataset.authenticated === '1';
  const guestStorageKey = 'astrotarot-guest';
  let continueAsGuest = false;
  let pendingGuestAction = null;
  try { continueAsGuest = sessionStorage.getItem(guestStorageKey) === '1'; } catch (error) {}
  const openAuthOnLoad = root.dataset.openAuth === '1';
  let creditsBalance = Number.parseInt(root.dataset.credits || '0', 10) || 0;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const initialLocale = root.dataset.locale === 'en' ? 'en' : 'hu';

  function jsonErrorMessage(data, fallback) {
    if (typeof data?.error === 'string' && data.error.trim()) return data.error;
    if (typeof data?.message === 'string' && data.message.trim()) return data.message;
    const first = data?.errors && Object.values(data.errors).flat()
      .find((item) => typeof item === 'string' && item.trim());
    return first || fallback;
  }

  function featureState(key) {
    const row = featureAccess[key];
    if (!row || typeof row !== 'object') {
      return { allowed: true, cost: 0, will_charge: false, message: '' };
    }
    return {
      allowed: row.allowed !== false,
      cost: Number.parseInt(row.cost, 10) || 0,
      will_charge: row.will_charge === true,
      message: typeof row.message === 'string' ? row.message : '',
    };
  }

  function readingFeatureKey(mode) {
    if (mode === 'clarify' || mode === 'refine') return 'reading.clarify';
    if (mode === 'expand') return 'reading.expand';
    return 'reading.initial';
  }

  function jsonHeaders(extra = {}) {
    return {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'X-Requested-With': 'XMLHttpRequest',
      ...extra,
    };
  }
  const drawProfile = (() => {
    try {
      return JSON.parse(root.dataset.drawProfile || '{}');
    } catch (error) {
      return {};
    }
  })();

  let gameConfig = {};
  try {
    gameConfig = JSON.parse(root.dataset.game || '{}') || {};
  } catch {
    gameConfig = {};
  }
  const packBase = (root.dataset.packBase || gameConfig.pack_base || '/card-pack').replace(/\/$/, '');
  const fallbackSlots = [
    { pack_slug: 'egi-orakulum', title_hu: 'Égi Orákulum', title_en: 'Celestial Oracle', info_mode: 'astro', row: 1, place: 1, draw_order: 1, full_bleed: true },
    { pack_slug: 'tarot', title_hu: 'Tarot figura', title_en: 'Tarot figure', info_mode: 'meaning', row: 1, place: 2, draw_order: 2, full_bleed: true },
  ];
  let gameSlots = (Array.isArray(gameConfig.slots) && gameConfig.slots.length)
    ? gameConfig.slots
    : fallbackSlots;
  let SLOT_COUNT = gameSlots.length;
  let hasQuestion = gameConfig.has_question !== false;
  let gameId = gameConfig.id || null;

  const copy = {
    hu: {
      navLabel: 'Navigáció megnyitása',
      selected: 'kiválasztva',
      title: 'Tedd fel a kérdésed a lenti szövegmezőben, majd válassz a pakliból Égi mestert, és tarot figurát!',
      placeholder: 'Mi az, amire most igazán szeretnél választ kapni?',
      hint: 'Először az Égi Orákulumból, aztán a Tarot pakliból választasz.',
      reading: 'Választott lapjaid',
      waiting: 'Rád vár',
      reset: 'Új húzás',
      pickCard: 'Egy kattintással válassz lapot a felső sorból!',
      spreadKicker: 'A jelenleg kiválasztott kirakás:',
      spreadSummary: ':name — :count lapot kell húzni',
      spreadChange: 'Változtatás',
      spreadGame: 'Kirakás',
      spreadPack: 'Pakli',
      spreadBack: 'Hátlap',
      spreadApply: 'Alkalmaz',
      spreadDefault: 'Alapértelmezett',
      spreadClose: 'Bezárás',
      incomplete: 'Válaszd ki a lapokat, és írd be a kérdésed',
      messageTitle: 'A lapok üzenete',
      messageKicker: 'AstroTarot értelmezés',
      messageLoading: 'Az LLM gondolkodik…',
      messageNeedQuestion: 'Írj legalább {n} karakteres kérdést, mielőtt lapot választasz.',
      drawNeedQuestion: 'Írj legalább {n} karakteres kérdést, mielőtt lapot választasz!',
      drawPick: 'Válaszd ki {article} {ordinal} lapot ami: {place}',
      messageError: 'Az értelmezés most nem sikerült. Próbáld újra egy pillanat múlva.',
      sessionExpired: 'A munkamenet lejárt. Frissítsd az oldalt, majd próbáld újra.',
      messageLlmDisabled: 'Az LLM értelmezés jelenleg ki van kapcsolva.',
      deckOracle: 'Égi Orákulum',
      deckTarot: 'Tarot pakli',
      slotTitles: ['Választott Égi mestered', 'Választott Figurád'],
      polarityLight: 'Light',
      polarityShadow: 'Shadow',
      fateRandom: 'sorslenyomat: :value',
      fateAnalyzing: 'Égi adatok elemzése',
      fateSky: 'Az égi helyzet: :value',
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
      followupPlaceholder: 'Pontosítás, vagy hagyd üresen az általános kifejtéshez',
      followupSend: 'Elküld',
      followupExpand: 'Bővebb kifejtést kérek',
      followupNeedText: 'Írd be a pontosítást, mielőtt elküldöd.',
      feedbackTitle: 'Adj visszajelzést a pontosabb válaszokért!',
      feedbackRating: 'Értékelés',
      feedbackVerdict: 'Minősítés',
      feedbackPlaceholder: 'Megjegyzés',
      feedbackSend: 'Elküldöm',
      feedbackNeed: 'Válassz értékelést, minősítést, vagy írj megjegyzést.',
      feedbackSaved: 'Köszönjük, a visszajelzést mentettük.',
      offerBirth: 'A pontosabb válaszhoz születési adat kell. Megadod? Ha nem, az eddigi olvasat jelenik meg.',
      offerRelation: 'A pontosabb válaszhoz egy másik személy születési adata kell. Megadod? Ha nem, az eddigi olvasat jelenik meg.',
      offerProjection: 'A pontosabb válaszhoz égi előrejelzés kell. A program kiszámolja. Elküldjem? Ez plusz kreditbe kerülhet.',
      offerYes: 'Igen, pontosítsuk',
      offerNo: 'Nem, maradjon az olvasat',
      offerWhen: 'Időpont',
      offerCountry: 'Ország',
      offerCity: 'Város',
      offerGender: 'Nem',
      offerFemale: 'nő',
      offerMale: 'férfi',
      offerOther: 'egyéb',
      offerIntro: 'Bemutatkozás',
      offerNeedWhen: 'Az időpont hiányzik.',
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
      sharePdfLocked: 'A PDF mentés Premium vagy Expert csomaghoz tartozik.',
      answerInitial: 'Olvasat',
      answerClarify: 'Pontosítás',
      answerExpand: 'Bővebb kifejtés',
      sourcePrefix: 'Forrás',
      questionHeading: 'Tedd fel a kérdésed, amire választ szeretnél kapni. Minél pontosabban leírod, annál pontosabb választ fogsz kapni.',
      questionClose: 'Bezárás',
      questionSend: 'Küldés',
      questionWorking: 'A kérdés besorolása folyamatban…',
      questionUnclear: 'A kérdés így nem értelmezhető. Fogalmazd át pontosabban.',
      questionUnmatched: 'Ehhez a kérdéshez most nincs megfelelő kirakás.',
      questionFailed: 'A besorolás most nem elérhető. Próbáld újra.',
      consentTitle: 'Születési adat',
      consentSaved: 'Elküldjük a mentett születési adatot?',
      consentMissing: 'Megadod a születési adatot ehhez a kirakáshoz?',
      consentYes: 'Igen',
      consentNo: 'Nem',
      consentFormTitle: 'Kit küldjünk?',
      consentSelf: 'Saját adat',
      consentRelation: 'Kapcsolati személy',
      consentWhen: 'Születés ideje',
      consentCountry: 'Ország',
      consentCity: 'Város',
      consentIntro: 'Bemutatkozás',
      consentLabel: 'Név',
      consentSavedNote: 'A saját mentett adat megy.',
      consentNone: 'Nem küldöm',
      consentNew: 'Új személy',
      consentContinue: 'Tovább',
      consentSkip: 'Adat nélkül folytatom',
      consentNeedBirth: 'A születés ideje hiányzik.',
      consentNeedName: 'A név hiányzik.',
      slotCoachKicker: 'Most ezt a lapot húzd',
      helperCaption: 'Égi segítő',
    },
    en: {
      navLabel: 'Open navigation',
      selected: 'selected',
      title: 'Enter your question in the text field below, then choose a Celestial Master and a tarot figure from the decks!',
      placeholder: 'What would you truly like clarity about right now?',
      hint: 'First pick from the Celestial Oracle, then from the Tarot deck.',
      reading: 'Your chosen cards',
      waiting: 'Waiting for you',
      reset: 'New reading',
      pickCard: 'Pick a card from the top row with one click!',
      spreadKicker: 'Current spread:',
      spreadSummary: ':name — draw :count cards',
      spreadChange: 'Change',
      spreadGame: 'Spread',
      spreadPack: 'Deck',
      spreadBack: 'Card back',
      spreadApply: 'Apply',
      spreadDefault: 'Default',
      spreadClose: 'Close',
      incomplete: 'Choose the cards and enter your question',
      messageTitle: 'The message of your cards',
      messageKicker: 'AstroTarot reading',
      messageLoading: 'The LLM is thinking…',
      messageNeedQuestion: 'Write a question of at least {n} characters before choosing a card.',
      drawNeedQuestion: 'Write a question of at least {n} characters before choosing a card!',
      drawPick: 'Choose the {ordinal} card, which is: {place}',
      messageError: 'The reading could not be created. Please try again in a moment.',
      sessionExpired: 'Your session expired. Please refresh the page and try again.',
      messageLlmDisabled: 'LLM readings are currently turned off.',
      deckOracle: 'Celestial Oracle',
      deckTarot: 'Tarot deck',
      slotTitles: ['Your Celestial Master', 'Your Chosen Figure'],
      polarityLight: 'Light',
      polarityShadow: 'Shadow',
      fateRandom: 'fate imprint: :value',
      fateAnalyzing: 'Reading the sky',
      fateSky: 'The sky’s position: :value',
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
      followupPlaceholder: 'Clarification, or leave empty for a general reading',
      followupSend: 'Send',
      followupExpand: 'Ask for a deeper reading',
      followupNeedText: 'Write a clarification before sending.',
      feedbackTitle: 'Give feedback for more accurate answers!',
      feedbackRating: 'Rating',
      feedbackVerdict: 'Verdict',
      feedbackPlaceholder: 'Note',
      feedbackSend: 'Send',
      feedbackNeed: 'Choose a rating, a verdict, or write a note.',
      feedbackSaved: 'Thank you, the feedback is saved.',
      offerBirth: 'A more precise answer needs a birth time. Will you add it? If not, the reading so far is shown.',
      offerRelation: 'A more precise answer needs another person’s birth data. Will you add it? If not, the reading so far is shown.',
      offerProjection: 'A more precise answer needs a sky forecast. The program calculates it. Send it? This can cost extra credits.',
      offerYes: 'Yes, refine it',
      offerNo: 'No, keep this reading',
      offerWhen: 'Time',
      offerCountry: 'Country',
      offerCity: 'City',
      offerGender: 'Gender',
      offerFemale: 'female',
      offerMale: 'male',
      offerOther: 'other',
      offerIntro: 'Introduction',
      offerNeedWhen: 'The time is missing.',
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
      sharePdfLocked: 'PDF export requires Premium or Expert.',
      answerInitial: 'Reading',
      answerClarify: 'Clarification',
      answerExpand: 'Deeper reading',
      sourcePrefix: 'Source',
      questionHeading: 'Ask the question you want answered. The more precisely you describe it, the more precise the answer will be.',
      questionClose: 'Close',
      questionSend: 'Send',
      questionWorking: 'Classifying the question…',
      questionUnclear: 'This question cannot be interpreted. Please rephrase it more precisely.',
      questionUnmatched: 'No spread matches this question right now.',
      questionFailed: 'Classification is unavailable right now. Please try again.',
      consentTitle: 'Birth data',
      consentSaved: 'Shall we send the saved birth data?',
      consentMissing: 'Will you give the birth data for this spread?',
      consentYes: 'Yes',
      consentNo: 'No',
      consentFormTitle: 'Whom shall we send?',
      consentSelf: 'My data',
      consentRelation: 'Related person',
      consentWhen: 'Time of birth',
      consentCountry: 'Country',
      consentCity: 'City',
      consentIntro: 'Introduction',
      consentLabel: 'Name',
      consentSavedNote: 'Your saved data will be sent.',
      consentNone: 'Do not send',
      consentNew: 'New person',
      consentContinue: 'Continue',
      consentSkip: 'Continue without data',
      consentNeedBirth: 'The time of birth is missing.',
      consentNeedName: 'The name is missing.',
      slotCoachKicker: 'Draw this card now',
      helperCaption: 'Celestial helper',
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

  const els = {
    menuButton: root.querySelector('[data-menu-open]'),
    menuClose: root.querySelector('[data-menu-close]'),
    menuBackdrop: root.querySelector('[data-menu-backdrop]'),
    sideMenu: root.querySelector('[data-side-menu]'),
    language: root.querySelector('[data-language]'),
    title: root.querySelector('[data-title]'),
    gameDescription: root.querySelector('[data-game-description]'),
    spreadKicker: root.querySelector('[data-spread-kicker]'),
    spreadSummary: root.querySelector('[data-spread-summary]'),
    spreadOpen: root.querySelector('[data-spread-open]'),
    spreadPanel: root.querySelector('[data-spread-panel]'),
    spreadGame: root.querySelector('[data-spread-game]'),
    spreadBack: root.querySelector('[data-spread-back]'),
    spreadBackPreview: root.querySelector('[data-spread-back-preview]'),
    questionWrap: root.querySelector('[data-question-wrap]'),
    question: root.querySelector('[data-question]'),
    questionModal: root.querySelector('[data-question-modal]'),
    questionForm: root.querySelector('[data-question-form]'),
    questionNote: root.querySelector('[data-question-note]'),
    questionSend: root.querySelector('[data-question-send]'),
    questionClose: root.querySelector('[data-question-close]'),
    consentModal: root.querySelector('[data-consent-modal]'),
    consentAsk: root.querySelector('[data-consent-ask]'),
    consentLead: root.querySelector('[data-consent-lead]'),
    consentYes: root.querySelector('[data-consent-yes]'),
    consentNo: root.querySelector('[data-consent-no]'),
    consentForm: root.querySelector('[data-consent-form]'),
    consentSavedNote: root.querySelector('[data-consent-saved-note]'),
    consentUserBlock: root.querySelector('[data-consent-user-block]'),
    consentRelationBlock: root.querySelector('[data-consent-relation-block]'),
    consentRelationSelect: root.querySelector('[data-consent-relation-select]'),
    consentRelationNew: root.querySelector('[data-consent-relation-new]'),
    consentNote: root.querySelector('[data-consent-note]'),
    consentSkip: root.querySelector('[data-consent-skip]'),
    slotCoach: root.querySelector('[data-slot-coach]'),
    slotCoachKicker: root.querySelector('[data-slot-coach-kicker]'),
    slotCoachTitle: root.querySelector('[data-slot-coach-title]'),
    slotCoachText: root.querySelector('[data-slot-coach-text]'),
    deckWrap: root.querySelector('[data-deck-wrap]'),
    counter: root.querySelector('[data-counter]'),
    hint: root.querySelector('[data-hint]'),
    deckLabel: root.querySelector('[data-deck-label]'),
    pickStage: root.querySelector('[data-pick-stage]'),
    resultHeader: root.querySelector('[data-result-header]'),
    askedQuestion: root.querySelector('[data-asked-question]'),
    resetButtons: root.querySelectorAll('[data-reset]'),
    slider: root.querySelector('[data-deck-slider]'),
    chosen: root.querySelector('[data-chosen-grid]'),
    drawReveal: root.querySelector('[data-draw-reveal]'),
    drawRevealImage: root.querySelector('[data-draw-reveal-image]'),
    drawRevealHelper: root.querySelector('[data-draw-reveal-helper]'),
    drawRevealHelperWrap: root.querySelector('[data-draw-reveal-helper-wrap]'),
    drawRevealHelperLabel: root.querySelector('[data-draw-reveal-helper-label]'),
    readingResult: root.querySelector('[data-reading-result]'),
    readingKicker: root.querySelector('[data-reading-kicker]'),
    readingTitle: root.querySelector('[data-reading-title]'),
    readingBody: root.querySelector('[data-reading-body]'),
    readingSource: root.querySelector('[data-reading-source]'),
    readingToast: root.querySelector('[data-reading-toast]'),
    readingFollowup: root.querySelector('[data-reading-followup]'),
    readingFollowupLabel: root.querySelector('[data-reading-followup-label]'),
    readingFollowupInput: root.querySelector('[data-reading-followup-input]'),
    readingFollowupSend: root.querySelector('[data-reading-followup-send]'),
    readingFollowupExpand: root.querySelector('[data-reading-followup-expand]'),
    readingFeedback: root.querySelector('[data-reading-feedback]'),
    feedbackRating: root.querySelector('[data-feedback-rating]'),
    feedbackVerdict: root.querySelector('[data-feedback-verdict]'),
    feedbackComment: root.querySelector('[data-feedback-comment]'),
    feedbackSend: root.querySelector('[data-feedback-send]'),
    feedbackStatus: root.querySelector('[data-feedback-status]'),
    readingOffer: root.querySelector('[data-reading-offer]'),
    readingOfferText: root.querySelector('[data-reading-offer-text]'),
    readingOfferForm: root.querySelector('[data-reading-offer-form]'),
    readingOfferYes: root.querySelector('[data-reading-offer-yes]'),
    readingOfferNo: root.querySelector('[data-reading-offer-no]'),
    readingShare: root.querySelector('[data-reading-share]'),
    readingShareLabel: root.querySelector('[data-reading-share-label]'),
    sharePdf: root.querySelector('[data-share-pdf]'),
    shareEmail: root.querySelector('[data-share-email]'),
    shareFacebook: root.querySelector('[data-share-facebook]'),
    authModal: root.querySelector('[data-auth-modal]'),
    creditsWelcome: root.querySelector('[data-credits-welcome]'),
  };

  let language = initialLocale;
  /** @type {Record<string, {id?:string, figures:any[], cardBack?:string}|null>} */
  const decksBySlug = {};
  /** @type {{deck:string, backIndex:number, figureId:string, variant:string}[]} */
  let selected = [];
  let revealing = false;
  let revealTimer = null;
  let settleTimer = null;
  let drawToken = 0;
  let flyer = null;
  let readingRequest = null;
  let lastReadingKey = '';
  let questionTimer = null;
  let renderedDeckKey = '';
  /** @type {{question:string, cards:any[], reading:string, answers: {mode:string, label:string, text:string, source?:string}[]}|null} */
  let activeReading = null;
  let pendingOffer = null;
  let lockedQuestion = '';
  let readingSessionId = '';
  let drawReady = false;
  let skyChoice = { user: null, relation: null };
  let consentNeeds = { user: false, relation: false, hasUser: false, profiles: [] };
  let shareBusy = false;
  let polarityTimer = null;
  let polarityFrame = 'positive';
  let revealingDraw = null;

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

  function slotRequestsAstroPolarity(slot) {
    const mode = gameConfig.light_shadow_mode || 'full-random';
    if (mode === 'full-astro') return true;
    if (mode === 'oracle-astro' || mode === 'orakulum-astro') {
      return (slot?.pack_type || '') === 'oracle';
    }
    return false;
  }

  function slotLightShadow(slot) {
    const mode = slot?.light_shadow;
    if (mode === 'none' || mode === 'random' || mode === 'random60' || mode === 'random70' || mode === 'astro' || mode === 'llm') {
      return mode;
    }
    return slotRequestsAstroPolarity(slot) ? 'llm' : 'random';
  }

  function celestialPool() {
    const list = Array.isArray(gameConfig.celestial_oracles) ? gameConfig.celestial_oracles : [];
    const ids = list.map((oracle) => oracle.id).filter(Boolean);
    return ids.length ? ids : ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn', 'uranus', 'neptune', 'pluto', 'aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo', 'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces'];
  }

  function resolveCelestialHelper(slot) {
    const value = slot?.celestial_helper || 'none';
    if (value === 'none') return null;
    if (value === 'random') return pick(celestialPool());
    return value;
  }

  function oracleFace(id) {
    const map = drawProfile.oracle_polarity || {};
    return map[id] === 'shadow' || map[id] === 'negative' ? 'shadow' : 'positive';
  }

  function rollLightShadow(mode) {
    const roll = Math.random();
    const lightChance = mode === 'random70' ? 0.7 : (mode === 'random60' ? 0.6 : 0.5);
    return roll < lightChance ? 'positive' : 'shadow';
  }

  function celestialHelperName(id) {
    const list = Array.isArray(gameConfig.celestial_oracles) ? gameConfig.celestial_oracles : [];
    const item = list.find((oracle) => oracle.id === id);
    if (!item) return id;
    return item[language] || item.en || item.hu || id;
  }

  function helperSrc(draw) {
    const tone = draw?.helperPolarity === 'shadow' ? 'shadow' : 'light';
    return `${packBase}/egi-orakulum-ls/figures/${draw.celestialHelper}-${tone}.png`;
  }

  function helperLabel(draw) {
    return `${t().helperCaption}: ${celestialHelperName(draw.celestialHelper)}`;
  }

  function helperCardHtml(draw) {
    if (!draw?.celestialHelper) return '';
    const name = celestialHelperName(draw.celestialHelper);
    return `<span class="celestial-helper"><span class="celestial-helper-label">${escapeHtml(helperLabel(draw))}</span><img class="celestial-helper-card" src="${helperSrc(draw)}" alt="${escapeHtml(name)}" decoding="async"></span>`;
  }

  function skyWord(variant) {
    return variant === 'shadow' ? 'shadow' : 'light';
  }

  function fateLabelHtml(draw) {
    const text = t();
    if (!draw || draw.polaritySource === 'none') return '';
    if (draw.polarityPending) {
      return `<p class="fate-label is-pending">${escapeHtml(text.fateAnalyzing)}</p>`;
    }
    const template = draw.polaritySource === 'astro' ? text.fateSky : text.fateRandom;
    return `<p class="fate-label ${polarityClass(draw.variant)}">${escapeHtml(template.replace(':value', skyWord(draw.variant)))}</p>`;
  }

  function paintFigure(img, draw, variant) {
    if (!img || !draw) return;
    const tone = variant === 'shadow' ? 'shadow' : 'positive';
    const polar = packHasPolarFigures(draw.deck);
    img.src = figureSrc(draw.deck, draw.figureId, polar ? tone : 'positive');
    img.classList.toggle('is-reversed', !polar && tone === 'shadow');
  }

  function tickPolarityPreview() {
    polarityFrame = polarityFrame === 'shadow' ? 'positive' : 'shadow';
    if (revealingDraw?.polarityPending) {
      paintFigure(els.drawRevealImage, revealingDraw, polarityFrame);
    }
    document.querySelectorAll('[data-polarity-pending="1"]').forEach((article) => {
      const img = article.querySelector('.card-figure');
      paintFigure(img, {
        deck: article.dataset.deck || '',
        figureId: article.dataset.figure || '',
      }, polarityFrame);
    });
  }

  function syncPolarityMotion() {
    const pending = Boolean(revealingDraw?.polarityPending)
      || selected.some((draw) => draw.polarityPending);
    if (!pending) {
      if (polarityTimer) {
        window.clearInterval(polarityTimer);
        polarityTimer = null;
      }
      return;
    }
    if (polarityTimer) return;
    polarityFrame = 'positive';
    polarityTimer = window.setInterval(tickPolarityPreview, 1000);
  }

  function settlePendingAsRandom() {
    let changed = false;
    selected.forEach((draw) => {
      if (!draw.polarityPending) return;
      draw.variant = randomVariant();
      draw.polarityPending = false;
      draw.polaritySource = 'random';
      changed = true;
    });
    if (!changed) return;
    renderChosen();
  }

  function normalizeSkyVariant(value) {
    const text = String(value || '').toLowerCase();
    if (text === 'shadow' || text === 'negative' || text === 'negatív') return 'shadow';
    if (text === 'light' || text === 'positive' || text === 'pozitív') return 'positive';
    return '';
  }

  function applySkyPolarities(rows) {
    const list = Array.isArray(rows) ? rows : [];
    const claimed = new Set();
    list.forEach((row) => {
      const variant = normalizeSkyVariant(row?.value ?? row?.polarity);
      if (!variant) return;
      let draw = null;
      const slotNo = Number(row?.slot);
      if (Number.isInteger(slotNo) && slotNo >= 1) draw = selected[slotNo - 1] || null;
      if (!draw) {
        const rowNo = Number(row?.row);
        const placeNo = Number(row?.place);
        draw = selected.find((item) => {
          const slot = slotDef(item.slotIndex);
          return Number(slot?.row) === rowNo && Number(slot?.place) === placeNo;
        }) || null;
      }
      if (!draw || !draw.polarityPending || claimed.has(draw)) return;
      claimed.add(draw);
      draw.variant = variant;
      draw.polarityPending = false;
      draw.polaritySource = 'astro';
    });
    selected.forEach((draw) => {
      if (!draw.polarityPending) return;
      draw.variant = randomVariant();
      draw.polarityPending = false;
      draw.polaritySource = 'random';
    });
    renderChosen();
  }

  function packSlug(key) {
    const value = String(key || '');
    if (value === 'oracle' || value === 'egi-orakulum') return 'egi-orakulum';
    if (value === 'tarot' || value === 'tarot-major') return 'tarot';
    if (value === 'cigany' || value === 'cigany-kartya') return 'cigany-kartya';
    return value;
  }

  function slotDef(index) {
    return gameSlots[index] || null;
  }

  function slotTitle(index) {
    const slot = slotDef(index);
    if (!slot) return '';
    if (language === 'en') return slot.title_en || slot.title || slot.title_hu || '';
    return slot.title_hu || slot.title || slot.title_en || '';
  }

  function slotDescription(index) {
    const slot = slotDef(index);
    if (!slot) return '';
    if (language === 'en') return slot.description_en || slot.description || slot.description_hu || '';
    return slot.description_hu || slot.description || slot.description_en || '';
  }

  function updateSlotCoach() {
    if (!els.slotCoach) return;
    if (!drawReady || revealing || selected.length >= SLOT_COUNT) {
      els.slotCoach.hidden = true;
      return;
    }
    const index = currentSlotIndex();
    if (els.slotCoachKicker) els.slotCoachKicker.textContent = t().slotCoachKicker;
    if (els.slotCoachTitle) els.slotCoachTitle.textContent = slotTitle(index).trim();
    if (els.slotCoachText) els.slotCoachText.textContent = slotDescription(index).trim();
    els.slotCoach.hidden = false;
  }

  function updateDrawPrompt() {
    updateSlotCoach();
    if (!els.deckLabel) return;
    els.deckLabel.textContent = '';
    els.deckLabel.hidden = true;
  }

  function drawSequence() {
    return gameSlots
      .map((slot, index) => ({
        index,
        order: Number(slot.draw_order) > 0 ? Number(slot.draw_order) : index + 1,
      }))
      .sort((a, b) => a.order - b.order || a.index - b.index);
  }

  function drawForSlot(index) {
    return selected.find((item) => item.slotIndex === index) || null;
  }

  function drawOrderOf(index) {
    const match = drawSequence().find((item) => item.index === index);
    return match ? match.order : index + 1;
  }

  function currentSlotIndex() {
    const taken = new Set(selected.map((item) => item.slotIndex));
    const next = drawSequence().find((item) => !taken.has(item.index));
    return next ? next.index : SLOT_COUNT;
  }

  function currentPackSlug() {
    return packSlug(slotDef(currentSlotIndex())?.pack_slug || gameSlots[0]?.pack_slug || 'egi-orakulum');
  }

  function roman(n) {
    const map = [
      [50, 'L'], [40, 'XL'], [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I'],
    ];
    let value = Math.max(1, Number(n) || 1);
    let out = '';
    map.forEach(([num, glyph]) => {
      while (value >= num) {
        out += glyph;
        value -= num;
      }
    });
    return out;
  }

  function groupedRows() {
    const map = new Map();
    gameSlots.forEach((slot, index) => {
      const row = Number(slot.row) || 1;
      if (!map.has(row)) map.set(row, []);
      map.get(row).push({ slot, index });
    });
    return [...map.entries()]
      .sort((a, b) => a[0] - b[0])
      .map(([row, items]) => ({
        row,
        items: items.sort((a, b) => (Number(a.slot.place) || 0) - (Number(b.slot.place) || 0)),
      }));
  }

  function currentDeck() {
    return decksBySlug[currentPackSlug()] || null;
  }

  function figureMeta(deckKey, figureId) {
    const deck = decksBySlug[packSlug(deckKey)];
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
    const oracle = (decksBySlug['egi-orakulum']?.figures || []).find((figure) => figure.id === object.id);
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
    return meaningCaptionHtml('tarot', figureId);
  }

  function meaningCaptionHtml(deckKey, figureId) {
    const meta = figureMeta(deckKey, figureId);
    const title = meta ? (meta[language] || meta.en || figureId) : figureId;
    const blurb = meta?.blurb?.[language] || meta?.blurb?.en || meta?.keyword?.[language] || '';
    return `
      <h3>${escapeHtml(title)}</h3>
      ${blurb ? `<p class="figure-blurb">${escapeHtml(blurb)}</p>` : ''}
    `;
  }

  function slotCaptionHtml(index, figureId) {
    const slot = slotDef(index);
    const mode = slot?.info_mode || (index === 0 ? 'astro' : 'meaning');
    const deckKey = packSlug(slot?.pack_slug || (index === 0 ? 'egi-orakulum' : 'tarot'));
    if (mode === 'astro') return oracleCaptionHtml(figureId);
    if (mode === 'meaning') return meaningCaptionHtml(deckKey, figureId);
    const meta = figureMeta(deckKey, figureId);
    const title = meta ? (meta[language] || meta.en || figureId) : figureId;
    return `<h3>${escapeHtml(title)}</h3>`;
  }

  function slotCaptionText(slot, figureId) {
    const tmp = document.createElement('div');
    tmp.innerHTML = slotCaptionHtml(slot, figureId);
    return tmp.textContent.replace(/\s+/g, ' ').trim();
  }

  function packSkin(deckKey) {
    const slug = packSlug(deckKey);
    return (gameConfig.packs && gameConfig.packs[slug]) || {};
  }

  function cardBackSrc(deckKey, slot) {
    if (slot?.card_back) return slot.card_back;
    const skin = packSkin(deckKey);
    if (skin.card_back) return skin.card_back;
    const slug = packSlug(deckKey);
    const deck = decksBySlug[slug];
    const relative = deck?.cardBack || 'card-back/card-back.png';
    return `${packBase}/${slug}/${relative}`;
  }

  function packHasPolarFigures(slug) {
    const key = packSlug(slug);
    if (gameConfig.packs && gameConfig.packs[key] && typeof gameConfig.packs[key].polar_figures === 'boolean') {
      return gameConfig.packs[key].polar_figures;
    }
    return decksBySlug[key]?.polarFigures === true;
  }

  function figureSrc(deckKey, figureId, variant) {
    const slug = packSlug(deckKey);
    if (packHasPolarFigures(slug)) {
      const tone = variant === 'shadow' ? 'shadow' : 'light';
      return `${packBase}/${slug}/figures/${figureId}-${tone}.png`;
    }
    if (slug === 'tarot') {
      if (figureId === 'significator') {
        return `${packBase}/${slug}/figures/significator.png`;
      }
      if (/^(wands|cups|swords|pentacles)-/.test(figureId)) {
        return `${packBase}/${slug}/figures/minors/${figureId}.png`;
      }
    }
    return `${packBase}/${slug}/figures/${figureId}.png`;
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

  function markGuestContinue() {
    continueAsGuest = true;
    try { sessionStorage.setItem(guestStorageKey, '1'); } catch (error) {}
    unlockGuestQuestion();
  }

  function unlockGuestQuestion() {
    if (!continueAsGuest || isAuthenticated || !els.question) return;
    els.question.readOnly = false;
    els.question.removeAttribute('readonly');
    els.questionWrap?.classList.remove('is-guest-gate');
    if (els.question.nextElementSibling) els.question.nextElementSibling.hidden = false;
    els.question.placeholder = t().placeholder;
  }

  function requireAuth(event, options = {}) {
    if (isAuthenticated) return false;
    if (options.allowGuest && continueAsGuest) return false;
    event?.preventDefault?.();
    event?.stopPropagation?.();
    els.question?.blur();
    pendingGuestAction = typeof options.onGuest === 'function' ? options.onGuest : null;
    setAuthModalOpen(true);
    return true;
  }

  function finishGuestContinue() {
    markGuestContinue();
    const action = pendingGuestAction;
    pendingGuestAction = null;
    setAuthModalOpen(false);
    action?.();
  }

  function gameDescription() {
    if (language === 'en') {
      return gameConfig.description_en || gameConfig.description || t().title;
    }
    return gameConfig.description_hu || gameConfig.description || t().title;
  }

  function applyCopy() {
    const text = t();
    const heading = gameDescription();
    const hint = drawSequence().map((item) => slotTitle(item.index)).filter(Boolean).join(' → ') || text.hint;
    if (els.menuButton) els.menuButton.setAttribute('aria-label', text.navLabel);
    if (els.questionClose) els.questionClose.setAttribute('aria-label', text.questionClose);
    const spreadName = language === 'en'
      ? (gameConfig.name_en || gameConfig.name || '')
      : (gameConfig.name_hu || gameConfig.name || '');
    const spreadKnown = drawReady || Boolean(readingSessionId);
    if (els.spreadKicker) {
      els.spreadKicker.hidden = !spreadKnown;
      els.spreadKicker.textContent = spreadKnown ? text.spreadKicker : '';
    }
    if (els.spreadSummary) {
      els.spreadSummary.textContent = spreadKnown
        ? text.spreadSummary.replace(':name', spreadName).replace(':count', String(SLOT_COUNT))
        : text.pickCard;
    }
    if (els.spreadOpen) els.spreadOpen.textContent = text.spreadChange;
    root.querySelectorAll('[data-copy]').forEach((node) => {
      const key = node.dataset.copy || '';
      if (text[key]) node.textContent = text[key];
    });
    root.querySelectorAll('[data-name-hu]').forEach((option) => {
      option.textContent = language === 'en'
        ? (option.dataset.nameEn || option.textContent)
        : (option.dataset.nameHu || option.textContent);
    });
    if (els.gameDescription) els.gameDescription.hidden = spreadName.trim() === '' && !els.spreadKicker;
    if (els.title && els.title !== els.gameDescription) els.title.textContent = heading;
    if (els.questionWrap) els.questionWrap.hidden = false;
    if (els.question) {
      els.question.placeholder = text.placeholder;
    }
    if (els.hint) els.hint.innerHTML = `<span>☾</span>${hint}`;
    els.resetButtons?.forEach((button) => {
      button.textContent = text.reset;
    });
    if (els.readingKicker) els.readingKicker.textContent = text.messageKicker;
    if (els.readingTitle) els.readingTitle.textContent = text.messageTitle;
    if (els.readingFollowupLabel) els.readingFollowupLabel.textContent = text.followupLabel;
    if (els.readingFollowupInput) els.readingFollowupInput.placeholder = text.followupPlaceholder;
    if (els.readingFollowupSend) els.readingFollowupSend.textContent = text.followupSend;
    if (els.readingFollowupExpand) els.readingFollowupExpand.textContent = text.followupExpand;
    if (els.feedbackComment) els.feedbackComment.placeholder = text.feedbackPlaceholder;
    const offerWhen = root.querySelector('[data-offer-when-label]');
    const offerCountry = root.querySelector('[data-offer-country-label]');
    const offerCity = root.querySelector('[data-offer-city-label]');
    const offerGender = root.querySelector('[data-offer-gender-label]');
    const offerIntro = root.querySelector('[data-offer-intro-label]');
    if (offerWhen) offerWhen.textContent = text.offerWhen;
    if (offerCountry) offerCountry.textContent = text.offerCountry;
    if (offerCity) offerCity.textContent = text.offerCity;
    if (offerGender) offerGender.textContent = text.offerGender;
    if (offerIntro) offerIntro.textContent = text.offerIntro;
    const female = root.querySelector('[data-offer-gender-female]');
    const male = root.querySelector('[data-offer-gender-male]');
    const other = root.querySelector('[data-offer-gender-other]');
    if (female) female.textContent = text.offerFemale;
    if (male) male.textContent = text.offerMale;
    if (other) other.textContent = text.offerOther;
    if (els.readingOfferYes) els.readingOfferYes.textContent = text.offerYes;
    if (els.readingOfferNo) els.readingOfferNo.textContent = text.offerNo;
    if (els.readingShareLabel) els.readingShareLabel.textContent = text.shareLabel;
    if (els.sharePdf) els.sharePdf.setAttribute('aria-label', text.sharePdf);
    if (els.sharePdf) els.sharePdf.setAttribute('title', text.sharePdf);
    if (els.shareEmail) els.shareEmail.setAttribute('aria-label', text.shareEmail);
    if (els.shareEmail) els.shareEmail.setAttribute('title', text.shareEmail);
    if (els.shareFacebook) els.shareFacebook.setAttribute('aria-label', text.shareFacebook);
    if (els.shareFacebook) els.shareFacebook.setAttribute('title', text.shareFacebook);
    if (els.counter) els.counter.innerHTML = `<b>${selected.length}</b>/${SLOT_COUNT} ${text.selected}`;
    updateDrawPrompt();
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
        (item) => packSlug(item.deck) === currentPackSlug() && item.backIndex === index
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

    const backSrc = cardBackSrc(currentPackSlug(), slotDef(currentSlotIndex()));
    const deckKey = `${currentPackSlug()}:${deck.figures.length}:${backSrc}`;
    if (!force && renderedDeckKey === deckKey && els.slider.children.length === deck.figures.length) {
      syncDeckSelection();
      return;
    }
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
      button.innerHTML = `<img src="${backSrc}" alt="" draggable="false" decoding="async">`;
      button.addEventListener('click', () => toggleBack(index));
      els.slider.appendChild(button);
    }

    els.slider.scrollLeft = 0;
    syncDeckSelection();
  }

  function renderChosen() {
    if (!els.chosen) return;
    const text = t();
    const maxPlaces = groupedRows().reduce((max, group) => Math.max(max, group.items.length), 1);
    els.chosen.innerHTML = '';
    els.chosen.className = 'chosen-grid';
    els.chosen.style.setProperty('--places', String(maxPlaces));

    groupedRows().forEach((group) => {
      const rowEl = document.createElement('div');
      rowEl.className = 'chosen-row';
      rowEl.dataset.places = String(group.items.length);

      group.items.forEach(({ slot, index }) => {
        const draw = drawForSlot(index);
        const article = document.createElement('article');
        article.className = `chosen-card${draw ? ' has-card' : ' is-placeholder'}${draw?.celestialHelper ? ' has-helper' : ''}`;
        article.dataset.slotIndex = String(index);
        const deckKey = packSlug(draw?.deck || slot.pack_slug);

        if (!draw) {
          article.innerHTML = `
          <h3 class="slot-title">${escapeHtml(slotTitle(index))}</h3>
          <p class="polarity-label is-light">—</p>
          <div class="card-art placeholder-art">
            <img class="card-back" src="${cardBackSrc(deckKey, slot)}" alt="" decoding="async">
            <span class="roman">${roman(drawOrderOf(index))}</span>
          </div>
          <div class="card-caption">
            <h3>${escapeHtml(text.waiting)}</h3>
          </div>
        `;
        } else {
          const pending = Boolean(draw.polarityPending);
          const paired = draw.polaritySource && draw.polaritySource !== 'none';
          const shownVariant = pending ? 'positive' : draw.variant;
          const polar = packHasPolarFigures(deckKey);
          const reversed = paired && !pending && !polar && draw.variant === 'shadow';
          article.dataset.polarityPending = pending ? '1' : '0';
          article.dataset.deck = deckKey;
          article.dataset.figure = draw.figureId;
          const polarityHtml = paired
            ? `<p class="polarity-label ${pending ? 'is-pending' : polarityClass(draw.variant)}">${pending ? escapeHtml(t().fateAnalyzing) : polarityLabel(draw.variant)}</p>`
            : '<p class="polarity-label is-light">—</p>';
          article.innerHTML = `
          <h3 class="slot-title">${escapeHtml(slotTitle(index))}</h3>
          ${polarityHtml}
          <div class="card-art is-full-bleed">
            <img class="card-figure${reversed ? ' is-reversed' : ''}" src="${figureSrc(draw.deck, draw.figureId, polar ? shownVariant : 'positive')}" alt="" decoding="async">
            <span class="roman">${roman(drawOrderOf(index))}</span>
            ${helperCardHtml(draw)}
          </div>
          <div class="card-caption">
            ${fateLabelHtml(draw)}
            ${slotCaptionHtml(index, draw.figureId)}
          </div>
        `;
        }

        rowEl.appendChild(article);
      });

      els.chosen.appendChild(rowEl);
    });

    syncCompleteView();
    syncPolarityMotion();
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
      const shown = hasQuestion && (lockedQuestion || questionText());
      els.askedQuestion.textContent = lockedQuestion || questionText();
      els.askedQuestion.hidden = !shown;
    }
    els.resetButtons?.forEach((button) => {
      button.hidden = !complete;
    });

    if (!complete) {
      clearReadingResult();
    }
  }

  function showReveal(draw) {
    if (!els.drawReveal || !els.drawRevealImage) return;
    revealingDraw = draw;
    const meta = figureMeta(draw.deck, draw.figureId);
    paintFigure(els.drawRevealImage, draw, draw.polarityPending ? 'positive' : draw.variant);
    els.drawRevealImage.alt = meta ? (meta[language] || meta.en || '') : '';
    if (els.drawRevealHelper) {
      if (draw.celestialHelper) {
        els.drawRevealHelper.hidden = false;
        els.drawRevealHelper.src = helperSrc(draw);
        els.drawRevealHelper.alt = celestialHelperName(draw.celestialHelper);
        if (els.drawRevealHelperWrap) els.drawRevealHelperWrap.hidden = false;
        if (els.drawRevealHelperLabel) els.drawRevealHelperLabel.textContent = helperLabel(draw);
      } else {
        els.drawRevealHelper.hidden = true;
        els.drawRevealHelper.removeAttribute('src');
        if (els.drawRevealHelperWrap) els.drawRevealHelperWrap.hidden = true;
        if (els.drawRevealHelperLabel) els.drawRevealHelperLabel.textContent = '';
      }
    }
    els.drawReveal.hidden = false;
    document.body.classList.add('is-revealing');
    syncPolarityMotion();
  }

  function hideReveal() {
    revealingDraw = null;
    if (els.drawReveal) els.drawReveal.hidden = true;
    if (els.drawRevealImage) {
      els.drawRevealImage.classList.remove('is-reversed');
      els.drawRevealImage.removeAttribute('src');
    }
    if (els.drawRevealHelper) {
      els.drawRevealHelper.hidden = true;
      els.drawRevealHelper.removeAttribute('src');
    }
    if (els.drawRevealHelperWrap) els.drawRevealHelperWrap.hidden = true;
    if (els.drawRevealHelperLabel) els.drawRevealHelperLabel.textContent = '';
    document.body.classList.remove('is-revealing');
    syncPolarityMotion();
  }

  function wait(ms) {
    return new Promise((resolve) => {
      settleTimer = window.setTimeout(() => {
        settleTimer = null;
        resolve();
      }, ms);
    });
  }

  function flyCardToSlot(draw, fromRect, src) {
    return new Promise((resolve) => {
      const art = els.chosen?.querySelector(`[data-slot-index="${draw.slotIndex}"] .card-art`);
      if (!art || !fromRect?.width || !src) {
        hideReveal();
        resolve();
        return;
      }
      const node = document.createElement('img');
      node.className = 'draw-flyer';
      node.alt = '';
      node.src = src;
      node.style.left = `${fromRect.left}px`;
      node.style.top = `${fromRect.top}px`;
      node.style.width = `${fromRect.width}px`;
      node.style.height = `${fromRect.height}px`;
      flyer = node;
      document.body.appendChild(node);
      hideReveal();
      const scrolling = document.documentElement;
      const previousScroll = scrolling.style.scrollBehavior;
      scrolling.style.scrollBehavior = 'auto';
      art.scrollIntoView({ block: 'center', inline: 'center', behavior: 'instant' });
      scrolling.style.scrollBehavior = previousScroll;
      const to = art.getBoundingClientRect();
      const motion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      let settled = false;
      const done = () => {
        if (settled) return;
        settled = true;
        node.remove();
        if (flyer === node) flyer = null;
        art.closest('.chosen-card')?.classList.remove('is-arriving');
        resolve();
      };
      if (motion || to.width < 2) {
        done();
        return;
      }
      node.addEventListener('transitionend', done, { once: true });
      window.setTimeout(done, 900);
      window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
          node.style.left = `${to.left}px`;
          node.style.top = `${to.top}px`;
          node.style.width = `${to.width}px`;
          node.style.height = `${to.height}px`;
        });
      });
    });
  }

  async function finishDraw(draw) {
    const token = drawToken;
    revealTimer = null;
    selected.push(draw);
    if (els.counter) els.counter.innerHTML = `<b>${selected.length}</b>/${SLOT_COUNT} ${t().selected}`;
    const from = els.drawRevealImage?.getBoundingClientRect();
    const src = els.drawRevealImage?.src || '';
    renderChosen();
    els.chosen?.querySelector(`[data-slot-index="${draw.slotIndex}"]`)?.classList.add('is-arriving');
    await flyCardToSlot(draw, from, src);
    if (token !== drawToken) return;
    await wait(1000);
    if (token !== drawToken) return;
    revealing = false;
    applyCopy();
    renderDeck(true);
    if (selected.length < SLOT_COUNT) {
      els.slider?.scrollTo({ left: 0, behavior: 'auto' });
      els.pickStage?.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }
    maybeRequestReading();
  }

  function toggleBack(backIndex) {
    if (!drawReady) {
      if (!readingSessionId) openQuestion();
      return;
    }
    if (revealing || selected.length >= SLOT_COUNT) return;
    const deckKey = currentPackSlug();
    if (selected.some((item) => packSlug(item.deck) === deckKey && item.backIndex === backIndex)) {
      return;
    }

    if (hasQuestion && selected.length === 0 && questionText().length < minQuestionChars) {
      els.question?.focus();
      showToast(needQuestionMessage(), false);
      return;
    }

    const deck = currentDeck();
    if (!deck) return;

    const slot = slotDef(currentSlotIndex());
    if (els.slotCoach) els.slotCoach.hidden = true;
    const helper = resolveCelestialHelper(slot);
    const shadowMode = slotLightShadow(slot);
    let variant = 'positive';
    let polarityPending = false;
    let polaritySource = 'none';
    if (shadowMode === 'llm') {
      polarityPending = true;
      polaritySource = 'llm';
    } else if (shadowMode === 'astro') {
      variant = helper ? oracleFace(helper) : 'positive';
      polaritySource = 'astro';
    } else if (shadowMode === 'random' || shadowMode === 'random60' || shadowMode === 'random70') {
      variant = rollLightShadow(shadowMode);
      polaritySource = 'random';
    }

    const used = selected.filter((item) => packSlug(item.deck) === deckKey).map((item) => item.figureId);
    const available = deck.figures.filter((figure) => !used.includes(figure.id));
    const pool = available.length ? available : deck.figures;
    const figure = pick(pool);

    const draw = {
      slotIndex: currentSlotIndex(),
      deck: deckKey,
      backIndex,
      figureId: figure.id,
      celestialHelper: helper,
      helperPolarity: helper ? oracleFace(helper) : '',
      variant,
      polarityPending,
      polaritySource,
    };
    revealing = true;
    showReveal(draw);
    revealTimer = window.setTimeout(() => finishDraw(draw), 2000);
  }

  function reset() {
    drawToken += 1;
    revealingDraw = null;
    if (polarityTimer) {
      window.clearInterval(polarityTimer);
      polarityTimer = null;
    }
    if (revealTimer) window.clearTimeout(revealTimer);
    if (settleTimer) window.clearTimeout(settleTimer);
    revealTimer = null;
    settleTimer = null;
    flyer?.remove();
    flyer = null;
    revealing = false;
    hideReveal();
    selected = [];
    lastReadingKey = '';
    activeReading = null;
    lockedQuestion = '';
    readingSessionId = '';
    drawReady = false;
    skyChoice = { user: null, relation: null };
    els.deckWrap?.classList.add('is-locked');
    if (els.slotCoach) els.slotCoach.hidden = true;
    if (els.consentModal) els.consentModal.hidden = true;
    if (els.questionModal) {
      els.questionModal.hidden = false;
      showQuestionNote('');
    }
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
      credentials: 'same-origin',
      headers: jsonHeaders(),
      body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));
    if (typeof data.credits === 'number') {
      updateCreditsBalance(data.credits);
    }
    if (response.status === 419) {
      throw new Error(t().sessionExpired);
    }
    if (!response.ok) {
      throw new Error(jsonErrorMessage(data, t().shareError));
    }
    return data;
  }

  async function downloadPdf() {
    const payload = exportPayload();
    if (!payload || !exportPdfUrl || shareBusy) return;
    const pdfFeature = featureState('reading.export.pdf');
    if (!pdfFeature.allowed) {
      showToast(pdfFeature.message || t().sharePdfLocked, false);
      return;
    }
    if (pdfFeature.will_charge && creditsBalance < pdfFeature.cost) {
      showToast(t().messageNeedCredits, false);
      return;
    }
    setShareBusy(true);
    showToast(t().shareBusy, true);
    try {
      const response = await fetch(exportPdfUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: jsonHeaders({ Accept: 'application/pdf' }),
        body: JSON.stringify(payload),
      });
      if (response.status === 419) {
        throw new Error(t().sessionExpired);
      }
      if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(jsonErrorMessage(data, t().shareError));
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
      const remaining = response.headers.get('X-Credits-Remaining');
      if (remaining !== null && remaining !== '') {
        updateCreditsBalance(Number.parseInt(remaining, 10));
      }
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
    const emailFeature = featureState('reading.export.email');
    if (!emailFeature.allowed) {
      showToast(emailFeature.message || t().sharePdfLocked, false);
      return;
    }
    if (emailFeature.will_charge && creditsBalance < emailFeature.cost) {
      showToast(t().messageNeedCredits, false);
      return;
    }
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
    const shareFeature = featureState('reading.share');
    if (!shareFeature.allowed) {
      showToast(shareFeature.message || t().shareError, false);
      return;
    }
    if (shareFeature.will_charge && creditsBalance < shareFeature.cost) {
      showToast(t().messageNeedCredits, false);
      return;
    }
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

  function formatSource(source) {
    const label = source?.label;
    if (!label) return '';
    return `${t().sourcePrefix}: ${label}`;
  }

  function setReadingSource(source) {
    if (!els.readingSource) return;
    const text = formatSource(source);
    els.readingSource.textContent = text;
    els.readingSource.hidden = !text;
  }

  function showReadingResult({ body, error = false, showFollowup = false, source = null }) {
    if (!els.readingResult || !els.readingBody) return;
    const text = t();
    hideToast();
    if (els.readingKicker) els.readingKicker.textContent = text.messageKicker;
    if (els.readingTitle) els.readingTitle.textContent = text.messageTitle;
    els.readingBody.classList.remove('is-loading');
    els.readingBody.classList.toggle('is-error', error);
    els.readingBody.textContent = body;
    setReadingSource(error ? null : source);
    els.readingResult.hidden = false;
    if (els.readingFollowup) {
      els.readingFollowup.hidden = !showFollowup || error;
      if (els.readingFollowupInput && showFollowup) {
        els.readingFollowupInput.value = '';
      }
      setFollowupBusy(false);
    }
    showShareActions(!error && Boolean(body));
    syncReadingFeedback();
    els.readingResult.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function resetReadingFeedback() {
    if (els.feedbackRating) els.feedbackRating.value = '';
    if (els.feedbackVerdict) els.feedbackVerdict.value = '';
    if (els.feedbackComment) els.feedbackComment.value = '';
    if (els.feedbackStatus) els.feedbackStatus.textContent = '';
  }

  function syncReadingFeedback() {
    if (!els.readingFeedback) return;
    const bodyVisible = Boolean(els.readingBody)
      && !els.readingBody.hidden
      && !els.readingBody.classList.contains('is-error')
      && (els.readingBody.textContent || '').trim() !== '';
    els.readingFeedback.hidden = !(bodyVisible && activeReading?.sessionId && feedbackUrl);
  }

  async function sendReadingFeedback() {
    const sessionId = activeReading?.sessionId || '';
    if (!feedbackUrl || !sessionId) return;
    const rating = els.feedbackRating?.value || '';
    const verdict = els.feedbackVerdict?.value || '';
    const comment = (els.feedbackComment?.value || '').trim();
    if (!rating && !verdict && !comment) {
      showToast(t().feedbackNeed, false);
      return;
    }
    if (els.feedbackSend) els.feedbackSend.disabled = true;
    try {
      const response = await fetch(feedbackUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify({
          session_id: sessionId,
          rating: rating ? Number(rating) : null,
          verdict: verdict || null,
          comment,
        }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(jsonErrorMessage(data, t().messageError));
      }
      if (els.feedbackStatus) els.feedbackStatus.textContent = t().feedbackSaved;
    } catch (error) {
      showToast(error?.message || t().messageError, false);
    } finally {
      if (els.feedbackSend) els.feedbackSend.disabled = false;
    }
  }

  function clearReadingResult() {
    if (els.readingResult) els.readingResult.hidden = true;
    if (els.readingBody) {
      els.readingBody.textContent = '';
      els.readingBody.classList.remove('is-loading', 'is-error');
    }
    setReadingSource(null);
    if (els.readingFollowup) els.readingFollowup.hidden = true;
    hideReadingOffer();
    resetReadingFeedback();
    syncReadingFeedback();
    showShareActions(false);
  }

  function hideReadingOffer() {
    pendingOffer = null;
    if (els.readingOffer) els.readingOffer.hidden = true;
    if (els.readingBody) els.readingBody.hidden = false;
  }

  function showReadingOffer(data) {
    const text = t();
    const need = data.need;
    pendingOffer = {
      need,
      requests: Array.isArray(data.requests) ? data.requests : [],
      reading: data.reading || '',
      sessionId: data.session_id || activeReading?.sessionId || '',
    };
    if (!els.readingOffer) return;
    const personal = need === 'birth' || need === 'relation';
    if (els.readingOfferText) {
      els.readingOfferText.textContent = need === 'relation'
        ? text.offerRelation
        : (need === 'projection' ? text.offerProjection : text.offerBirth);
    }
    if (els.readingOfferForm) els.readingOfferForm.hidden = !personal;
    if (els.readingBody) els.readingBody.hidden = personal;
    els.readingOffer.hidden = false;
    if (els.readingFollowup) els.readingFollowup.hidden = true;
    showShareActions(false);
    syncReadingFeedback();
  }

  function offerPayload() {
    if (!pendingOffer) return null;
    const need = pendingOffer.need;
    const refine = { need, requests: pendingOffer.requests };
    if (need !== 'birth' && need !== 'relation') return refine;
    const when = root.querySelector('[data-offer-at]')?.value || '';
    if (!when) {
      showToast(t().offerNeedWhen, false);
      return null;
    }
    const country = root.querySelector('[data-offer-country]')?.value || '';
    const city = root.querySelector('[data-offer-city]')?.value || '';
    const gender = root.querySelector('[data-offer-gender]')?.value || '';
    const intro = root.querySelector('[data-offer-intro]')?.value || '';
    if (need === 'birth') {
      refine.birth_at = when;
      refine.birth_country = country;
      refine.birth_city = city;
      refine.gender = gender;
      refine.introduction = intro;
    } else {
      refine.relation_at = when;
      refine.relation_country = country;
      refine.relation_city = city;
      refine.relation_gender = gender;
      refine.relation_introduction = intro;
    }
    return refine;
  }

  function setFollowupBusy(busy) {
    if (els.readingFollowupSend) els.readingFollowupSend.disabled = busy;
    if (els.readingFollowupExpand) els.readingFollowupExpand.disabled = busy;
    if (els.readingFollowupInput) els.readingFollowupInput.disabled = busy;
    if (els.readingOfferYes) els.readingOfferYes.disabled = busy;
    if (els.readingOfferNo) els.readingOfferNo.disabled = busy;
  }

  function appendReadingText(extra, mode = 'expand', source = null) {
    if (!els.readingBody || !extra) return;
    const current = els.readingBody.textContent || '';
    els.readingBody.textContent = current
      ? `${current}\n\n———\n\n${extra}`
      : extra;
    setReadingSource(source);
    if (activeReading) {
      activeReading.reading = els.readingBody.textContent;
      activeReading.answers = activeReading.answers || [];
      activeReading.answers.push({
        mode,
        label: answerLabel(mode),
        text: extra,
        source: source?.label || '',
      });
    }
    showShareActions(true);
  }

  function buildCardsPayload() {
    return selected.map((draw) => {
      const slot = draw.slotIndex;
      const meta = figureMeta(draw.deck, draw.figureId);
      const pending = Boolean(draw.polarityPending);
      const polarity = pending ? '' : (draw.variant === 'shadow' ? 'shadow' : 'positive');
      return {
        figure_id: draw.figureId,
        figure_name: meta ? meta[language] : draw.figureId,
        keyword: meta?.keyword ? meta.keyword[language] : '',
        blurb: meta?.blurb ? (meta.blurb[language] || meta.blurb.en || '') : '',
        variant: pending ? '' : draw.variant,
        polarity,
        light_shadow_request: pending || draw.polaritySource === 'llm',
        celestial_helper: draw.celestialHelper || '',
        celestial_polarity: draw.helperPolarity || '',
        deck: packSlug(draw.deck),
        row: Number(slotDef(slot)?.row) || 1,
        place: Number(slotDef(slot)?.place) || (slot + 1),
        slot_title: slotTitle(slot),
        info_mode: slotDef(slot)?.info_mode || '',
        astro_caption: slotCaptionText(slot, draw.figureId).slice(0, 8000),
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
    const scoring = (drawProfile.scoring && typeof drawProfile.scoring === 'object')
      ? drawProfile.scoring
      : {};
    return {
      sun_sign: drawProfile.sun_sign || null,
      element: drawProfile.element || null,
      modality: drawProfile.modality || null,
      tone: drawProfile.tone || null,
      tones: drawProfile.tones || [],
      variants: drawProfile.variants || [],
      datetime_utc: drawProfile.datetime_utc || null,
      chart_mode: drawProfile.chart_mode || null,
      scoring,
    };
  }

  async function requestReading({ mode = 'initial', followUp = '', refine = null } = {}) {
    if (!readingUrl || selected.length !== SLOT_COUNT) return;

    const feature = featureState(readingFeatureKey(mode));
    if (!feature.allowed) {
      showToast(feature.message || t().messageNeedCredits, false);
      if (mode === 'initial') settlePendingAsRandom();
      return;
    }
    if (feature.will_charge && creditsBalance < feature.cost) {
      showToast(t().messageNeedCredits, false);
      if (mode === 'initial') settlePendingAsRandom();
      return;
    }

    const question = hasQuestion
      ? questionText()
      : (language === 'en' ? 'No question for this game.' : 'Ehhez a játékhoz nincs kérdés.');
    if (hasQuestion && question.length < minQuestionChars) {
      showToast(needQuestionMessage(), false);
      els.question?.focus();
      return;
    }

    if (!llmEnabled) {
      showToast(t().messageLlmDisabled, false);
      if (mode === 'initial') settlePendingAsRandom();
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
        credentials: 'same-origin',
        headers: jsonHeaders(),
        signal: controller.signal,
        body: JSON.stringify({
          question,
          locale: language,
          mode,
          game_id: gameId || undefined,
          follow_up: followUp || undefined,
          previous_reading: mode === 'initial' ? undefined : (pendingOffer?.reading || activeReading?.reading),
          session_id: mode === 'initial'
            ? (readingSessionId || undefined)
            : (pendingOffer?.sessionId || activeReading?.sessionId || undefined),
          sky_user: mode === 'initial' && readingSessionId ? (skyChoice.user || undefined) : undefined,
          sky_relation: mode === 'initial' && readingSessionId ? (skyChoice.relation || undefined) : undefined,
          cards,
          astro: readingAstroPayload(),
          refine: mode === 'refine' ? refine : undefined,
        }),
      });

      const data = await response.json().catch(() => ({}));
      if (typeof data.credits === 'number') {
        updateCreditsBalance(data.credits);
      }
      if (!response.ok) {
        if (response.status === 419) {
          throw new Error(t().sessionExpired);
        }
        if (response.status === 401) {
          throw new Error(jsonErrorMessage(data, t().messageNeedAuth));
        }
        if (response.status === 402) {
          throw new Error(jsonErrorMessage(data, t().messageNeedCredits));
        }
        throw new Error(jsonErrorMessage(data, t().messageError));
      }

      if (!data.reading && !data.need) {
        throw new Error(t().messageError);
      }

      if (mode === 'initial') {
        applySkyPolarities(data.light_shadow || []);
        activeReading = {
          question,
          cards: buildCardsPayload(),
          reading: data.reading || '',
          answers: [{
            mode: 'initial',
            label: answerLabel('initial'),
            text: data.reading || '',
            source: data.source?.label || '',
          }],
          sessionId: data.session_id || '',
        };
        syncCompleteView();
        showReadingResult({
          body: data.reading || '',
          error: false,
          showFollowup: !data.need,
          source: data.source || null,
        });
        if (data.need) showReadingOffer(data);
        hideToast();
      } else if (mode === 'refine') {
        hideReadingOffer();
        if (els.readingBody) els.readingBody.hidden = false;
        if (activeReading) activeReading.reading = data.reading || activeReading.reading;
        showReadingResult({
          body: data.reading || '',
          error: false,
          showFollowup: true,
          source: data.source || null,
        });
        hideToast();
      } else {
        appendReadingText(data.reading, mode, data.source || null);
        if (els.readingFollowupInput) els.readingFollowupInput.value = '';
        hideToast();
      }
    } catch (error) {
      if (error?.name === 'AbortError') return;
      if (mode === 'initial') {
        lastReadingKey = '';
        settlePendingAsRandom();
      }
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
    function syncSpreadPacks() {
      const types = (els.spreadGame?.selectedOptions?.[0]?.dataset.types || '').split(',').filter(Boolean);
      root.querySelectorAll('[data-pack-type]').forEach((row) => {
        const visible = types.length === 0 || types.includes(row.dataset.packType || '');
        row.hidden = !visible;
        row.querySelectorAll('select').forEach((select) => {
          select.disabled = !visible;
        });
      });
    }

    function syncSpreadBack() {
      if (!els.spreadBack || !els.spreadBackPreview) return;
      const option = els.spreadBack.selectedOptions?.[0];
      const src = option?.dataset.preview || '';
      if (!src) {
        els.spreadBackPreview.hidden = true;
        els.spreadBackPreview.removeAttribute('src');
        return;
      }
      els.spreadBackPreview.hidden = false;
      els.spreadBackPreview.src = src;
    }

    els.spreadOpen?.addEventListener('click', () => {
      if (!els.spreadPanel) return;
      els.spreadPanel.hidden = !els.spreadPanel.hidden;
      if (!els.spreadPanel.hidden) syncSpreadPacks();
    });
    els.spreadPanel?.querySelector('[data-spread-close]')?.addEventListener('click', () => {
      if (els.spreadPanel) els.spreadPanel.hidden = true;
    });
    els.spreadGame?.addEventListener('change', () => {
      root.querySelectorAll('[data-pack-type] select').forEach((select) => {
        select.value = '';
      });
      syncSpreadPacks();
    });
    els.spreadBack?.addEventListener('change', syncSpreadBack);
    syncSpreadPacks();
    syncSpreadBack();

    els.menuButton?.addEventListener('click', () => setMenuOpen(true));
    els.menuClose?.addEventListener('click', () => setMenuOpen(false));
    els.menuBackdrop?.addEventListener('click', () => setMenuOpen(false));
    window.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        setMenuOpen(false);
        pendingGuestAction = null;
        setAuthModalOpen(false);
        if (els.spreadPanel) els.spreadPanel.hidden = true;
        hideCreditsWelcome();
        hideToast();
      }
    });

    els.readingToast?.querySelector('[data-reading-toast-close]')?.addEventListener('click', hideToast);
    root.querySelector('[data-credits-welcome-close]')?.addEventListener('click', hideCreditsWelcome);

    root.querySelectorAll('[data-auth-guest]').forEach((node) => {
      node.addEventListener('click', () => {
        finishGuestContinue();
      });
    });
    window.addEventListener('astrotarot:guest', () => {
      finishGuestContinue();
    });

    root.querySelectorAll('[data-auth-open]').forEach((node) => {
      node.addEventListener('click', () => {
        setMenuOpen(false);
        setAuthModalOpen(true);
      });
    });
    root.querySelectorAll('[data-auth-close]').forEach((node) => {
      node.addEventListener('click', () => {
        pendingGuestAction = null;
        setAuthModalOpen(false);
      });
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
    els.readingOfferYes?.addEventListener('click', () => {
      const refine = offerPayload();
      if (!refine) return;
      requestReading({ mode: 'refine', refine });
    });
    els.readingOfferNo?.addEventListener('click', () => {
      const held = pendingOffer?.reading || '';
      hideReadingOffer();
      if (els.readingBody) {
        els.readingBody.hidden = false;
        els.readingBody.textContent = held;
      }
      if (activeReading) activeReading.reading = held;
      if (els.readingFollowup) els.readingFollowup.hidden = !held;
      showShareActions(Boolean(held));
      syncReadingFeedback();
    });
    els.readingFeedback?.addEventListener('submit', (event) => event.preventDefault());
    els.feedbackSend?.addEventListener('click', () => { sendReadingFeedback(); });

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
      if (els.consentModal && !els.consentModal.hidden && els.consentAsk && !els.consentAsk.hidden && els.consentLead) {
        els.consentLead.textContent = consentNeeds.hasUser ? t().consentSaved : t().consentMissing;
      }
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
      updateDrawPrompt();
      clearTimeout(questionTimer);
      questionTimer = setTimeout(() => {
        if (selected.length === SLOT_COUNT) maybeRequestReading();
      }, 600);
    });

    els.resetButtons?.forEach((button) => {
      button.addEventListener('click', (event) => {
        if (requireAuth(event, { onGuest: () => reset() })) return;
        reset();
      });
    });

    els.questionForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      submitQuestion();
    });
    els.questionClose?.addEventListener('click', () => closeQuestion());
    els.questionModal?.addEventListener('click', (event) => {
      if (event.target === els.questionModal) closeQuestion();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && els.questionModal && !els.questionModal.hidden) closeQuestion();
    });
    els.consentYes?.addEventListener('click', () => acceptConsent());
    els.consentNo?.addEventListener('click', () => declineConsent());
    els.consentSkip?.addEventListener('click', () => declineConsent());
    els.consentForm?.addEventListener('submit', (event) => {
      event.preventDefault();
      submitConsent();
    });
    els.consentRelationSelect?.addEventListener('change', () => {
      if (els.consentRelationNew) els.consentRelationNew.hidden = els.consentRelationSelect.value !== 'new';
    });
  }

  function openQuestion() {
    if (!els.questionModal || drawReady || readingSessionId) return;
    els.questionModal.hidden = false;
    els.deckWrap?.classList.add('is-locked');
  }

  function closeQuestion() {
    if (!els.questionModal || els.questionModal.hidden || drawReady) return;
    els.questionModal.hidden = true;
    if (!readingSessionId) els.deckWrap?.classList.remove('is-locked');
  }

  function showQuestionNote(message) {
    if (!els.questionNote) return;
    els.questionNote.textContent = message || '';
  }

  function showConsentNote(message) {
    if (!els.consentNote) return;
    els.consentNote.textContent = message || '';
  }

  function startDraw() {
    drawReady = true;
    els.deckWrap?.classList.remove('is-locked');
    if (els.questionModal) els.questionModal.hidden = true;
    if (els.consentModal) els.consentModal.hidden = true;
    applyCopy();
    renderChosen();
    renderDeck(true);
    updateDrawPrompt();
  }

  async function ensureDecks(slots) {
    const slugs = [...new Set(slots.map((slot) => packSlug(slot.pack_slug)))].filter((slug) => !decksBySlug[slug]);
    if (!slugs.length) return;
    const responses = await Promise.all(slugs.map((slug) => fetch(`${packBase}/${slug}/deck.json`)));
    if (responses.some((response) => !response.ok)) {
      throw new Error('Deck assets unavailable');
    }
    await Promise.all(responses.map(async (response, index) => {
      decksBySlug[slugs[index]] = await response.json();
    }));
  }

  async function applyMatchedGame(config) {
    if (config && typeof config === 'object') gameConfig = config;
    if (Array.isArray(gameConfig.slots) && gameConfig.slots.length) {
      gameSlots = gameConfig.slots;
      SLOT_COUNT = gameSlots.length;
    }
    gameId = gameConfig.id || gameId;
    hasQuestion = true;
    await ensureDecks(gameSlots);
    applyCopy();
    renderChosen();
    renderDeck(true);
  }

  function birthFields(prefix) {
    const at = root.querySelector(`[data-consent-${prefix}-at]`)?.value || '';
    const country = root.querySelector(`[data-consent-${prefix}-country]`)?.value?.trim() || '';
    const city = root.querySelector(`[data-consent-${prefix}-city]`)?.value?.trim() || '';
    const introduction = root.querySelector(`[data-consent-${prefix}-intro]`)?.value?.trim() || '';
    const label = prefix === 'relation'
      ? (root.querySelector('[data-consent-relation-label]')?.value?.trim() || '')
      : '';
    if (!at) return null;
    const fields = { birth_at: at };
    if (country) fields.birth_country = country;
    if (city) fields.birth_city = city;
    if (introduction) fields.introduction = introduction;
    if (label) fields.label = label;
    return fields;
  }

  async function saveBirthProfile(role, fields) {
    const response = await fetch(birthProfileUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: jsonHeaders(),
      body: JSON.stringify({ role, ...fields }),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(jsonErrorMessage(data, t().questionFailed));
    }
    return data;
  }

  function fillRelationChoices() {
    if (!els.consentRelationSelect) return;
    const text = t();
    const options = [`<option value="">${text.consentNone}</option>`];
    consentNeeds.profiles.forEach((profile) => {
      const label = String(profile.label || '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
      }[char]));
      options.push(`<option value="${profile.id}">${label}</option>`);
    });
    options.push(`<option value="new">${text.consentNew}</option>`);
    els.consentRelationSelect.innerHTML = options.join('');
    if (els.consentRelationNew) els.consentRelationNew.hidden = true;
  }

  function openConsent(data) {
    consentNeeds = {
      user: Boolean(data.needs_user),
      relation: Boolean(data.needs_relation),
      hasUser: Boolean(data.has_user),
      profiles: Array.isArray(data.profiles) ? data.profiles : [],
    };
    skyChoice = { user: null, relation: null };
    if (els.questionModal) els.questionModal.hidden = true;
    if (els.consentModal) els.consentModal.hidden = false;
    if (els.consentAsk) els.consentAsk.hidden = false;
    if (els.consentForm) els.consentForm.hidden = true;
    if (els.consentLead) {
      els.consentLead.textContent = consentNeeds.hasUser ? t().consentSaved : t().consentMissing;
    }
    showConsentNote('');
  }

  function declineConsent() {
    skyChoice = { user: null, relation: null };
    startDraw();
  }

  function acceptConsent() {
    if (consentNeeds.hasUser && consentNeeds.user && !consentNeeds.relation) {
      skyChoice = { user: { source: 'self' }, relation: null };
      startDraw();
      return;
    }
    if (els.consentAsk) els.consentAsk.hidden = true;
    if (els.consentForm) els.consentForm.hidden = false;
    if (els.consentSavedNote) {
      els.consentSavedNote.hidden = !(consentNeeds.hasUser && consentNeeds.user);
      els.consentSavedNote.textContent = t().consentSavedNote;
    }
    if (els.consentUserBlock) els.consentUserBlock.hidden = !(consentNeeds.user && !consentNeeds.hasUser);
    if (els.consentRelationBlock) els.consentRelationBlock.hidden = !consentNeeds.relation;
    fillRelationChoices();
    showConsentNote('');
  }

  async function submitConsent() {
    showConsentNote('');
    try {
      let user = null;
      if (consentNeeds.user) {
        if (consentNeeds.hasUser) {
          user = { source: 'self' };
        } else {
          const fields = birthFields('user');
          if (!fields) {
            showConsentNote(t().consentNeedBirth);
            return;
          }
          if (isAuthenticated && birthProfileUrl) {
            await saveBirthProfile('self', fields);
            user = { source: 'self' };
          } else {
            user = fields;
          }
        }
      }

      let relation = null;
      if (consentNeeds.relation) {
        const choice = els.consentRelationSelect?.value || '';
        if (choice === 'new') {
          const fields = birthFields('relation');
          if (!fields) {
            showConsentNote(t().consentNeedBirth);
            return;
          }
          if (!fields.label) {
            showConsentNote(t().consentNeedName);
            return;
          }
          if (isAuthenticated && birthProfileUrl) {
            const saved = await saveBirthProfile('relation', fields);
            relation = { profile_id: saved.id };
          } else {
            relation = fields;
          }
        } else if (choice) {
          relation = { profile_id: Number(choice) };
        }
      }

      skyChoice = { user, relation };
      startDraw();
    } catch (error) {
      showConsentNote(error?.message || t().questionFailed);
    }
  }

  async function submitQuestion() {
    const question = (els.question?.value || '').trim();
    if (question.length < minQuestionChars) {
      showQuestionNote(t().drawNeedQuestion.replace('{n}', String(minQuestionChars)));
      els.question?.focus();
      return;
    }
    if (!classifyUrl) {
      showQuestionNote(t().questionFailed);
      return;
    }
    if (els.questionSend) els.questionSend.disabled = true;
    showQuestionNote(t().questionWorking);
    try {
      const response = await fetch(classifyUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify({ question, locale: language }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(jsonErrorMessage(data, t().questionFailed));
      }
      if (data.status === 'unclear') {
        showQuestionNote(t().questionUnclear);
        return;
      }
      if (data.status === 'unmatched') {
        showQuestionNote(t().questionUnmatched);
        return;
      }
      if (data.status !== 'ready' || !data.game) {
        throw new Error(t().questionFailed);
      }
      readingSessionId = data.session_id || '';
      lockedQuestion = question;
      await applyMatchedGame(data.game);
      if (data.needs_user || data.needs_relation) {
        openConsent(data);
      } else {
        skyChoice = { user: null, relation: null };
        startDraw();
      }
    } catch (error) {
      showQuestionNote(error?.message || t().questionFailed);
    } finally {
      if (els.questionSend) els.questionSend.disabled = false;
    }
  }

  async function boot() {
    if (els.language) els.language.value = language;
    applyCopy();
    bindUiEvents();
    if (!isAuthenticated) markGuestContinue();

    try {
      await ensureDecks(gameSlots);
      renderDeck(true);
      renderChosen();
      if (els.questionModal) els.questionModal.hidden = false;
      els.deckWrap?.classList.add('is-locked');
    } catch (error) {
      console.error(error);
      showToast(language === 'en'
        ? 'Card decks could not be loaded. Please refresh.'
        : 'A paklik nem töltődtek be. Frissítsd az oldalt.', false);
    }
  }

  boot();
})();
