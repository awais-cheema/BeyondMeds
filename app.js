// Small interactions for the static storefront. Page markup and imagery remain unchanged.
(() => {
  const header = document.querySelector('#header');
  const navbar = header?.querySelector('.navbar');
  const menuButton = header?.querySelector('.navbar-hamburger');
  const cart = document.querySelector('.cart-drawer');
  const cartButton = header?.querySelector('button.navbar-menu-list-item-link');

  function closeMenu() {
    navbar?.classList.remove('active');
    menuButton?.setAttribute('aria-expanded', 'false');
  }

  menuButton?.setAttribute('role', 'button');
  menuButton?.setAttribute('tabindex', '0');
  menuButton?.setAttribute('aria-label', 'Open navigation');
  menuButton?.setAttribute('aria-expanded', 'false');
  const toggleMenu = () => {
    const open = navbar?.classList.toggle('active') ?? false;
    menuButton?.setAttribute('aria-expanded', String(open));
    menuButton?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
  };
  menuButton?.addEventListener('click', toggleMenu);
  menuButton?.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      toggleMenu();
    }
  });
  navbar?.querySelectorAll('.navbar-menu a').forEach(link => link.addEventListener('click', closeMenu));

  const closeCart = () => cart?.classList.remove('active');
  cartButton?.addEventListener('click', () => cart?.classList.add('active'));
  cart?.querySelector('.cart-drawer-overlay')?.addEventListener('click', closeCart);
  cart?.querySelector('.cart-drawer__header-close')?.addEventListener('click', closeCart);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      closeMenu();
      closeCart();
    }
  });

  const updateHeader = () => header?.classList.toggle('active', window.scrollY > 60);
  window.addEventListener('scroll', updateHeader, { passive: true });
  updateHeader();

  const exploreParallaxImages = [...document.querySelectorAll('#explore .half__grid-img .parallax-image')];
  const exploreReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  if (exploreParallaxImages.length && 'IntersectionObserver' in window) {
    const imageMotion = exploreParallaxImages.map(element => ({
      element,
      baseTop: null,
      visible: false
    }));

    const updateExploreParallax = () => {
      imageMotion.forEach(item => {
        if (!item.visible || exploreReducedMotion.matches) {
          item.element.style.setProperty('--explore-parallax-y', '0px');
          return;
        }
        const bounds = item.element.parentElement.getBoundingClientRect();
        if (item.baseTop === null) item.baseTop = bounds.top;
        const relativeScroll = item.baseTop - bounds.top;
        const parallaxOffset = relativeScroll * 0.25;
        item.element.style.setProperty('--explore-parallax-y', `${parallaxOffset.toFixed(2)}px`);
      });
    };

    const exploreObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        const item = imageMotion.find(candidate => candidate.element === entry.target);
        if (item) item.visible = entry.isIntersecting;
      });
      updateExploreParallax();
    });
    imageMotion.forEach(item => exploreObserver.observe(item.element));
    window.addEventListener('scroll', updateExploreParallax, { passive: true });
    window.addEventListener('resize', updateExploreParallax);
    exploreReducedMotion.addEventListener('change', updateExploreParallax);
  }

  const ethosImage = document.querySelector('#ethos .ethos__image');
  if (ethosImage && 'IntersectionObserver' in window) {
    const ethosSection = ethosImage.closest('#ethos');
    let ethosBaseTop = null;
    let ethosVisible = false;
    const updateEthosParallax = () => {
      if (exploreReducedMotion.matches) {
        ethosImage.style.setProperty('--ethos-parallax-y', '0px');
        return;
      }
      if (!ethosVisible) return;
      const bounds = ethosSection.getBoundingClientRect();
      if (ethosBaseTop === null) ethosBaseTop = bounds.top;
      const maxOffset = 120;
      const offset = Math.max(-maxOffset, Math.min(maxOffset, (bounds.top - ethosBaseTop) * 0.4));
      ethosImage.style.setProperty('--ethos-parallax-y', `${offset.toFixed(2)}px`);
    };
    const ethosObserver = new IntersectionObserver(([entry]) => {
      ethosVisible = entry.isIntersecting;
      updateEthosParallax();
    }, { rootMargin: '0px 0px -20% 0px' });
    ethosObserver.observe(ethosImage);
    window.addEventListener('scroll', updateEthosParallax, { passive: true });
    window.addEventListener('resize', updateEthosParallax);
    exploreReducedMotion.addEventListener('change', updateEthosParallax);
  }

  document.querySelectorAll('#explore .migraine-cap-carousel, #explore .varnaya-carousel').forEach(dragSurface => {
    const carousel = dragSurface.querySelector('.swiper');
    const track = carousel?.querySelector('.swiper-wrapper');
    if (!carousel || !track || !track.children.length) return;

    const originalCardCount = track.children.length;
    [...track.children].forEach(card => {
      const clone = card.cloneNode(true);
      clone.setAttribute('aria-hidden', 'true');
      clone.inert = true;
      clone.querySelectorAll('[id]').forEach(element => element.removeAttribute('id'));
      track.append(clone);
    });

    const loopLength = track.children[originalCardCount]?.offsetLeft || track.scrollWidth / 2;
    let pointerId = null;
    let startX = 0;
    let startScroll = 0;
    let dragged = false;
    let suppressClick = false;
    let pauseUntil = 0;
    carousel.style.scrollBehavior = 'auto';
    window.setInterval(() => {
      if (pointerId || performance.now() < pauseUntil || document.visibilityState !== 'visible') return;
      carousel.scrollLeft += 2;
      if (carousel.scrollLeft >= loopLength) carousel.scrollLeft -= loopLength;
    }, 32);

    carousel.addEventListener('wheel', () => { pauseUntil = performance.now() + 1600; }, { passive: true });

    dragSurface.addEventListener('pointerdown', event => {
      if (event.pointerType === 'mouse' && event.button !== 0) return;
      pointerId = event.pointerId;
      startX = event.clientX;
      startScroll = carousel.scrollLeft;
      dragged = false;
      pauseUntil = performance.now() + 1600;
      dragSurface.classList.add('is-dragging');
      dragSurface.setPointerCapture(pointerId);
    });
    dragSurface.addEventListener('pointermove', event => {
      if (event.pointerId !== pointerId) return;
      const delta = event.clientX - startX;
      if (Math.abs(delta) > 4) dragged = true;
      if (!dragged) return;
      event.preventDefault();
      carousel.scrollLeft = startScroll - delta;
    });

    const finishDrag = event => {
      if (event.pointerId !== pointerId) return;
      pointerId = null;
      dragSurface.classList.remove('is-dragging');
      pauseUntil = performance.now() + 1600;
      if (dragged) {
        suppressClick = true;
        window.setTimeout(() => { suppressClick = false; }, 0);
      }
    };
    dragSurface.addEventListener('pointerup', finishDrag);
    dragSurface.addEventListener('pointercancel', finishDrag);
    dragSurface.addEventListener('click', event => {
      if (!suppressClick) return;
      event.preventDefault();
      event.stopPropagation();
      suppressClick = false;
    }, true);
  });

  const ingredientsFrame = document.querySelector('#ingredients .ingredients__image-wrapper');
  const ingredientsImageElement = ingredientsFrame?.querySelector('img');

  if (ingredientsFrame && ingredientsImageElement) {
    const ovalWrap = document.createElement('div');
    const oval = document.createElement('div');
    const ovalInner = document.createElement('div');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const smallScreen = window.matchMedia('(max-width: 767.98px)');
    const ovalStyles = document.createElement('style');

    ovalWrap.className = 'oval-wrap';
    oval.className = 'oval';
    ovalInner.className = 'oval-inner';
    ingredientsImageElement.className = 'oval-img';
    ingredientsImageElement.src = '/Second-Section-Image.png';

    ovalStyles.id = 'ingredients-oval-styles';
    ovalStyles.textContent = `
      #ingredients .oval-wrap {
        --oval-w: 750px;
        --oval-h: 130vh;
        --tilt: 50deg;
        position: relative;
        width: var(--oval-w);
        height: var(--oval-h);
      }
      #ingredients .oval {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        overflow: hidden;
        background: #f1e4d8;
        transform: rotate(var(--tilt));
        isolation: isolate;
      }
      #ingredients .oval-inner {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 100%;
        height: 100%;
        transform: translate(-50%, -50%) rotate(calc(var(--tilt) * -1));
      }
      #ingredients .oval-img {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
        object-position: 45% 0%;
        transform: translateY(var(--parallax, 0px));
        will-change: transform;
      }
      #ingredients .ingredients__item,
      #ingredients .ingredients__image-1,
      #ingredients .ingredients__image-2 {
        --card-parallax: 0px;
        translate: 0 var(--card-parallax);
        will-change: translate;
      }
      @media (prefers-reduced-motion: reduce) {
        #ingredients .ingredients__item,
        #ingredients .ingredients__image-1,
        #ingredients .ingredients__image-2 {
          translate: none;
          will-change: auto;
        }
      }
      @media (max-width: 768px) {
        #ingredients .oval-wrap {
          --oval-w: 90vw;
          --oval-h: 56vw;
          --tilt: -14deg;
        }
      }
    `;
    document.head.appendChild(ovalStyles);

    ovalInner.appendChild(ingredientsImageElement);
    oval.appendChild(ovalInner);
    ovalWrap.appendChild(oval);
    ingredientsFrame.replaceChildren(ovalWrap);

    ingredientsFrame.style.background = 'transparent';
    ingredientsFrame.style.borderRadius = '0';
    ingredientsFrame.style.overflow = 'visible';
    ingredientsFrame.style.maskImage = 'none';
    ingredientsFrame.style.webkitMaskImage = 'none';

    const updateIngredientsFrame = () => {
      if (smallScreen.matches) {
        ingredientsFrame.style.position = 'relative';
        ingredientsFrame.style.width = '90vw';
        ingredientsFrame.style.height = '72vw';
        ingredientsFrame.style.margin = '0 auto 2rem';
        ingredientsFrame.style.left = 'auto';
        ingredientsFrame.style.top = 'auto';
        ingredientsFrame.style.transform = 'none';
        ingredientsFrame.style.zIndex = '1';
      } else {
        ingredientsFrame.style.position = 'absolute';
        ingredientsFrame.style.width = 'min(50vw, 800px)';
        ingredientsFrame.style.height = 'min(40vw, 640px)';
        ingredientsFrame.style.margin = '0';
        ingredientsFrame.style.left = '50%';
        ingredientsFrame.style.top = '-25%';
        ingredientsFrame.style.transform = 'translate(-50%, 0)';
        ingredientsFrame.style.zIndex = '-1';
      }
    };

    updateIngredientsFrame();
    window.addEventListener('resize', updateIngredientsFrame);
    smallScreen.addEventListener('change', updateIngredientsFrame);

    ingredientsImageElement.style.setProperty('--parallax', '0px');
    const imageParallaxRange = 80;
    const cardParallaxMultiplier = 3;

    if (!reducedMotion.matches && 'IntersectionObserver' in window) {
      let currentOffset = 0;
      let targetOffset = 0;
      let visible = false;
      let animationFrame = 0;

      const tick = () => {
        currentOffset += (targetOffset - currentOffset) * 0.1;
        ingredientsImageElement.style.setProperty('--parallax', `${currentOffset.toFixed(2)}px`);

        if (visible && Math.abs(targetOffset - currentOffset) > 0.1) {
          animationFrame = window.requestAnimationFrame(tick);
        } else {
          animationFrame = 0;
        }
      };

      const updateTarget = () => {
        const bounds = ovalWrap.getBoundingClientRect();
        const offset = (bounds.top + bounds.height / 2 - window.innerHeight / 2) / window.innerHeight;
        targetOffset = Math.max(-1, Math.min(1, offset)) * imageParallaxRange;
        if (visible && !animationFrame) animationFrame = window.requestAnimationFrame(tick);
      };

      const observer = new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        if (visible) {
          updateTarget();
        } else if (animationFrame) {
          window.cancelAnimationFrame(animationFrame);
          animationFrame = 0;
        }
      });

      observer.observe(ovalWrap);
      window.addEventListener('scroll', updateTarget, { passive: true });
      updateTarget();
    }

    const ingredientsCards = [...document.querySelectorAll('#ingredients .ingredients__item')];
    const ingredientsDecorations = [...document.querySelectorAll('#ingredients .ingredients__image-1, #ingredients .ingredients__image-2')];

    if ((ingredientsCards.length || ingredientsDecorations.length) && 'IntersectionObserver' in window) {
      const sectionMotion = [
        ...ingredientsCards.map((element, index) => ({
          element,
          multiplier: index === 0 || index === ingredientsCards.length - 1 ? 0.9 : 1,
          current: 0,
          target: 0,
          visible: false
        })),
        ...ingredientsDecorations.map(element => ({
          element,
          multiplier: 0.85,
          current: 0,
          target: 0,
          visible: false
        }))
      ];
      let sectionAnimationFrame = 0;

      const tickSectionMotion = () => {
        let isMoving = false;

        sectionMotion.forEach(item => {
          item.current += (item.target - item.current) * 0.1;
          if (Math.abs(item.target - item.current) > 0.1) {
            isMoving = true;
          } else {
            item.current = item.target;
          }
          item.element.style.setProperty('--card-parallax', `${item.current.toFixed(2)}px`);
        });

        sectionAnimationFrame = 0;
        if (isMoving) sectionAnimationFrame = window.requestAnimationFrame(tickSectionMotion);
      };

      const updateSectionTargets = () => {
        sectionMotion.forEach(item => {
          if (!item.visible || reducedMotion.matches) {
            item.target = 0;
            return;
          }

          const bounds = item.element.getBoundingClientRect();
          const offset = (bounds.top + bounds.height / 2 - window.innerHeight / 2) / window.innerHeight;
          item.target = Math.max(-1, Math.min(1, offset)) * imageParallaxRange * cardParallaxMultiplier * item.multiplier;
        });

        if (sectionMotion.some(item => Math.abs(item.target - item.current) > 0.1) && !sectionAnimationFrame) {
          sectionAnimationFrame = window.requestAnimationFrame(tickSectionMotion);
        }
      };

      const sectionObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          const item = sectionMotion.find(motionItem => motionItem.element === entry.target);
          if (item) item.visible = entry.isIntersecting;
        });
        updateSectionTargets();
      });

      const updateSectionMotionPreference = () => {
        if (reducedMotion.matches) {
          sectionObserver.disconnect();
          window.cancelAnimationFrame(sectionAnimationFrame);
          sectionAnimationFrame = 0;
          sectionMotion.forEach(item => {
            item.current = 0;
            item.target = 0;
            item.visible = false;
            item.element.style.setProperty('--card-parallax', '0px');
          });
        } else {
          sectionMotion.forEach(item => sectionObserver.observe(item.element));
          updateSectionTargets();
        }
      };

      sectionMotion.forEach(item => sectionObserver.observe(item.element));
      window.addEventListener('scroll', updateSectionTargets, { passive: true });
      window.addEventListener('resize', updateSectionTargets);
      reducedMotion.addEventListener('change', updateSectionMotionPreference);
    }
  }
})();
