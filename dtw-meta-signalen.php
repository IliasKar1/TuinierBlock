<?php
/**
 * Plugin Name: DTW Meta-signalen (mu)
 * Description: Twee metingen voor Meta, via de browser (pixel) en via de server (Conversions API) met hetzelfde event-id, zodat Meta ze niet dubbel telt. (1) Contact bij een klik op een WhatsApp-knop: de offerteknop op de landings en de zwevende knop. (2) EchtBezoek op productpagina's en landings, na 60 seconden zichtbaar op de pagina en minstens 50% gescrold, één keer per paginabezoek. Het bestaande event "Click to Chat by HoliThemes" blijft ongemoeid. Op productie pas actief als het bestand dtw-meta-signalen.flag in mu-plugins staat.
 * Author: Daan (web-dev-agent)
 * Version: 0.1.1
 *
 * Besluit eigenaar 28-11-2026 00:12 ("Ja nu bouwen"), opdracht marketing-manager 27-11 23:56.
 * Instellingen staan bovenaan (DTW_MS_*). Uitzetten: het bestand dtw-meta-signalen.flag verwijderen (productie), of dit bestand verwijderen of hernoemen.
 * Testen zonder de telling te raken: zet de optie dtw_meta_test_code op de testcode uit Gebeurtenisbeheer (Test events); dan gaan de server-events alleen daarheen.
 * Logboek van de laatste 40 server-events: GET /wp-json/dtw/v1/meta-log (alleen voor beheerders).
 */

defined('ABSPATH') || exit;

const DTW_MS_PIXEL       = '297242519876535';
const DTW_MS_SECONDEN    = 60;   // zichtbaar op de pagina
const DTW_MS_SCROLL      = 50;   // procent van de pagina gescrold
const DTW_MS_MAX_PER_IP  = 30;   // server-events per bezoeker per 10 minuten
const DTW_MS_EVENTS      = array('Contact', 'EchtBezoek');

/** Testsite: altijd aan. Productie: pas met het bestand dtw-meta-signalen.flag. */
function dtw_ms_aan() {
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    if (strpos($host, 'staging.') === 0) {
        return true;
    }
    return file_exists(WPMU_PLUGIN_DIR . '/dtw-meta-signalen.flag');
}

/** Toegangscode voor de Conversions API: eigen constante, anders die van de Meta-plugins die al op de site staan. */
function dtw_ms_token() {
    if (defined('DTW_META_CAPI_TOKEN') && DTW_META_CAPI_TOKEN) {
        return (string) DTW_META_CAPI_TOKEN;
    }
    $t = get_option('wc_facebook_access_token');
    if (is_string($t) && $t !== '') {
        return $t;
    }
    $c = get_option('facebook_config');
    if (is_array($c) && !empty($c['access_token'])) {
        return (string) $c['access_token'];
    }
    return '';
}

function dtw_ms_is_bot($ua) {
    return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|meta-externalagent|headless|lighthouse|pingdom|curl|wget|python/i', $ua);
}

function dtw_ms_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $priv = !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if ($priv && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $eerste = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($eerste, FILTER_VALIDATE_IP)) {
            $ip = $eerste;
        }
    }
    return $ip;
}

function dtw_ms_log($regel) {
    $log = get_option('dtw_meta_log', array());
    if (!is_array($log)) {
        $log = array();
    }
    array_unshift($log, $regel);
    update_option('dtw_meta_log', array_slice($log, 0, 40), false);
}

/* ---------- Browserkant ---------- */

