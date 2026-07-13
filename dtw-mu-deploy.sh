#!/bin/sh
# deTuinierwinkel — mu-plugins deploy (staging)
# Chroot-proof: maakt wp-content/mu-plugins zelf aan, CA-bundle-fallback voor curl.

# 1) vind wp-content (chroot: /staging.../wp-content; cron: via $HOME of vhosts-pad)
WC=""
for CAND in \
  /staging.detuinierwinkel.nl/wp-content \
  "$HOME/staging.detuinierwinkel.nl/wp-content" \
  /var/www/vhosts/detuinierwinkel.nl/staging.detuinierwinkel.nl/wp-content
do
  [ -d "$CAND" ] && WC="$CAND" && break
done
[ -z "$WC" ] && WC=$(find / -maxdepth 4 -type d -path "*staging.detuinierwinkel.nl/wp-content" 2>/dev/null | head -1)
[ -z "$WC" ] && echo "FOUT: wp-content niet gevonden" && exit 1

DIR="$WC/mu-plugins"
mkdir -p "$DIR" || { echo "FOUT: kan $DIR niet aanmaken"; exit 1; }

# 2) download met CA-fallback (chroot-curl mist /etc/pki ca-bundle)
URL=https://codeload.github.com/IliasKar1/TuinierBlock/tar.gz/refs/heads/mu-plugins
TMP="${TMPDIR:-/tmp}/dtw-mu.$$.tgz"
CA=""
[ -f /etc/ssl/certs/ca-certificates.crt ] && CA="--cacert /etc/ssl/certs/ca-certificates.crt"
if command -v curl >/dev/null 2>&1; then
  curl -sL $CA "$URL" -o "$TMP" 2>/dev/null || curl -skL "$URL" -o "$TMP" || { echo "FOUT: download mislukt"; exit 1; }
else
  wget -qO "$TMP" "$URL" 2>/dev/null || wget -q --no-check-certificate -O "$TMP" "$URL" || { echo "FOUT: download mislukt"; exit 1; }
fi
[ -s "$TMP" ] || { rm -f "$TMP"; echo "FOUT: lege download"; exit 1; }

# 3) uitpakken
tar xzf "$TMP" --strip-components=1 -C "$DIR" || { rm -f "$TMP"; echo "FOUT: uitpakken mislukt"; exit 1; }
rm -f "$TMP"
echo "DEPLOY OK -> $DIR"
ls "$DIR"
