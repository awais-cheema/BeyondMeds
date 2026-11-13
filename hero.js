(() => {
  'use strict';

  const story = document.querySelector('.film-story');
  const stage = story.querySelector('.film-stage');
  const canvas = story.querySelector('#comfort-frames');
  const poster = story.querySelector('.film-poster');
  const loader = document.querySelector('.site-loader');
  const status = loader.querySelector('.loader-status');
  const retry = loader.querySelector('.loader-retry');
  const continueButton = loader.querySelector('.loader-continue');
  const main = document.querySelector('main');
  const footer = document.querySelector('footer');
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  const mobile = matchMedia('(max-width:700px)').matches;
  const sources = window.BEYONDMEDS_HERO_FRAMES || [];
  const frames = Array(sources.length);
  const context = canvas.getContext('2d', { alpha: true });
  const workers = mobile || (navigator.deviceMemory || 8) <= 4 ? 4 : 6;

  let loaded = 0, ready = false, animated = false, active = true;
  let start = 0, travel = 1, display = 0, last = -1, lastTime = 0;
  let raf = 0, lenis;

  function measure() {
    start = story.getBoundingClientRect().top + scrollY;
    travel = Math.max(1, story.offsetHeight - stage.offsetHeight);
    schedule();
  }

  function updateProgress() {
    loader.style.setProperty('--loaded', loaded / sources.length);
    status.textContent = `${Math.round(loaded / sources.length * 100)}%`;
  }

  async function decode(index) {
    const image = new Image();
    image.decoding = 'async';
    image.src = sources[index];
    let timeout;
    try {
      await Promise.race([
        image.decode(),
        new Promise((_, reject) => {
          timeout = setTimeout(() => reject(new Error('Frame download timed out')), 30000);
        })
      ]);
      frames[index] = typeof createImageBitmap === 'function' ? await createImageBitmap(image) : image;
      loaded++;
      updateProgress();
    } finally {
      clearTimeout(timeout);
      if (frames[index] !== image) image.removeAttribute('src');
    }
  }

  function draw(progress) {
    const position = progress * (frames.length - 1);
    const index = Math.floor(position);
    const mix = position - index;
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.globalAlpha = mix > .001 ? 1 - mix : 1;
    context.drawImage(frames[index], 0, 0);
    if (mix > .001) {
      context.globalAlpha = mix;
      context.globalCompositeOperation = 'lighter';
      context.drawImage(frames[index + 1], 0, 0);
      context.globalCompositeOperation = 'source-over';
    }
    context.globalAlpha = 1;
    canvas.dataset.frame = String(index);
    last = progress;
  }

  function schedule() {
    if (ready && !raf && !document.hidden) raf = requestAnimationFrame(tick);
  }

  function tick(time) {
    raf = 0;
    lenis?.raf(time);
    let moving = false;
    if (animated && active && !reduce.matches) {
      const target = Math.max(0, Math.min(1, (scrollY - start) / travel));
      const elapsed = Math.min(50, lastTime ? time - lastTime : 16.7);
      display += (target - display) * (1 - Math.exp(-elapsed / 36));
      if (Math.abs(target - display) < .0001) display = target;
      if (display !== last) draw(display);
      moving = display !== target;
    }
    lastTime = time;
    if (lenis || moving) schedule();
  }

  function setupMotion() {
    lenis?.destroy();
    lenis = !reduce.matches && window.Lenis ? new window.Lenis({
      autoRaf: false, lerp: .11, wheelMultiplier: 1, smoothWheel: true,
      syncTouch: false, anchors: true, allowNestedScroll: true
    }) : null;
    window.beyondMedsLenis = lenis;
    stage.classList.toggle('film-ready', animated && !reduce.matches);
    schedule();
  }

  function reveal(playAnimation) {
    animated = playAnimation;
    if (!animated) {
      frames.forEach(frame => frame.close?.());
      frames.fill(undefined);
    }
    if (animated) draw(0);
    ready = true;
    document.documentElement.classList.remove('hero-loading');
    main.inert = false;
    footer.inert = false;
    document.querySelector('.whatsapp-float-container')?.removeAttribute('inert');
    setupMotion();
    measure();
    loader.classList.add('is-ready');
    window.dispatchEvent(new Event('beyondmeds:hero-ready'));
    setTimeout(() => loader.remove(), 700);
  }

  async function preload() {
    retry.hidden = true;
    continueButton.hidden = true;
    if (reduce.matches) {
      await poster.decode().catch(() => {});
      reveal(false);
      return;
    }
    if (!context || !sources.length) {
      showError();
      return;
    }
    updateProgress();
    let next = 0, failed = false;
    async function loadFrames() {
      while (!failed && next < sources.length) {
        const index = next++;
        if (frames[index]) continue;
        try {
          await decode(index);
        } catch {
          failed = true;
        }
      }
    }
    await Promise.all(Array.from({ length: workers }, loadFrames));
    if (loaded === sources.length) reveal(true);
    else showError();
  }

  function showError() {
    status.textContent = 'The animation couldn’t finish loading.';
    retry.hidden = false;
    continueButton.hidden = false;
    retry.focus();
  }

  canvas.width = mobile ? 622 : 910;
  canvas.height = mobile ? 525 : 768;
  retry.addEventListener('click', preload);
  continueButton.addEventListener('click', () => reveal(false));
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(([entry]) => {
      active = entry.isIntersecting;
      lastTime = 0;
      schedule();
    }, { rootMargin: '150px' }).observe(story);
  }
  addEventListener('scroll', schedule, { passive: true });
  addEventListener('resize', measure, { passive: true });
  reduce.addEventListener('change', () => { if (ready) setupMotion(); });
  document.addEventListener('visibilitychange', () => {
    lastTime = 0;
    if (document.hidden) {
      cancelAnimationFrame(raf);
      raf = 0;
    } else schedule();
  });
  document.documentElement.classList.add('hero-loading');
  main.inert = true;
  footer.inert = true;
  document.querySelector('.whatsapp-float-container')?.setAttribute('inert', '');
  preload();
})();