add_action('wp_footer', function () {
    if (!dtw_ms_aan() || is_admin() || (function_exists('is_checkout') && (is_checkout() || is_cart()))) {
        return;
    }
    $soort = (function_exists('is_product') && is_product()) ? 'product' : (is_page() ? 'pagina' : 'overig');
    $cfg = array(
        'url'     => esc_url_raw(rest_url('dtw/v1/meta')),
        'soort'   => $soort,
        'sec'     => DTW_MS_SECONDEN,
        'scroll'  => DTW_MS_SCROLL,
        'product' => ($soort === 'product') ? (string) get_the_ID() : '',
        'test'    => strpos((string) wp_parse_url(home_url(), PHP_URL_HOST), 'staging.') === 0 && get_option('dtw_meta_test_code', '') !== '',
    );
    ?>
<script id="dtw-meta-signalen" data-cfasync="false">
(function () {
  if (window.dtwMs) return;
  var C = window.dtwMs = <?php echo wp_json_encode($cfg); ?>;
  function id() { return 'dtw-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10); }
  function viaPixel(naam, data, eid, standaard, poging) {
    poging = poging || 0;
    if (typeof window.fbq === 'function') {
      try { window.fbq(standaard ? 'track' : 'trackCustom', naam, data, { eventID: eid }); } catch (e) {}
      return;
    }
    if (poging < 20) setTimeout(function () { viaPixel(naam, data, eid, standaard, poging + 1); }, 500);
  }
  function viaServer(naam, data, eid) {
    var body = JSON.stringify({ event: naam, id: eid, url: location.href, data: data });
    try {
      if (navigator.sendBeacon && navigator.sendBeacon(C.url, new Blob([body], { type: 'text/plain' }))) return;
    } catch (e) {}
    try { fetch(C.url, { method: 'POST', body: body, keepalive: true, headers: { 'Content-Type': 'text/plain' } }); } catch (e) {}
  }
  /* Toestemming: de site heeft (nog) geen eigen cookiebanner. We volgen de pixel: draait die (cookie _fbp of fbq aanwezig),
     dan mag het signaal mee. Komt er later een banner die de pixel tegenhoudt tot er toestemming is, dan gaat hier vanzelf niets meer heen.
     Op de testsite (geen pixel) alleen als er een testcode is gezet. */
  function mag() {
    if (typeof window.fbq === 'function' || /(?:^|;\s*)_fbp=/.test(document.cookie)) return true;
    return !!C.test;
  }
  function productId() {
    if (C.product) return C.product;
    var el = document.querySelector('#twb-cta[data-c], [data-c*="add-to-cart="], a[href*="add-to-cart="]');
    var s = el ? (el.getAttribute('data-c') || el.getAttribute('href') || '') : '';
    var m = s.match(/add-to-cart=(\d+)/);
    return m ? m[1] : '';
  }
  function meld(naam, data, standaard) {
    if (!mag()) return;
    var pid = productId();
    if (pid) { data.content_ids = [pid]; data.content_type = 'product'; }
    data.content_name = document.title;
    var eid = id();
    viaPixel(naam, data, eid, standaard);
    viaServer(naam, data, eid);
  }

  /* 1. Contact: klik op een WhatsApp-knop. Slot van 3 seconden: de cache speelt de eerste klik soms opnieuw af. */
  var laatst = 0;
  document.addEventListener('click', function (e) {
    var el = e.target && e.target.closest ? e.target : null;
    if (!el) return;
    var a = el.closest('a'), href = a ? (a.getAttribute('href') || '') : '';
    var zwevend = el.closest('.ht-ctc-chat, .ht_ctc_chat_style, #ht-ctc-chat');
    var waLink = /wa\.me\/|api\.whatsapp\.com|whatsapp:\/\//i.test(href);
    if (!waLink && !zwevend) return;
    var nu = Date.now();
    if (nu - laatst < 3000) return;
    laatst = nu;
    meld('Contact', { knop: zwevend && !waLink ? 'zwevend' : 'offerte' }, true);
  }, true);

  /* 2. EchtBezoek: productpagina of landing, 60 s zichtbaar en minstens 50% gescrold. */
  var isLanding = !!document.querySelector('[id^="twb-"]');
  if (C.soort !== 'product' && !isLanding) return;
  var soort = C.soort === 'product' ? 'product' : 'landing';
  var tijd = 0, diepte = 0, klaar = false, start = document.visibilityState === 'visible' ? Date.now() : 0;
  function meet() {
    var h = Math.max(document.documentElement.scrollHeight - window.innerHeight, 1);
    var p = Math.min(100, Math.round(((window.scrollY || window.pageYOffset || 0) / h) * 100));
    if (p > diepte) diepte = p;
  }
  function tel() {
    if (start) { tijd += Date.now() - start; start = document.visibilityState === 'visible' ? Date.now() : 0; }
  }
  function check() {
    if (klaar) return;
    tel(); meet();
    if (tijd >= C.sec * 1000 && diepte >= C.scroll) {
      klaar = true;
      meld('EchtBezoek', { pagina: soort, seconden: Math.round(tijd / 1000), scroll_procent: diepte }, false);
    }
  }
  window.addEventListener('scroll', function () { meet(); }, { passive: true });
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') { if (!start) start = Date.now(); } else { tel(); start = 0; }
  });
  var t = setInterval(function () { check(); if (klaar) clearInterval(t); }, 2000);
})();
</script>
    <?php
}, 99);

/* ---------- Serverkant ---------- */

add_action('rest_api_init', function () {
    register_rest_route('dtw/v1', '/meta', array(
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => 'dtw_ms_ontvang',
    ));
    register_rest_route('dtw/v1', '/meta-log', array(
        'methods'             => 'GET',
        'permission_callback' => function () { return current_user_can('manage_woocommerce'); },
        'callback'            => function () {
            return array(
                'aan'       => dtw_ms_aan(),
                'token'     => dtw_ms_token() !== '' ? 'aanwezig' : 'ontbreekt',
                'test_code' => (string) get_option('dtw_meta_test_code', ''),
                'log'       => get_option('dtw_meta_log', array()),
            );
        },
    ));
});

