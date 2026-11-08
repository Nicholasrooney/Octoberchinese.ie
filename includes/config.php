<?php
/*
 * October Chinese Dining: site settings
 * Edit the values below. Anything left as '' (empty) is hidden on the site.
 */

$site = [
    'name'      => 'October Chinese Dining',
    'domain'    => 'octoberdining.ie',
    'name_cn'   => '食悦',
    'tagline'   => 'Chinese Dining',
    'instagram' => '',   // e.g. 'https://www.instagram.com/octoberdining/'

    // Contact details (fill these in, they appear in the footer and on the home page)
    'address'   => "60 George's Street Upper, Dún Laoghaire, Co. Dublin",
    'eircode'   => 'A96 CY53',
    'phone'     => '01 663 6326',
    'email'     => '',   // e.g. 'hello@octoberdining.ie'
    'maps_url'  => 'https://www.google.com/maps/search/?api=1&query=October+Chinese+Dining%2C+60+George%27s+Street+Upper%2C+D%C3%BAn+Laoghaire%2C+A96+CY53',

    // Optional but recommended: the exact pin, as "latitude,longitude".
    // Get it by right-clicking the café in Google Maps and copying the numbers.
    // When set, directions use this instead of the address text, which stops
    // map apps guessing at the wrong unit in the shopping centre.
    'coords'    => '',

    // Show prices on the menu and the catering page? Off for now. Everything
    // price-related follows this one switch: menu prices and add-on prices,
    // the home page dishes, the catering price card, emails, and the prices
    // sent to Google. Set to true to bring them all back.
    'show_prices' => true,

    // Opening hours are NOT set here. They come from $booking['service_hours']
    // below, and are filled in at the bottom of this file, so the hours shown
    // on the site, the hours Google is told, and the times the booking form
    // offers can never disagree with each other.
    'hours' => [],
];

// Booking form options
$booking = [
    /*
     * Opening hours, per day. [open, close] in 24-hour time, or null if the
     * café is closed that day. This is the single source of truth — it drives
     * the hours on the site, the hours Google shows, and the slots the booking
     * form offers.
     */
    'service_hours' => [
        // ASSUMED from Google ("closes 9:30 PM") and the lunch menu (from
        // 12:30). Confirm with the restaurant before going live.
        'mon' => ['12:30', '21:30'],
        'tue' => ['12:30', '21:30'],
        'wed' => ['12:30', '21:30'],
        'thu' => ['12:30', '21:30'],
        'fri' => ['12:30', '21:30'],
        'sat' => ['12:30', '21:30'],
        'sun' => ['12:30', '21:30'],
    ],

    // How often a slot comes round, in minutes.
    'slot_minutes' => 30,

    // The last booking is this many minutes before closing, so nobody books a
    // table for five minutes before the doors shut. Weekdays close at 16:00,
    // so the last slot is 15:00; weekends close at 17:00, so it is 16:00.
    'last_booking_before_close' => 60,

    /*
     * One-off dates the café will not take new bookings for, on top of the
     * normal closed days. Use it for a day that is already full, a private
     * function, or a closure.
     *
     * Bookings already taken on these dates are untouched, and staff can still
     * add one by hand in the diary — this only stops the public form.
     *
     * Give a date an empty message for the standard wording, or write your own:
     *     '2026-12-25' => 'We are closed for Christmas.',
     */
    /*
     * Which days the staff "Pause this weekend" button covers.
     *
     * Friday is in because that is what has actually been paused in practice.
     * Drop it to 'sat', 'sun' if the weekend should mean just the two days.
     */
    'weekend_days' => ['fri', 'sat', 'sun'],

    // What a customer is told when the weekend has been paused from the
    // staff area. Keep it the same shape as the messages below.
    'weekend_paused_message' => 'We are not taking any more bookings this weekend.',

    'no_bookings_dates' => [
        // One-off dates, on top of the weekly closed days and the staff
        // weekend toggle. Format:
        //     '2026-12-25' => 'We are closed for Christmas.',
    ],

    // Filled in below from service_hours. Do not edit by hand.
    'times' => [],
    /*
     * Online table booking on the website. false hides every "Book a table"
     * button and link, sends booking.php back to the home page, and asks
     * people to ring instead. Staff can still add phone bookings in the diary.
     * Set to true to bring the booking page back.
     */
    'online'            => false,

    'max_guests'        => 8,   // bigger groups are asked to ring
    'days_ahead'        => 60,  // how far in advance people can book

    // The banquette along the feature wall is the seat to ask for.
    'seating'           => ['Banquette', 'Table', 'No preference'],

    // How long a table is held when the café is busy, which is usually at
    // weekends. Shown on the booking page and in the confirmation email.
    'table_minutes'     => 90,

    // Bookings are saved here until the backend is connected.
    // The data folder is blocked from public access by data/.htaccess
    'csv_file'          => __DIR__ . '/../data/bookings.csv',

    // Who is emailed when a TABLE BOOKING comes in. Each person gets their own
    // copy. Catering enquiries have their own list in $catering below.
    'notify_email'      => [
        'nicholas.rooney2010@gmail.com',
        // add the restaurant's own inbox here
    ],

    // Who is emailed when staff CHANGE a booking — cancelled, no-show, or
    // edited. Alex leads here; these are the ones worth interrupting someone
    // for, so the list is deliberately shorter than the new-booking list.
    'change_notify_email' => [
        'nicholas.rooney2010@gmail.com',
    ],

    /*
     * How many bookings the café takes in one hour, whatever their size.
     * Slots come round every half hour, so 13:00 and 13:30 share the 13:00
     * hour and together use up its two bookings.
     *
     * The public form stops offering a time once its hour is full. Staff can
     * still override, and the override is recorded.
     */
    'max_bookings_per_hour' => 4,

    // Customer details are anonymised this many months after their last visit.
    'retention_months'    => 24,
];

