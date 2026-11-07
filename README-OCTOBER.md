# October Chinese Dining website

Built on the Cilantro Café codebase: same booking system, staff diary,
database, emails and Google Calendar sync. The public pages, design,
menu and photos are new.

## Before going live: confirm these

All of these are in `includes/config.php` unless noted.

1. **Opening hours** (`service_hours`). These are ASSUMED as 12:30–21:30 every
   day, taken from Google ("closes 9:30 PM") and the lunch menu. They drive
   the hours shown on the site, the hours sent to Google, and the booking slots.
2. **Domain** (`domain`). Set to `octoberdining.ie` as a placeholder. Also update
   `robots.txt` (sitemap line) and `mail.from`.
3. **Booking emails** (`notify_email`, `change_notify_email`, catering
   `notify_email`). Only Nick's address for now. Add the restaurant's inbox.
4. **Instagram / email**: empty, so hidden. Fill in to show them.
5. **Google Analytics** (`ga4_id`): cleared. Create a property for October.
6. **Booking rules**: up to 8 guests online, 4 bookings per hour, tables held
   90 minutes, seating Banquette / Table. Adjust to how the restaurant works.
7. **Database + staff password**: run `/setup.php` once on the new hosting,
   exactly as for Cilantro (see `docs/SETUP-NOW.md`).

## Content

- Menu and prices: `includes/menu-data.php` (à la carte + lunch). Allergen numbers
  copied from the printed menus.
- Reviews and Google rating: `includes/reviews.php`.
- Gallery photos: list at the top of `gallery.php`; files in `/images`.

## Not carried over

- The event catering page (it was Mexican-specific). Catering stays switched
  off in config.
- Cilantro's booking data, mail log, secrets file and Git history.
- The test files that checked Cilantro's own data (`db/test-seo.php`,
  `db/test-catering.php`, `db/test-weekend.php`). `db/test.php` and
  `db/test-setup.php` still pass.