function dtw_ms_ontvang(WP_REST_Request $req) {
    if (!dtw_ms_aan()) {
        return new WP_REST_Response(array('ok' => false, 'reden' => 'uit'), 200);
    }
    $in = json_decode((string) $req->get_body(), true);
    if (!is_array($in)) {
        return new WP_REST_Response(array('ok' => false, 'reden' => 'leeg'), 400);
    }
    $event = isset($in['event']) ? (string) $in['event'] : '';
    $eid   = isset($in['id']) ? (string) $in['id'] : '';
    $url   = isset($in['url']) ? esc_url_raw((string) $in['url']) : '';
    $data  = (isset($in['data']) && is_array($in['data'])) ? $in['data'] : array();
    if (!in_array($event, DTW_MS_EVENTS, true) || !preg_match('/^dtw-[a-z0-9-]{6,40}$/', $eid)) {
        return new WP_REST_Response(array('ok' => false, 'reden' => 'ongeldig'), 400);
    }
    $eigen = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    if ((string) wp_parse_url($url, PHP_URL_HOST) !== $eigen) {
        return new WP_REST_Response(array('ok' => false, 'reden' => 'andere site'), 400);
    }
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 400) : '';
    if (dtw_ms_is_bot($ua)) {
        return new WP_REST_Response(array('ok' => false, 'reden' => 'robot'), 200);
    }
    $ip  = dtw_ms_ip();
    $key = 'dtw_ms_' . md5($ip);
    $n   = (int) get_transient($key);
    if ($n >= DTW_MS_MAX_PER_IP) {
        return new WP_REST_Response(array('ok' => false, 'reden' => 'te vaak'), 429);
    }
    set_transient($key, $n + 1, 10 * MINUTE_IN_SECONDS);

    // Alleen platte, korte waarden doorgeven.
    $custom = array();
    foreach (array('content_name', 'content_type', 'knop', 'pagina', 'seconden', 'scroll_procent') as $k) {
        if (isset($data[$k]) && is_scalar($data[$k])) {
            $custom[$k] = is_numeric($data[$k]) ? $data[$k] + 0 : substr(sanitize_text_field((string) $data[$k]), 0, 200);
        }
    }
    if (!empty($data['content_ids']) && is_array($data['content_ids'])) {
        $custom['content_ids'] = array_slice(array_map('strval', array_map('intval', $data['content_ids'])), 0, 5);
    }
    $user = array('client_ip_address' => $ip, 'client_user_agent' => $ua);
    foreach (array('_fbp' => 'fbp', '_fbc' => 'fbc') as $cookie => $veld) {
        if (!empty($_COOKIE[$cookie]) && preg_match('/^fb\.\d\.\d+\.[A-Za-z0-9_.-]+$/', (string) $_COOKIE[$cookie])) {
            $user[$veld] = (string) $_COOKIE[$cookie];
        }
    }
    $payload = array('data' => array(array(
        'event_name'       => $event,
        'event_time'       => time(),
        'event_id'         => $eid,
        'action_source'    => 'website',
        'event_source_url' => $url,
        'user_data'        => $user,
        'custom_data'      => $custom,
    )));
    $test = (string) get_option('dtw_meta_test_code', '');
    if ($test !== '') {
        $payload['test_event_code'] = $test;
    }
    // Toestemming aan de serverkant: alleen als de pixel bij deze bezoeker draait (cookie _fbp). Testsite met testcode uitgezonderd.
    $testsite = strpos($eigen, 'staging.') === 0;
    if (!isset($user['fbp']) && !($testsite && $test !== '')) {
        return new WP_REST_Response(array('ok' => false, 'reden' => 'geen pixel, geen toestemming'), 200);
    }

    $regel = array('tijd' => current_time('mysql'), 'event' => $event, 'id' => $eid, 'url' => $url, 'fbp' => isset($user['fbp']) ? 'ja' : 'nee', 'test' => $test !== '' ? 'ja' : 'nee',
                   'custom_data' => $custom);
    $token = dtw_ms_token();
    if ($token === '') {
        $regel['uitkomst'] = 'droog: geen toegangscode, niet verstuurd';
        dtw_ms_log($regel);
        return new WP_REST_Response(array('ok' => true, 'verstuurd' => false), 200);
    }
    $r = wp_remote_post('https://graph.facebook.com/' . DTW_MS_PIXEL . '/events?access_token=' . rawurlencode($token), array(
        'timeout' => 5,
        'headers' => array('Content-Type' => 'application/json'),
        'body'    => wp_json_encode($payload),
    ));
    if (is_wp_error($r)) {
        $regel['uitkomst'] = 'fout: ' . $r->get_error_message();
    } else {
        $regel['uitkomst'] = wp_remote_retrieve_response_code($r) . ' ' . substr((string) wp_remote_retrieve_body($r), 0, 200);
    }
    dtw_ms_log($regel);
    return new WP_REST_Response(array('ok' => true, 'verstuurd' => true), 200);
}