/*
 * Database. Create it in hPanel > Databases > MySQL Databases, then run
 * db/schema.sql through phpMyAdmin. See docs/database-setup.md.
 *
 * Credentials belong in includes/secrets/db.php, which is gitignored — see the
 * bottom of this file.
 */
$db = [
    'driver'  => 'mysql',
    'host'    => 'localhost',
    'name'    => '',
    'user'    => '',
    'pass'    => '',
    'charset' => 'utf8mb4',
    'path'    => '',   // only used when driver is 'sqlite' (the test suite)
];

/*
 * Staff area at /staff/.
 *
 * Generate the hash by visiting /staff/hash.php, then paste it here. Never put
 * the plain password in this file.
 */
$staff = [
    'password_hash'   => '',
    'session_hours'   => 12,
    'max_attempts'    => 5,
    'lockout_minutes' => 15,
];

/*
 * Outgoing email.
 *
 * Fill in 'from' with a mailbox on this domain — mail claiming to come from a
 * gmail.com address will be treated as forged and binned.
 *
 * SMTP is strongly preferred over bare mail(): create the mailbox in hPanel >
 * Emails, then put its details here. Leave smtp.host empty to use mail().
 * Either way, set SPF and DKIM for the domain or confirmations go to spam.
 */
$mail = [
    /*
     * 'from' MUST be on this domain. A message claiming to come from a
     * gmail.com address but sent through Hostinger fails SPF and gets binned
     * or spam-filed — so the café's own address sends it, and replies are
     * pointed at Nicholas's inbox instead.
     *
     * This address does not need a mailbox to send from. Create one at
     * hPanel > Emails when you want to receive at it too, then fill in the
     * smtp block below.
     */
    'from'      => 'bookings@octoberdining.ie',
    'from_name' => 'October Chinese Dining',
    'reply_to'  => 'nicholas.rooney2010@gmail.com',
    'log_file'  => __DIR__ . '/../data/mail.log',

    'smtp' => [
        // Hostinger's outgoing mail server. Takes effect once 'user' below is
        // filled in with the mailbox address and the password is set in
        // includes/secrets/db.php.
        'host'     => 'smtp.hostinger.com',
        'port'     => 587,
        'security' => 'tls',        // 'tls' (STARTTLS), 'ssl', or '' for none
        'user'     => '',           // the full email address
        'pass'     => '',           // put this in includes/secrets/db.php
        'timeout'  => 10,
    ],
];

/*
 * Google Calendar sync (one way: website -> Google).
 *
 * Setup is documented in docs/google-calendar-setup.md. In short: create a
 * service account, put its JSON key at the path below, then share the calendar
 * with the service account's email address giving it "Make changes to events".
 *
 * Leave 'enabled' false until the key file is in place. Sync failures never
 * block a booking either way — they are written to the log file instead.
 */
$calendar = [
    'enabled'          => false,
    'calendar_id'      => '',   // from Google Calendar > Settings > Integrate calendar
    'key_file'         => __DIR__ . '/secrets/google-service-account.json',
    'token_cache'      => __DIR__ . '/../data/.google-token.json',
    'log_file'         => __DIR__ . '/../data/calendar-sync.log',
    'timezone'         => 'Europe/Dublin',
    'duration_minutes' => 60,   // how long a table is held in the calendar

    // Token for calendar-test.php. Set to a long random string to run the test
    // page, then blank it again when you are finished.
    'test_token'       => '',
];

