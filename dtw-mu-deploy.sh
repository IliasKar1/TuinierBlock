#!/bin/sh
# deTuinierwinkel — mu-plugins deploy (staging)
# Haalt de mu-plugins branch van GitHub en pakt 'm uit in wp-content/mu-plugins.
# Installeert zichzelf in ~/bin voor de cron-taak.
DIR=$(find ~ -maxdepth 4 -type d -path "*staging.detuinierwinkel.nl/wp-content/mu-plugins" 2>/dev/null | head -1)
[ -z "$DIR" ] && echo "FOUT: mu-plugins map niet gevonden" && exit 1
TMP=$(mktemp)
if command -v curl >/dev/null 2>&1; then
  curl -sL https://codeload.github.com/IliasKar1/TuinierBlock/tar.gz/refs/heads/mu-plugins -o "$TMP" || exit 1
else
  wget -qO "$TMP" https://codeload.github.com/IliasKar1/TuinierBlock/tar.gz/refs/heads/mu-plugins || exit 1
fi
tar xzf "$TMP" --strip-components=1 -C "$DIR" && rm -f "$TMP" || exit 1
mkdir -p ~/bin
cp "$DIR/dtw-mu-deploy.sh" ~/bin/dtw-mu-deploy.sh 2>/dev/null
chmod +x ~/bin/dtw-mu-deploy.sh 2>/dev/null
echo "DEPLOY OK -> $DIR"
