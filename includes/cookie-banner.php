<?php
/*
 * Cookie notice.
 *
 * Two things here need consent: Google Analytics, and the Google Maps embed.
 * Neither loads until somebody presses "Accept" (the map can also be shown
 * once with a tap). The booking and order forms' session cookie is strictly
 * necessary and needs none.
 *
 * In line with the Data Protection Commission's guidance:
 * - "Reject" and "Accept" look exactly the same, so nobody is nudged;
 * - nothing optional runs before a choice is made;
 * - the choice is asked again after six months (see js/main.js);
 * - the footer's "Cookie settings" link reopens this, so a choice can be
 *   changed or withdrawn as easily as it was given.
 *
 * The choice is kept in localStorage rather than a cookie, so declining
 * cookies does not itself set one.
 */
function render_cookie_banner(): void
{
    ?>
    <div class="cookie" id="cookie-banner" hidden>
        <div class="cookie-inner">
            <p class="cookie-text">
                With your OK we use Google Analytics to see which pages people visit, so we
                can improve the site, and show a Google map on our home page. Google may set
                cookies for both. No ads, and nothing loads unless you accept.
                <a href="privacy.php">Privacy notice</a>.
            </p>
            <div class="cookie-do">
                <button type="button" class="btn btn-light" data-cookie="essential">Reject</button>
                <button type="button" class="btn btn-light" data-cookie="all">Accept</button>
            </div>
        </div>
    </div>
    <?php
}
