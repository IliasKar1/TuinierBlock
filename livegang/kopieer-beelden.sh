#!/bin/sh
# deTuinierwinkel livegang: kopieert de beelden uit de lijst van staging naar productie.
# Overschrijft niets. Gebruik: sh kopieer-beelden.sh <adres van beelden.txt>
SRC=/staging.detuinierwinkel.nl
DST=/httpdocs
[ -d "$SRC/wp-content/uploads" ] || { echo "FOUT: staging niet gevonden"; exit 1; }
[ -d "$DST/wp-content/uploads" ] || { echo "FOUT: productie niet gevonden"; exit 1; }
LIST="${TMPDIR:-/tmp}/dtw-beelden.$$.txt"
curl -skL "$1" -o "$LIST" || { echo "FOUT: lijst niet opgehaald"; exit 1; }
[ -s "$LIST" ] || { echo "FOUT: lege lijst"; exit 1; }
ok=0; had=0; anders=0; mis=0
cd "$SRC" || exit 1
while IFS= read -r f; do
  [ -z "$f" ] && continue
  rel="${f#/}"
  case "$rel" in wp-content/uploads/*) ;; *) echo "OVERGESLAGEN $rel"; continue ;; esac
  if [ ! -f "$SRC/$rel" ]; then mis=$((mis+1)); echo "ONTBREEKT_OP_STAGING $rel"; continue; fi
  if [ -f "$DST/$rel" ]; then
    if [ "$(wc -c < "$SRC/$rel")" = "$(wc -c < "$DST/$rel")" ]; then had=$((had+1)); else anders=$((anders+1)); echo "ANDER_BESTAND_OP_PRODUCTIE $rel"; fi
    continue
  fi
  if cp -n --parents "$rel" "$DST/"; then ok=$((ok+1)); else mis=$((mis+1)); echo "MISLUKT $rel"; fi
done < "$LIST"
rm -f "$LIST"
echo "KLAAR gekopieerd=$ok stond_er_al=$had ander_bestand=$anders mislukt=$mis"
