<?php
/*
 * Google Analytics 4, loaded only with consent.
 *
 * This is Google's own gtag.js snippet with one change: the script is not
 * fetched until the visitor has chosen "Accept all" in the cookie notice.
 * Analytics cookies need prior consent under Irish and EU law, so pasting the
 * snippet straight into <head> would set them on every visitor the moment the
 * page loads. Until someone accepts, the page makes no request to Google at all.
 *
 * js/main.js reports what people do — menu, directions, bookings, phone and
 * Instagram taps — through window.octoberAnalytics.track().
 * Every event goes to two places:
 *
 *   1. Our own stats (track.php → staff/stats.php), always, because it sets
 *      no cookie and keeps nothing personal. This is the ROI dashboard.
 *   2. Google Analytics, only after "Accept all", for the deeper reports.
 *
 * Nothing personal is ever sent to either: no names, phone numbers or email
 * addresses, which Google's terms forbid anyway.
 *
 * Only the public pages include this. The staff area and setup page never load
 * analytics, so staff activity doesn't skew the numbers and booking screens full
 * of customer details are never reported anywhere.
 */

function render_analytics_head(): void
{
    global $analytics, $stats;

    $id = (string) ($analytics['ga4_id'] ?? '');
    if (!preg_match('/^G-[A-Z0-9]{4,}$/', $id)) {
        $id = '';
    }
    $gtm = (string) ($analytics['gtm_id'] ?? '');
    if (!preg_match('/^GTM-[A-Z0-9]{4,}$/', $gtm)) {
        $gtm = '';
    }
    $own = !empty($stats['enabled']);
    ?>
    <script>
    (function () {
      var ID = <?= json_encode($id) ?>;
      var GTM = <?= json_encode($gtm) ?>;
      var OWN = <?= $own ? 'true' : 'false' ?>;
      var loaded = false;

      // Our own stats: a fire-and-forget beacon, no cookie, nothing personal.
      function beacon(name, params) {
        if (!OWN || !navigator.sendBeacon) return;
        try {
          var f = new FormData();
          f.append('event', name);
          f.append('page', location.pathname);
          params = params || {};
          // The app is the useful label: Google Maps vs Apple Maps.
          var label = params.app || params.label;
          if (label) f.append('label', String(label));
          if (params.location) f.append('location', String(params.location));
          if (name === 'page_view') {
            f.append('ref', document.referrer || '');
            var m = location.search.match(/[?&]utm_source=([^&]+)/);
            if (m) f.append('utm', decodeURIComponent(m[1]));
          }
          navigator.sendBeacon('/track.php', f);
        } catch (e) {}
      }

      window.dataLayer = window.dataLayer || [];
      function gtag() { window.dataLayer.push(arguments); }
      window.gtag = gtag;

      function choice() {
        // Same rule as js/main.js: a choice older than six months doesn't count.
        try {
          var parts = (localStorage.getItem('october-cookies') || '').split('|');
          var at = parseInt(parts[1], 10);
          return at && Date.now() - at <= 182 * 864e5 ? parts[0] : null;
        } catch (e) { return null; }
      }

      function load() {
        if (loaded || (!ID && !GTM)) return;
        loaded = true;
        if (ID) window['ga-disable-' + ID] = false;

        // Analytics only. This site runs no ads, so advertising storage stays off.
        gtag('consent', 'default', {
          analytics_storage: 'granted',
          ad_storage: 'denied',
          ad_user_data: 'denied',
          ad_personalization: 'denied'
        });

        // Google Tag Manager: the container decides which tags run (GA4 etc.).
        if (GTM) {
          window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
          var g = document.createElement('script');
          g.async = true;
          g.src = 'https://www.googletagmanager.com/gtm.js?id=' + GTM;
          document.head.appendChild(g);
        }
        if (!ID) return;

        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + ID;
        document.head.appendChild(s);

        gtag('js', new Date());
        gtag('config', ID);
      }

      // Withdrawing consent has to be as easy as giving it: stop sending, and
      // clear the cookies Google already set.
      function unload() {
        if (!ID && !GTM) return;
        if (ID) window['ga-disable-' + ID] = true;
        // Tell Tag Manager's tags that consent is withdrawn.
        gtag('consent', 'update', { analytics_storage: 'denied' });
        loaded = false;
        var host = location.hostname;
        document.cookie.split(';').forEach(function (c) {
          var name = c.split('=')[0].trim();
          if (name.indexOf('_ga') !== 0) return;
          [host, '.' + host, host.replace(/^www\./, '.')].forEach(function (d) {
            document.cookie = name + '=; Max-Age=0; path=/; domain=' + d;
          });
          document.cookie = name + '=; Max-Age=0; path=/';
        });
      }

      window.octoberAnalytics = {
        enable: load,
        disable: unload,
        enabled: function () { return loaded; },
        track: function (name, params) {
          beacon(name, params);
          if (!loaded) return;
          if (ID) gtag('event', name, params || {});
          // For Tag Manager: a plain dataLayer event it can trigger tags on.
          if (GTM) {
            var ev = { event: name };
            params = params || {};
            for (var k in params) { if (Object.prototype.hasOwnProperty.call(params, k)) ev[k] = params[k]; }
            window.dataLayer.push(ev);
          }
        }
      };

      if (choice() === 'all') load();
      beacon('page_view');
    })();
    </script>
    <?php
}