/*
 * Click and collect ordering (order.php). Collection only: no delivery.
 *
 * Orders are for TODAY only, collected between opening time plus the prep
 * time and closing time minus last_order_before_close. The opening hours come
 * from $booking['service_hours'] above, so ordering follows the same hours.
 *
 * Payment is taken in the restaurant when the order is collected. Online card
 * payment (e.g. SumUp) can be added later without changing the order flow.
 */
$ordering = [
    // false takes ordering off the site: the Order link disappears and
    // order.php sends people back to the home page. Set to true to bring it back.
    'enabled' => false,

    // Minutes the kitchen needs. The earliest collection time is now + this.
    'prep_minutes' => 25,

    // Collection times are offered every this many minutes.
    'slot_minutes' => 15,

    // The last collection time is this many minutes before closing.
    'last_order_before_close' => 15,

    // Orders the kitchen can take for one collection time. 0 = no limit.
    'max_orders_per_slot' => 4,

    // Lunch menu dishes can only be collected before this time.
    'lunch_until' => '16:00',

    // Smallest order in euro. 0 = no minimum.
    'min_order' => 0,

    // Most of one dish in a single order, to catch typos like 50 instead of 5.
    'max_qty_per_item' => 20,

    // Shown at checkout and in the confirmation email.
    'payment_note' => 'Pay when you collect, by card or cash.',

    // Who is emailed when an order comes in. Each person gets their own copy.
    'notify_email' => [
        'nicholas.rooney2010@gmail.com',
        // add the restaurant's own inbox here
    ],
];

/*
 * Google Analytics 4. Not a secret — it appears in every page's source.
 * Loaded only after a visitor accepts cookies; see includes/analytics.php.
 * Leave empty to switch analytics off entirely.
 */
$analytics = [
    'ga4_id' => '',   // create a GA4 property for October and paste its G- ID
];

/*
 * Event catering. The public catering page from the Cilantro build was
 * Mexican-specific and has been removed, so keep 'enabled' false until a new
 * event-catering.php is written for October.
 *
 * Package prices are NOT typed here. Each package names the menu dishes it is
 * built from, and its "from" price per person is read live from
 * includes/menu-data.php: the cheapest dish in each group, added together.
 * Change a price on the menu and the catering price follows, so the two can
 * never disagree.
 *
 * If a named dish is ever removed from the menu, that package shows
 * "price on request" instead of a wrong number.
 */
$catering = [
    /*
     * Is the catering page live?
     *
     * false takes event-catering.php offline: it answers 404, the nav link
     * and the home page panel disappear, and it drops out of the sitemap.
     * Nothing is deleted and enquiries already taken stay in the staff area.
     * Set it back to true to put the page back.
     */
    'enabled' => false,

    // Who is emailed when a CATERING ENQUIRY comes in.
    'notify_email' => [
        'nicholas.rooney2010@gmail.com',
    ],

    'min_guests' => 20,
    'max_guests' => 200,

    // Pills on the price card. Each suggests a starting package; the visitor
    // can switch.
    'occasions' => [
        'office_lunch' => ['label' => 'Office lunch',     'package' => 'dumpling_platters'],
        'birthday'     => ['label' => 'Birthday',         'package' => 'banquet'],
        'family'       => ['label' => 'Family gathering', 'package' => 'banquet'],
        'other'        => ['label' => 'Something else',   'package' => 'dumpling_platters'],
    ],

    // 'price_from' is a list of groups: the price per person is the cheapest
    // dish in each group, summed.
    'packages' => [
        'dumpling_platters' => [
            'name'       => 'Dumpling platters',
            'includes'   => 'A plate of pan-fried or steamed dumplings each',
            'price_from' => [['Pan-fried Prawn & Chive Dumplings', 'Pan-fried Chicken & Vegetable Dumplings', 'Pan-fried Mixed Vegetable Dumplings', 'Steamed Beef & Vegetable Dumplings']],
        ],
        'banquet' => [
            'name'       => 'October banquet',
            'includes'   => 'A starter and a main each, served for sharing',
            'price_from' => [
                ['Vegetable Spring Rolls', 'Bao Buns with Crispy Duck', 'Sesame Prawn on Toast'],
                ['Stir-fried Chicken Fillet', 'Sweet & Sour Chicken', 'Sichuan Spicy Chicken'],
            ],
        ],
    ],

    /*
     * Catering videos. The video section stays hidden until something is listed
     * here, so the page never shows an empty "coming soon" box. Either:
     *
     *     ['title' => 'Taco bar at a wedding', 'file' => 'videos/wedding.mp4', 'poster' => 'images/wedding.jpg'],
     *     ['title' => 'Office lunch for 60',   'youtube' => 'dQw4w9WgXcQ'],
     */
    'videos' => [],
];

