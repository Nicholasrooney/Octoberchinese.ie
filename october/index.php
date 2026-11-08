<?php
/*
 * October, second design: home page in the style of the China Hunan site.
 */
require_once __DIR__ . '/../includes/october-theme.php';
require_once __DIR__ . '/../includes/menu-data.php';
require_once __DIR__ . '/../includes/reviews.php';

$lunchFrom = min(array_map(fn($i) => (float) $i['price'], array_merge(...array_column($lunchMenu, 'items'))));
$img = OCT_ROOT . 'images/oct/';

oct_header(
    'October Chinese Dining | Chinese Restaurant in Dún Laoghaire',
    'October is a Chinese restaurant on George’s Street Upper, Dún Laoghaire. Sichuan, Hunan and Cantonese dishes for sharing, handmade dumplings and lunch from 12:30.',
    'home'
);
?>

<section class="oc-hero" aria-labelledby="oc-hero-title">
    <img class="oc-hero-bg" src="<?= $img ?>sichuan-chicken-anise.jpg" alt="" aria-hidden="true">
    <div class="oc-wrap oc-hero-inner">
        <h1 id="oc-hero-title" class="oc-visually-hidden">October Chinese Dining, Dún Laoghaire</h1>
        <a class="oc-seal" href="#reviews" aria-label="Rated <?= e($googleRating['rating']) ?> on Google from <?= (int) $googleRating['count'] ?> reviews">
            <span class="oc-seal-top">Rated on Google</span>
            <span class="oc-seal-score"><?= e($googleRating['rating']) ?></span>
            <span class="oc-seal-stars"><?= str_repeat(icon('star'), 5) ?></span>
            <span class="oc-seal-bottom"><?= (int) $googleRating['count'] ?> reviews</span>
        </a>
        <ul class="oc-triad">
            <li><a href="<?= e(book_href(OCT_ROOT)) ?>"><span class="oc-triad-en">Booking</span><span class="oc-triad-cn" lang="zh-Hans"><?= booking_online() ? '预订餐桌' : '电话预订' ?></span></a></li>
            <li><a href="menu.php"><span class="oc-triad-en">Menu</span><span class="oc-triad-cn" lang="zh-Hans">菜单</span></a></li>
            <li><a href="gallery.php"><span class="oc-triad-en">Gallery</span><span class="oc-triad-cn" lang="zh-Hans">美味图鉴</span></a></li>
        </ul>
        <p class="oc-hero-address">60 George’s Street Upper, Dún Laoghaire, <?= e($site['eircode']) ?></p>
    </div>
</section>

<section class="oc-welcome oc-peach" aria-labelledby="oc-welcome-title">
    <p class="oc-vertical oc-vertical-left" lang="zh-Hans" aria-hidden="true">食悦 · 美食让人相聚 · 一桌好菜 与亲友共享</p>
    <div class="oc-wrap oc-narrow oc-center oc-reveal">
        <?= oct_eyebrow('欢迎光临', 'About us') ?>
        <h2 id="oc-welcome-title">Welcome to October</h2>
        <p class="oc-welcome-badge">Rated <?= e($googleRating['rating']) ?> on Google</p>
        <p class="oc-welcome-sub">Chinese dining on George’s Street Upper, Dún Laoghaire.</p>
        <p class="oc-lead"><span lang="zh-Hans">食悦</span> means the joy of eating. That is the whole idea behind October.</p>
        <p>The menu travels across China: Hunan sea bass steamed under chopped chillies, Sichuan chicken tossed through dried peppers, Cantonese roast duck, and beef short ribs slow-cooked with Shaoxing wine and aged tangerine peel.</p>
        <p>Every plate is made for the middle of the table. Order a few, pass them round, and stay a while under the gold tree and the copper arches.</p>
        <figure class="oc-welcome-photo">
            <img src="<?= OCT_ROOT ?>images/interior.jpg" alt="The dining room: a gold tree and copper arches on a deep green wall, warm pendant lights over wooden tables" loading="lazy">
        </figure>
    </div>
    <p class="oc-vertical oc-vertical-right" aria-hidden="true">Sichuan heat, Hunan chillies and Cantonese classics, cooked to share with the people you love.</p>
</section>

<section class="oc-strip" aria-label="Dishes from the kitchen">
    <figure><img src="<?= $img ?>sichuan-chicken.jpg" alt="Sichuan spicy chicken piled high with dried chillies" loading="lazy"></figure>
    <figure><img src="<?= $img ?>sea-bass-close.jpg" alt="Steamed sea bass under Hunan chopped chillies" loading="lazy"></figure>
    <figure><img src="<?= $img ?>beef-fillet-steam.jpg" alt="Stir-fried beef fillet with wild chilli pepper, steam rising" loading="lazy"></figure>
</section>

<section class="oc-feature oc-peach" aria-labelledby="oc-f1">
    <div class="oc-feature-grid">
        <div class="oc-feature-text oc-reveal">
            <?= oct_eyebrow('剁椒鲈鱼', 'Chef’s recommendation') ?>
            <h2 id="oc-f1">Discover our sea bass with Hunan chillies</h2>
            <p>Fillets of sea bass steamed under a blanket of bright red Hunan chopped chillies, then finished with a ladle of hot oil poured over the top. Laid out in a chevron and made for the middle of the table.</p>
            <a class="oc-btn" href="menu.php#october-special">View the October specials</a>
        </div>
        <figure class="oc-feature-img oc-bleed-right oc-reveal">
            <img src="<?= $img ?>sea-bass-top.jpg" alt="Steamed sea bass fillets laid in a chevron under Hunan chopped chillies" loading="lazy">
        </figure>
    </div>
