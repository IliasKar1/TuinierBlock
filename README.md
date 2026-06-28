# tuinierwinkel-blocks

Custom WordPress block theme (FSE) voor **deTuinierwinkel.nl**. Vervangt op termijn Flatsome + Elementor. Asset-light pilot — start met 1 productpagina-template, breidt geleidelijk uit.

## Status

`0.1.0-alpha` — Sprint 1 skeleton.

## Design-richting

**Lush Maximalism × Scrollytelling.**

- Palette: forest green `#1F3D2B`, moss `#3F6B4A`, fern `#8DAE7E`, cream `#F4EFE2`, terracotta `#C0633D`, mustard `#D9A441`, paper `#FBF8F1`.
- Typografie: **Fraunces** (display, serif) + **Manrope** (body, sans).
- Interactie: scroll-triggered fade-in op productpagina-secties via native CSS `animation-timeline: view()` (geen JS-framework).

## Architectuur

- FSE (Full Site Editing) — geen page builders.
- Pure HTML templates + theme.json v3.
- Geen build-step in deze fase (geen Tailwind/Vite/PostCSS).
- WooCommerce native blocks (Product Image Gallery, Add-to-Cart Form, Product Meta, Related Products).

## Bestanden

```
templates/
  index.html             archief van blog/post (fallback)
  page.html              statische pagina's
  single-product.html    productpagina (pilot-template)
  404.html               niet-gevonden
parts/
  header.html            site header + nav + WC mini-cart
  footer.html            footer
style.css                theme header + minimal global CSS (scroll animations)
theme.json               design system (v3)
functions.php            theme bootstrap (patterns, theme-support)
```

## Deploy

GitHub repo `tuinierwinkel-blocks` (private) → Plesk Git pull naar:

```
/var/www/vhosts/detuinierwinkel.nl/staging.detuinierwinkel.nl/wp-content/themes/tuinierwinkel-blocks/
```

Webhook op `main` push. Post-deploy: `wp cache flush --path=<webroot>`.

## Strangler Fig

Een mu-plugin (`tuinierwinkel-theme-migrator`) zorgt dat ALLEEN het pilot-product dit theme krijgt. Rest van de site = Flatsome. Pas als pilot bewezen is wordt theme breder uitgerold.

## Rollback

- Snel: deactiveer/verwijder de mu-plugin `tuinierwinkel-theme-migrator` → instant terug naar Flatsome.
- Backup: Plesk Restore-point van staging vóór elke deploy.
- Productie wordt niet geraakt in deze fase.
