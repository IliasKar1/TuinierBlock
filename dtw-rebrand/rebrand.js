/* DTW Rebrand v1 — bewegingslaag.
   Voegt bij scrollen de class 'dtw-condensed' toe aan <html>, zodat de CSS
   de topbar kan wegvouwen, het logo kan verkleinen en de frosted-glass +
   schaduw op de sticky header kan activeren. rAF-throttled + passief.
   Geen dependencies, defensief. */
(function () {
  'use strict';
  var root = document.documentElement;
  var THRESHOLD = 50;   // px voordat de header condenseert
  var ticking = false;

  function update() {
    var y = window.pageYOffset || document.body.scrollTop || 0;
    root.classList.toggle('dtw-condensed', y > THRESHOLD);
    ticking = false;
  }

  function onScroll() {
    if (!ticking) {
      window.requestAnimationFrame(update);
      ticking = true;
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll, { passive: true });

  // begintoestand meteen goed zetten (bv. bij herladen halverwege de pagina)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', update);
  } else {
    update();
  }
})();