</section>

<section class="oc-feature oc-peach oc-feature-flip" aria-labelledby="oc-f2">
    <div class="oc-feature-grid">
        <figure class="oc-feature-img oc-bleed-left oc-reveal">
            <img src="<?= $img ?>sichuan-chicken-anise.jpg" alt="Sichuan spicy chicken with dried chillies and star anise" loading="lazy">
        </figure>
        <div class="oc-feature-text oc-reveal">
            <?= oct_eyebrow('川湘粤风味', 'Across China') ?>
            <h2 id="oc-f2">A journey of Chinese flavours, made to share.</h2>
            <p>Sichuan spicy chicken tossed through dried chillies. Beef fillet stir-fried with wild chilli pepper. Kataifi-wrapped king prawns with mango lime sauce, and bao buns with crispy duck, stamped with the October tree. Order a few and pass them round.</p>
            <a class="oc-btn" href="menu.php">View our menu</a>
        </div>
    </div>
</section>

<section class="oc-banner" aria-labelledby="oc-banner-title">
    <img class="oc-banner-bg" src="<?= $img ?>kataifi-prawns-pour.jpg" alt="" aria-hidden="true">
    <div class="oc-wrap oc-narrow oc-center oc-reveal">
        <?= oct_eyebrow('菜单', 'Menu') ?>
        <h2 id="oc-banner-title">A menu designed for every occasion</h2>
        <p>From handmade dumplings and noodle soups at lunchtime to Sichuan, Hunan and Cantonese dishes in the evening, with plenty for vegetarians. Allergens are listed beside every dish.</p>
        <a class="oc-btn oc-btn-light" href="menu.php">View our menus</a>
    </div>
</section>

<section class="oc-feature oc-peach" aria-labelledby="oc-f3">
    <div class="oc-feature-grid">
        <div class="oc-feature-text oc-reveal">
            <?= oct_eyebrow('午市', 'Lunch · ' . $lunchHours) ?>
            <h2 id="oc-f3">A proper bowl at lunchtime</h2>
            <p>Signature rice bowls, Taiwanese braised beef noodle soup, wonton noodle soup and handmade dumplings<?php if (show_prices()): ?>, from €<?= e(fmt_price(number_format($lunchFrom, 2))) ?><?php endif; ?>. Every rice bowl comes with half a soy-braised egg.</p>
            <a class="oc-btn" href="menu.php?tab=lunch">View the lunch menu</a>
        </div>
        <figure class="oc-feature-img oc-bleed-right oc-reveal">
            <img src="<?= $img ?>beef-noodle-close.jpg" alt="Taiwanese braised beef noodle soup in a blue and white bowl with pak choi and a soy-braised egg" loading="lazy">
        </figure>
    </div>
</section>

<section class="oc-feature oc-peach oc-feature-flip" aria-labelledby="oc-f4">
    <div class="oc-feature-grid">
        <figure class="oc-feature-img oc-bleed-left oc-reveal">
            <img src="<?= $img ?>bao-buns-open.jpg" alt="Two bao buns stamped with the October tree, in a bamboo steamer" loading="lazy">
        </figure>
        <div class="oc-feature-text oc-reveal">
            <?= oct_eyebrow('聚会宴请', 'Groups & celebrations') ?>
            <h2 id="oc-f4">Celebrate your special moments with us.</h2>
            <p>Birthdays, families, work teams: we love a long table, and we have looked after groups of fifteen with plenty to share and food that comes out fast. <?php if (booking_online()): ?>Book online for up to <?= (int) $booking['max_guests'] ?> guests, or ring us and we will set it up.<?php else: ?>Ring us and we will set it up.<?php endif; ?></p>
            <div class="oc-btn-row">
                <?php if (oct_tel()): ?><a class="oc-btn" href="<?= e(oct_tel()) ?>">Call <?= e($site['phone']) ?></a><?php endif; ?>
                <?php if (booking_online()): ?><a class="oc-btn" href="<?= OCT_ROOT ?>booking.php">Book online</a><?php endif; ?>
            </div>
            <p class="oc-small">Parties of 5 or more are subject to a 10% service charge, all of which goes directly to the staff.</p>
        </div>
    </div>
</section>

<section class="oc-reviews" id="reviews" aria-labelledby="oc-reviews-title">
    <div class="oc-wrap">
        <div class="oc-center oc-reveal">
            <?= oct_eyebrow('食客评价', 'What guests say') ?>
            <h2 id="oc-reviews-title">Rated <?= e($googleRating['rating']) ?> on Google</h2>
            <p class="oc-reviews-meta"><span class="oc-stars"><?= str_repeat(icon('star'), 5) ?></span> from <?= (int) $googleRating['count'] ?> reviews</p>
        </div>
        <div class="oc-review-grid">
            <?php foreach (array_slice($reviews, 0, 3) as $r): ?>
                <figure class="oc-review oc-reveal">
                    <blockquote><p>“<?= e($r['text']) ?>”</p></blockquote>
                    <figcaption><?= e($r['name']) ?> · Google review</figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php oct_footer(); ?>
