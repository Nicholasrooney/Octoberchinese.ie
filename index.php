<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle       = 'October Chinese Dining | Chinese Restaurant in Dún Laoghaire';
$pageDescription = 'October is a Chinese restaurant on George’s Street Upper, Dún Laoghaire. Sichuan, Hunan and Cantonese dishes for sharing, handmade dumplings, and lunch from 12:30. Rated 5.0 on Google.' . (booking_online() ? ' Book a table online.' : '');
$pageKeywords    = 'Chinese restaurant Dún Laoghaire, Chinese restaurant Dun Laoghaire, Sichuan food Dublin, dumplings Dún Laoghaire, Chinese lunch Dún Laoghaire, George’s Street restaurant';
$activePage      = 'home';
$heroHeader      = true;

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/menu-data.php';
require_once __DIR__ . '/includes/reviews.php';

// Answers here are all true of the restaurant — they come from the menus, the
// address and the booking form. A wrong answer in a rich result is worse than
// no rich result at all.
$pageSchema = [
    seo_faq([
        'Where is October Chinese Dining?' =>
            'October is at 60 George’s Street Upper, Dún Laoghaire, Co. Dublin, A96 CY53.',
        'Do you take bookings?' =>
            booking_online()
                ? 'Yes. Book online for up to ' . $booking['max_guests'] . ' people, or ring ' . $site['phone'] . ' for a bigger group.'
                : 'Yes. Ring us on ' . $site['phone'] . ' to book a table.',
        'Do you serve lunch?' =>
            'Yes. The lunch menu runs from 12:30 to 16:00, with signature rice bowls, noodle soups and handmade dumplings.',
        'Are there vegetarian options?' =>
            'Yes. Vegetarian dishes are marked on the menu, including smashed cucumber salad, dry-fried green beans, crispy aubergines, tofu claypot and vegetable dumplings.',
        'Do you cater for allergies?' =>
            'Allergen numbers are listed next to every dish on our menu. Please tell a member of staff about any allergies or dietary needs before ordering.',
        'Can you take large groups?' =>
            'Yes. We regularly seat large groups. Ring us to arrange it. Parties of 5 or more are subject to a 10% service charge, all of which goes to the staff.',
    ]),
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/directions.php';

// Pull a dish's live price from the menu, so the home page never disagrees.
function menu_lookup(array $menu, string $name): ?array
{
    foreach ($menu as $section) {
        foreach ($section['items'] as $item) {
            if (strcasecmp($item['name'], $name) === 0) {
                return $item;
            }
        }
    }
    return null;
}

$signatures = [
    ['dish' => 'Steamed Sea Bass with Hunan Chopped Chillies', 'img' => 'sea-bass-chillies.jpg',
     'alt' => 'Sea bass fillets laid in a chevron under bright red Hunan chopped chillies', 'tag' => 'Chef’s recommendation'],
    ['dish' => 'Kataifi Wrapped King Prawns', 'img' => 'kataifi-prawns.jpg',
     'alt' => 'Three king prawns wrapped in golden kataifi with a brass pot of mango lime sauce', 'tag' => 'To start'],
    ['dish' => 'Sichuan Spicy Chicken', 'img' => 'sichuan-chicken.jpg',
     'alt' => 'Sichuan spicy chicken piled high with dried chillies, star anise scattered around the plate', 'tag' => 'October special'],
    ['dish' => 'Bao Buns with Crispy Duck', 'img' => 'bao-buns-overhead.jpg',
     'alt' => 'Two steamed bao buns branded with the October tree, in a bamboo steamer', 'tag' => 'To start'],
    ['dish' => 'Stir-fried Beef Fillet', 'img' => 'beef-fillet-overhead.jpg',
     'alt' => 'Stir-fried beef fillet with wild chilli peppers and celery in a wide white bowl', 'tag' => 'October special'],
    ['dish' => 'Pan-fried Prawn & Chive Dumplings', 'img' => 'dumplings-overhead.jpg',
     'alt' => 'Four golden pan-fried dumplings with dipping sauce and a small salad', 'tag' => 'Dumplings'],
];
foreach ($signatures as &$sig) {
    $sig['item'] = menu_lookup($menu, $sig['dish']);
}
unset($sig);

$lunchFrom = min(array_map(fn($i) => (float) $i['price'], array_merge(...array_column($lunchMenu, 'items'))));
?>

<section class="hero hero-split" aria-labelledby="hero-title">
    <div class="hero-media">
        <img src="images/oct/beef-fillet-steam.jpg" alt="Stir-fried beef fillet with wild chilli pepper, steam rising from the bowl"
             style="object-position: 52% 60%" width="1800" height="1440" fetchpriority="high">
    </div>
    <?php render_arches('arches hero-arches'); ?>
    <div class="hero-inner">
        <p class="eyebrow reveal">Dún Laoghaire · George’s Street Upper</p>
        <h1 id="hero-title" class="hero-title reveal">
            <span class="hero-word">October</span>
            <span class="hero-cn" lang="zh-Hans"><?= e($site['name_cn']) ?></span>
        </h1>
        <p class="hero-lede reveal">Sichuan heat, Hunan chillies and Cantonese classics, cooked to share with the people you love.</p>
        <div class="hero-actions reveal">
            <a class="btn btn-gold" href="<?= e(book_href()) ?>"><?= e(book_label()) ?></a>
            <a class="btn btn-ghost" href="menu.php">Explore the menu</a>
        </div>
        <a class="rating-badge reveal" href="#reviews">
            <span class="rating-stars"><?= str_repeat(icon('star'), 5) ?></span>
            <span><strong><?= e($googleRating['rating']) ?></strong> on Google · <?= (int) $googleRating['count'] ?> reviews</span>
        </a>
    </div>
    <a class="hero-scroll" href="#story" aria-label="Scroll to read more"><span></span></a>
</section>

<section class="section story" id="story" aria-labelledby="story-title">
    <div class="container story-grid">
        <div class="story-photo reveal">
            <div class="arch-frame">
                <img src="images/interior.jpg" alt="The dining room: a gold tree and copper arches on a deep green wall, warm pendant lights over wooden tables"
                     width="1024" height="1432" loading="lazy">
            </div>
            <span class="story-seal" lang="zh-Hans" aria-hidden="true">食悦</span>
        </div>
        <div class="story-text reveal">
            <p class="eyebrow">Our table</p>
            <h2 id="story-title">Good food brings <em>people together</em></h2>
            <p class="lede"><span lang="zh-Hans">食悦</span> means the joy of eating. That is the whole idea behind October.</p>
            <p>The menu travels across China: Hunan sea bass steamed under chopped chillies, Sichuan chicken tossed through dried peppers, Cantonese roast duck, and beef short ribs slow-cooked with Shaoxing wine and aged tangerine peel.</p>
            <p>Every plate is made for the middle of the table. Order a few, pass them round, and stay a while under the copper arches.</p>
            <ul class="story-points">
                <li><?= icon('leaf') ?> Plenty for vegetarians</li>
                <li><?= icon('people') ?> Big groups welcome</li>
                <li><?= icon('clock') ?> Lunch from 12:30</li>
            </ul>
        </div>
    </div>
</section>

<section class="section signatures" aria-labelledby="sig-title">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <p class="eyebrow">From the kitchen</p>
                <h2 id="sig-title">Signature dishes</h2>
            </div>
            <a class="text-link" href="menu.php">See the full menu <?= icon('arrow') ?></a>
        </div>
        <ul class="sig-grid">
            <?php foreach ($signatures as $i => $sig): if (!$sig['item']) continue; $item = $sig['item']; ?>
                <li class="sig-card reveal" style="--d: <?= $i * 70 ?>ms">
                    <a href="menu.php#<?= e($i === 1 || $i === 3 ? 'appetisers' : ($i === 5 ? 'dumplings' : 'october-special')) ?>">
                        <div class="sig-photo">
                            <img src="images/<?= e($sig['img']) ?>" alt="<?= e($sig['alt']) ?>" loading="lazy">
                            <span class="sig-tag"><?= e($sig['tag']) ?></span>
                        </div>
                        <div class="sig-body">
                            <div class="sig-top">
                                <h3><?= e($item['name']) ?></h3>
                                <?php if (show_prices()): ?>
                                    <span class="sig-price">€<?= e(fmt_price($item['price'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="sig-cn" lang="zh-Hans"><?= e($item['cn']) ?></p>
                            <?php if ($item['desc']): ?><p class="sig-desc"><?= e($item['desc']) ?></p><?php endif; ?>
                            <?= spice_meter((int) $item['spice']) ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="feature-band" aria-labelledby="pour-title">
    <img class="feature-band-img" src="images/sea-bass-pour.jpg" alt="Hot oil ladled over steamed sea bass with Hunan chopped chillies" loading="lazy">
    <div class="feature-band-inner reveal">
        <p class="eyebrow">Chef’s recommendation</p>
        <h2 id="pour-title">Sizzling oil, <em>Hunan chillies</em></h2>
        <p>Steamed sea bass under a blanket of Hunan chopped chillies, finished with a ladle of hot oil poured over the top.</p>
        <a class="btn btn-ghost" href="menu.php#october-special">October specials</a>
    </div>
</section>

<section class="section lunch" aria-labelledby="lunch-title">
    <div class="container lunch-grid">
        <div class="lunch-text reveal">
            <p class="eyebrow">Lunch · <?= e($lunchHours) ?></p>
            <h2 id="lunch-title">A proper bowl at <em>lunchtime</em></h2>
            <p>Signature rice bowls, slow-braised noodle soups and handmade dumplings, from €<?= e(fmt_price(number_format($lunchFrom, 2))) ?>. Every rice bowl comes with half a soy-braised egg.</p>
            <ul class="lunch-list">
                <?php foreach ([$lunchMenu[1]['items'][0], $lunchMenu[1]['items'][1], $lunchMenu[0]['items'][2], $lunchMenu[0]['items'][1]] as $li): ?>
                    <li><span><?= e($li['name']) ?></span><span class="dots" aria-hidden="true"></span><?php if (show_prices()): ?><span>€<?= e(fmt_price($li['price'])) ?></span><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn-gold" href="menu.php?tab=lunch#lunch">See the lunch menu</a>
        </div>
        <div class="lunch-photos reveal">
            <img class="lp-1" src="images/beef-noodle-soup-overhead.jpg" alt="Taiwanese braised beef noodle soup in a blue and white bowl, with pak choi and a soy-braised egg" loading="lazy">
            <img class="lp-2" src="images/wonton-noodle-soup.jpg" alt="Wonton noodle soup with prawn wontons and greens" loading="lazy">
        </div>
    </div>
</section>

<section class="section reviews" id="reviews" aria-labelledby="reviews-title">
    <div class="container">
        <div class="reviews-head reveal">
            <p class="eyebrow">What guests say</p>
            <h2 id="reviews-title">Rated <?= e($googleRating['rating']) ?> on Google</h2>
            <p class="reviews-meta"><span class="rating-stars"><?= str_repeat(icon('star'), 5) ?></span> from <?= (int) $googleRating['count'] ?> reviews</p>
        </div>
    </div>
    <div class="review-track" data-reviews tabindex="0" aria-label="Guest reviews, scroll sideways for more">
        <?php foreach ($reviews as $i => $r): ?>
            <figure class="review-card">
                <span class="rating-stars"><?= str_repeat(icon('star'), 5) ?></span>
                <blockquote><p>“<?= e($r['text']) ?>”</p></blockquote>
                <figcaption>
                    <span class="review-avatar" aria-hidden="true"><?= e(mb_substr($r['name'], 0, 1)) ?></span>
                    <span><strong><?= e($r['name']) ?></strong><small>Google review</small></span>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
    <div class="container review-controls">
        <button type="button" class="round-btn" data-reviews-prev aria-label="Previous reviews">&#8249;</button>
        <button type="button" class="round-btn" data-reviews-next aria-label="More reviews">&#8250;</button>
    </div>
</section>

<section class="section groups" aria-labelledby="groups-title">
    <div class="container"><div class="groups-inner reveal">
        <div class="groups-photo">
            <img src="images/bao-buns.jpg" alt="Bao buns branded with the October tree, in a bamboo steamer" loading="lazy">
        </div>
        <div class="groups-text">
            <p class="eyebrow">Birthdays, families, teams</p>
            <h2 id="groups-title">Bringing a crowd?</h2>
            <p>We love a long table. We have looked after a group of fifteen, with plenty to share and food that comes out fast. <?php if (booking_online()): ?>Book online for up to <?= (int) $booking['max_guests'] ?>, or ring us and we will set it up.<?php else: ?>Ring us and we will set it up.<?php endif; ?></p>
            <div class="groups-actions">
                <?php if ($telHref): ?>
                    <a class="btn btn-gold" href="<?= e($telHref) ?>"><?= icon('phone') ?> Call <?= e($site['phone']) ?></a>
                <?php endif; ?>
                <?php if (booking_online()): ?><a class="btn btn-ghost" href="booking.php">Book online</a><?php endif; ?>
            </div>
            <p class="small-note">Parties of 5 or more are subject to a 10% service charge, all of which goes directly to the staff.</p>
        </div>
    </div></div>
</section>

<section class="section visit" id="visit" aria-labelledby="visit-title">
    <div class="container visit-grid">
        <div class="visit-text reveal">
            <p class="eyebrow">Visit us</p>
            <h2 id="visit-title">Find the green door</h2>
            <p>Look for the green shopfront with the gold tree, on Dún Laoghaire’s main street.</p>
            <?php render_directions_actions($site); ?>
            <?php if (!empty($site['hours'])): ?>
                <div class="visit-hours">
                    <p class="visit-label"><?= icon('clock') ?> Opening hours</p>
                    <dl class="hours hours-large">
                        <?php foreach ($site['hours'] as $days => $time): ?>
                            <div><dt><?= e($days) ?></dt><dd><?= e($time) ?></dd></div>
                        <?php endforeach; ?>
                        <div><dt>Lunch menu</dt><dd><?= e($lunchHours) ?></dd></div>
                    </dl>
                </div>
            <?php endif; ?>
            <?php if ($telHref): ?>
                <p class="visit-phone"><?= icon('phone') ?> <a href="<?= e($telHref) ?>"><?= e($site['phone']) ?></a></p>
            <?php endif; ?>
        </div>
        <a class="visit-photo reveal" href="gallery.php">
            <img src="images/storefront-tall.jpg" alt="October’s green shopfront on George’s Street Upper with the gold tree sign" loading="lazy">
            <span class="visit-photo-label">View the gallery <?= icon('arrow') ?></span>
        </a>
    </div>
</section>

<?php render_map_section($site); ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
