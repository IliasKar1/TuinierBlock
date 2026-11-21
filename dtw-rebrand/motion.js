/* DTW beweging v1 (proef, 2026-11-21) — hoort bij motion.css.
   A. Productkaart -> landing: geeft de foto van de aangeklikte kaart de naam van de hoofdfoto,
      zodat de browser de ene in de andere laat overlopen (en terug bij "vorige").
   B. Scroll-animaties met GSAP + ScrollTrigger op de eigen pagina's (homepage, categorieën, Over ons).
      GSAP wordt alleen daar geladen, na het laden van de pagina.
   Geen beweging voor wie "minder beweging" heeft ingesteld. Alles is defensief: gaat er iets mis,
   dan staat de pagina er gewoon zonder animatie. */
(function () {
  'use strict';
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var NAAM = 'kas-hero';

  /* ---------- A. kaart -> landing ---------- */
  function wisNamen() {
    var met = document.querySelectorAll('a.twh-card img');
    for (var i = 0; i !== met.length; i++) {
      if (met[i].style.viewTransitionName) met[i].style.viewTransitionName = '';
    }
  }

  document.addEventListener('click', function (ev) {
    if (ev.button || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
    var a = ev.target && ev.target.closest ? ev.target.closest('a.twh-card') : null;
    if (!a) return;
    var img = a.querySelector('img');
    if (!img) return;
    wisNamen();                       // een naam mag maar één keer op de pagina voorkomen
    img.style.viewTransitionName = NAAM;
  }, true);

  // terug van een landing naar de lijst: de kaart van dat model krijgt de naam, als hij in beeld staat
  window.addEventListener('pagereveal', function (ev) {
    window.__dtwOvergang = !!(ev && ev.viewTransition);
    if (!ev || !ev.viewTransition) return;
    var van = null;
    try {
      if (window.navigation && window.navigation.activation && window.navigation.activation.from) {
        van = new URL(window.navigation.activation.from.url).pathname;
      }
    } catch (e) { van = null; }
    if (!van) return;
    wisNamen();
    var kaarten = document.querySelectorAll('a.twh-card[href="' + van + '"]');
    for (var i = 0; i !== kaarten.length; i++) {
      var img = kaarten[i].querySelector('img');
      if (!img || !img.offsetParent) continue;
      var r = img.getBoundingClientRect();
      if (r.bottom > 0 && window.innerHeight > r.top) {
        img.style.viewTransitionName = NAAM;
        break;
      }
    }
    var klaar = function () { wisNamen(); };
    ev.viewTransition.finished.then(klaar, klaar);
  });

  /* ---------- B. scroll-animaties ---------- */
  // Dit script staat bovenaan de pagina (de browser start een overgang al vóór de inhoud er staat);
  // de rest wacht daarom tot de inhoud er is.
  var home, cat, over, root;

  function laad(src, klaar) {
    var s = document.createElement('script');
    s.src = src;
    s.async = true;
    s.onload = klaar;
    document.head.appendChild(s);
  }

  function start() {
    var gsap = window.gsap, ST = window.ScrollTrigger;
    if (!gsap || !ST) return;
    gsap.registerPlugin(ST);

    // wat bij het laden al in beeld staat, blijft staan: geen flits
    function onder(el) { return el.getBoundingClientRect().top > window.innerHeight * 0.9; }

    function enkel(sel) {
      var els = root.querySelectorAll(sel);
      for (var i = 0; i !== els.length; i++) {
        if (!onder(els[i])) continue;
        gsap.from(els[i], { y: 34, autoAlpha: 0, duration: 0.9, ease: 'power3.out', clearProps: 'all',
                            scrollTrigger: { trigger: els[i], start: 'top 88%', once: true } });
      }
    }

    function groep(wrapSel, itemSel) {
      var wraps = root.querySelectorAll(wrapSel);
      for (var i = 0; i !== wraps.length; i++) {
        var wrap = wraps[i];
        if (!wrap.offsetParent || !onder(wrap)) continue;
        var items = wrap.querySelectorAll(itemSel);
        if (!items.length) continue;
        gsap.set(items, { transition: 'none' });   // de hover-overgang van de kaart mag de animatie niet vertragen
        gsap.from(items, { y: 42, autoAlpha: 0, duration: 0.8, ease: 'power3.out', stagger: 0.07, clearProps: 'all',
                           scrollTrigger: { trigger: wrap, start: 'top 86%', once: true } });
      }
    }

    function inkomen(items) {
      if (!items.length) return;
      gsap.killTweensOf(items);
      gsap.set(items, { transition: 'none' });
      gsap.fromTo(items, { y: 22, autoAlpha: 0 },
                  { y: 0, autoAlpha: 1, duration: 0.5, ease: 'power2.out', stagger: 0.03, clearProps: 'all', overwrite: true });
    }

    enkel('.twh-head');
    enkel('.twh-seo');
    enkel('.twc-tabel-wrap');
    enkel('.two-duo > *');
    enkel('.twb-review-wrap');
    groep('.twh-tiles', '.twh-tile');
    groep('.twh-ruimte', '.twh-ruimte-btn');
    groep('.twh-sec > .twh-wrap > .twh-cards', '.twh-card');
    groep('.twh-punten', '.twh-punt');
    groep('.twh-kennis', '.twh-kennis-card');
    groep('.twc-vragen', '.twc-vraag');
    groep('.two-stappen', '.two-stap');
    groep('.two-contact', '.two-kaart');

    // homepage: een lijst die opengaat, laat zijn kaarten na elkaar binnenkomen
    if (home) {
      home.addEventListener('click', function (ev) {
        var knop = ev.target && ev.target.closest ? ev.target.closest('[data-twh-open]') : null;
        if (!knop) return;
        window.setTimeout(function () {
          var paneel = document.getElementById(knop.getAttribute('data-twh-open'));
          if (paneel && paneel.classList.contains('is-open')) inkomen(paneel.querySelectorAll('.twh-card'));
          ST.refresh();
        }, 30);
      });
    }

    // categoriepagina: na een filter of sortering komen de overgebleven kaarten opnieuw binnen
    if (cat) {
      var zichtbaar = function () {
        var uit = [], alle = cat.querySelectorAll('#twc-cards .twh-card');
        for (var i = 0; i !== alle.length; i++) { if (!alle[i].hidden) uit.push(alle[i]); }
        return uit;
      };
      cat.addEventListener('click', function (ev) {
        var t = ev.target && ev.target.closest ? ev.target.closest('[data-twc-filter], [data-twc-kies], #twc-reset') : null;
        if (!t) return;
        window.setTimeout(function () { inkomen(zichtbaar()); ST.refresh(); }, 20);
      });
      var sorteer = document.getElementById('twc-sort');
      if (sorteer) sorteer.addEventListener('change', function () { window.setTimeout(function () { inkomen(zichtbaar()); }, 20); });
    }
  }

  function laadGsap() {
    laad('https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js', function () {
      laad('https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js', start);
    });
  }
  function zoek() {
    home = document.getElementById('twh-home');
    cat = document.getElementById('twc-cat');
    over = document.getElementById('two-overons');
    root = home || cat || over;
    if (!root) return;
    if (document.readyState === 'complete') laadGsap();
    else window.addEventListener('load', laadGsap);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', zoek);
  else zoek();
})();
