const yearEl = document.getElementById('year');
if (yearEl) yearEl.textContent = new Date().getFullYear();

// Theme toggle
const themeToggle = document.getElementById('themeToggle');
if (themeToggle) {
  themeToggle.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
  });
}

// Mobile menu
const navToggle = document.getElementById('navToggle');
const mobileMenu = document.getElementById('mobileMenu');

if (navToggle && mobileMenu) {
  navToggle.addEventListener('click', () => {
    const isOpen = mobileMenu.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', String(isOpen));
  });

  mobileMenu.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      mobileMenu.classList.remove('open');
      navToggle.setAttribute('aria-expanded', 'false');
    });
  });
}

// Earlier roles toggle
const toggleEarlier = document.getElementById('toggleEarlier');
const earlierTimeline = document.getElementById('earlierTimeline');

if (toggleEarlier && earlierTimeline) {
  toggleEarlier.addEventListener('click', () => {
    const isHidden = earlierTimeline.hasAttribute('hidden');
    if (isHidden) {
      earlierTimeline.removeAttribute('hidden');
      toggleEarlier.textContent = 'Hide earlier roles';
      toggleEarlier.setAttribute('aria-expanded', 'true');
      requestAnimationFrame(() => {
        earlierTimeline.querySelectorAll('.reveal').forEach(el => el.classList.add('in-view'));
      });
    } else {
      earlierTimeline.setAttribute('hidden', '');
      toggleEarlier.textContent = 'Show earlier roles (2000 – 2016)';
      toggleEarlier.setAttribute('aria-expanded', 'false');
    }
  });
}

// Scroll reveal
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const revealEls = document.querySelectorAll('.reveal');

if (prefersReducedMotion) {
  revealEls.forEach(el => el.classList.add('in-view'));
} else {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

  revealEls.forEach(el => observer.observe(el));
}