/*
 * Everything below is derived from service_hours. One source of truth: change
 * the hours above and the site, the schema and the booking form all follow.
 */
require_once __DIR__ . '/hours.php';

$site['hours']     = hours_display($booking['service_hours']);
$booking['times']  = hours_all_slots($booking['service_hours']);

/*
 * Credentials live outside this file so they never reach Git.
 *
 * Preferred location is one level above public_html, where the web server
 * cannot serve it at all. includes/secrets/db.php is the fallback; it is
 * gitignored and blocked by .htaccess.
 *
 * The file just overrides what it needs, for example:
 *
 *     <?php
 *     $db['name'] = 'u123_october';
 *     $db['user'] = 'u123_october';
 *     $db['pass'] = '...';
 *     $staff['password_hash'] = '$2y$...';
 */
foreach ([__DIR__ . '/../../private/secrets.php', __DIR__ . '/secrets/db.php'] as $secretsFile) {
    if (is_readable($secretsFile)) {
        require $secretsFile;
        break;
    }
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * A stylesheet or script URL with a version stamp on it.
 *
 * .htaccess tells browsers to keep CSS and JS for a week, which is right for
 * speed and wrong the moment we change one: returning visitors keep the old
 * file until it expires. Stamping the modification time onto the URL makes an
 * edited file a different URL, so it is fetched immediately and an unchanged
 * one still comes from cache.
 *
 * Returns a ROOT-relative URL. It must be root-relative: a page at /staff/
 * asking for 'staff/staff.css' would look for /staff/staff/staff.css.
 *
 * @param string $path  from the web root, e.g. 'css/style.css'
 */
function asset(string $path): string
{
    $path  = ltrim($path, '/');
    $file  = __DIR__ . '/../' . $path;
    $stamp = is_file($file) ? filemtime($file) : time();
    return '/' . $path . '?v=' . $stamp;
}

/**
 * How a stored seating value should read now.
 *
 * Bookings taken before the seating options were renamed still hold the old
 * wording — "Booth inside" from when booths were on the form, and the longer
 * "Stools inside" / "Table outside". They are relabelled here so old bookings
 * read the same as new ones, whether or not db/relabel-seating.php has been
 * run against the live database.
 */
function booking_seating_label(?string $seating): string
{
    $seating = trim((string) $seating);
    if ($seating === '') {
        return 'No preference';
    }

    $legacy = [
        'booth inside'  => 'Stools',
        'booth'         => 'Stools',
        'stools inside' => 'Stools',
        'table outside' => 'Table',
        'table inside'  => 'Table',
    ];

    return $legacy[mb_strtolower($seating)] ?? $seating;
}

/**
 * Whether prices are shown anywhere on the site. See $site['show_prices'].
 */
/** Is online table booking switched on? See $booking['online']. */
function booking_online(): bool
{
    return !empty($GLOBALS['booking']['online']);
}

/** Is click and collect switched on? See $ordering['enabled']. */
function ordering_online(): bool
{
    return !empty($GLOBALS['ordering']['enabled']);
}

/** Where a "book" button goes: the booking page, or the phone when booking is off. */
function book_href(string $root = ''): string
{
    global $site;
    if (booking_online()) {
        return $root . 'booking.php';
    }
    return $site['phone'] ? 'tel:+353' . ltrim(preg_replace('/[^0-9]/', '', $site['phone']), '0') : $root . 'index.php#visit';
}

/** What a "book" button says. */
function book_label(): string
{
    return booking_online() ? 'Book a table' : 'Call to book';
}

/** Sends a switched-off page back to the home page. */
function redirect_home_unless(bool $on, string $root = ''): void
{
    if (!$on) {
        header('Location: ' . $root . 'index.php', true, 302);
        exit;
    }
}

function show_prices(): bool
{
    global $site;
    return !empty($site['show_prices']);
}

/**
 * Takes the euro amounts out of a line of menu text, for when prices are off:
 *
 *     "Add bacon +€3.50 or salmon +€5.00"  ->  "Add bacon or salmon"
 */
function strip_prices(string $text): string
{
    $text = preg_replace('/\s*\+?\s*€\s*\d+(?:[.,]\d{1,2})?/u', '', $text);
    return trim(preg_replace('/\s{2,}/', ' ', $text));
}
