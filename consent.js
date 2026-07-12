// Cookie consent: nothing non-essential loads until the visitor accepts.
// Stores the choice in localStorage; Accept and Reject are equal one-click actions.
(function () {
  var KEY = 'cookie-consent';        // 'granted' | 'denied'
  var GTM_ID = 'GTM-TLCJZMQ9';

  function get() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function set(v) { try { localStorage.setItem(KEY, v); } catch (e) {} }

  var gtmLoaded = false;
  function loadGTM() {
    if (gtmLoaded) return;
    gtmLoaded = true;
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
    var f = document.getElementsByTagName('script')[0];
    var j = document.createElement('script');
    j.async = true;
    j.src = 'https://www.googletagmanager.com/gtm.js?id=' + GTM_ID;
    f.parentNode.insertBefore(j, f);
  }

  // Called whenever consent is active (fresh Accept, or a returning visitor
  // who accepted earlier). Listeners like the Instagram embed wait on this.
  function activate() {
    loadGTM();
    document.dispatchEvent(new CustomEvent('consent-granted'));
  }

  var banner = null;
  function showBanner() {
    if (banner) { banner.hidden = false; return; }
    banner = document.createElement('div');
    banner.className = 'cookie-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Cookie consent');
    banner.tabIndex = -1;
    banner.innerHTML =
      '<p>I use Google Analytics, and Instagram embeds on one page, to understand traffic. ' +
      'They set cookies only if you accept. Read the <a href="/privacy.html">privacy policy</a>.</p>' +
      '<div class="cookie-actions">' +
        '<button type="button" class="btn btn-ghost" data-consent="deny">Reject</button>' +
        '<button type="button" class="btn btn-primary" data-consent="accept">Accept</button>' +
      '</div>';
    document.body.appendChild(banner);
    banner.focus();
    banner.querySelector('[data-consent="accept"]').addEventListener('click', function () {
      set('granted'); banner.hidden = true; activate();
    });
    banner.querySelector('[data-consent="deny"]').addEventListener('click', function () {
      set('denied'); banner.hidden = true;
    });
  }

  window.cookieConsent = {
    granted: function () { return get() === 'granted'; },
    open: showBanner
  };

  // Footer "Cookie settings" link re-opens the banner from any page.
  document.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('[data-cookie-settings]');
    if (t) { e.preventDefault(); showBanner(); }
  });

  var choice = get();
  if (choice === 'granted') {
    activate();
  } else if (choice !== 'denied') {
    if (document.body) showBanner();
    else document.addEventListener('DOMContentLoaded', showBanner);
  }
})();
