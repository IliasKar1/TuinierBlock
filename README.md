# deTuinierwinkel — mu-plugins (branch `mu-plugins`)

Deze branch deployt via Plesk Git naar `wp-content/mu-plugins/` op staging.
Aparte branch in het TuinierBlock-repo zodat er geen extra GitHub-repo nodig is.

## Inhoud

- `dtw-rebrand.php` + `dtw-rebrand/` — header/footer-rebrand (hybride B×C + footer forest).
  CSS/JS laden site-breed over Flatsome heen; cache-busting via filemtime.
- `tuinierwinkel-theme-migrator/` — Strangler Fig loader (sprint 2, block-theme pilot).

## Werkwijze

1. Wijzig bestanden lokaal in `C:\Users\ilias\WebDev\mu-plugins-repo\`
2. Commit + push naar `origin mu-plugins`
3. Plesk pullt automatisch via webhook → live op staging in <30 sec

Nooit handmatig op de server bewerken — Git is de bron van waarheid.
