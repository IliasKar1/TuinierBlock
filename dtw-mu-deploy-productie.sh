#!/bin/sh
# deTuinierwinkel — mu-plugins naar PRODUCTIE (livegang 2026-11-21).
# Zet de plugins neer; de doorverwijzing en het categorie-sjabloon gaan pas aan met het bestand dtw-live.flag.
DIR=/httpdocs/wp-content/mu-plugins
[ -d /httpdocs/wp-content ] || { echo "FOUT: productie niet gevonden"; exit 1; }
mkdir -p "$DIR" || { echo "FOUT: kan $DIR niet aanmaken"; exit 1; }
URL=https://codeload.github.com/IliasKar1/TuinierBlock/tar.gz/refs/heads/mu-plugins
TMP="${TMPDIR:-/tmp}/dtw-mu-prod.$$.tgz"
curl -skL "$URL" -o "$TMP" || { echo "FOUT: download mislukt"; exit 1; }
[ -s "$TMP" ] || { rm -f "$TMP"; echo "FOUT: lege download"; exit 1; }
tar xzf "$TMP" --strip-components=1 -C "$DIR" \
  --exclude='*/tuinierwinkel-theme-migrator' --exclude='*/tuinierwinkel-theme-migrator/*' \
  --exclude='*/livegang' --exclude='*/livegang/*' --exclude='*/README.md' --exclude='*/dtw-mu-deploy*.sh' \
  || { rm -f "$TMP"; echo "FOUT: uitpakken mislukt"; exit 1; }
rm -f "$TMP"
echo "DEPLOY PRODUCTIE OK -> $DIR"
ls "$DIR"
