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
})();
