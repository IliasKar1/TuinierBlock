#!/bin/sh
# deTuinierwinkel — mu-plugins deploy (staging)
# Haalt de mu-plugins branch van GitHub en pakt 'm uit in wp-content/mu-plugins.
# Werkt zowel in de (chrooted) Plesk SSH-terminal als vanuit een cron-taak.
DIR=""
for BASE in "$HOME" /; do
  DIR=$(find "$BASE" -maxdepth 5 -type d -path "*staging.detuinierwinkel.nl/wp-content/mu-plugins" 2>/dev/null | head -1)
  [ -n "$DIR" ] && break
done
[ -z "$DIR" ] && echo "FOUT: mu-plugins map niet gevonden" && exit 1
TMP="${TMPDIR:-/tmp}/dtw-mu.$$.tgz"
if command -v curl >/dev/null 2>&1; then
  curl -sL https://codeload.github.com/IliasKar1/TuinierBlock/tar.gz/refs/heads/mu-plugins -o "$TMP" || { echo "FOUT: download mislukt"; exit 1; }
else
  wget -qO "$TMP" https://codeload.github.com/IliasKar1/TuinierBlock/tar.gz/refs/heads/mu-plugins || { echo "FOUT: download mislukt"; exit 1; }
fi
tar xzf "$TMP" --strip-components=1 -C "$DIR" || { rm -f "$TMP"; echo "FOUT: uitpakken mislukt"; exit 1; }
rm -f "$TMP"
echo "DEPLOY OK -> $DIR"
