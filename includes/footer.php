</main>

<footer class="site-footer">
    <?php render_arches('arches footer-arches'); ?>
    <div class="footer-inner">
        <div class="footer-brand">
            <a class="brand brand-footer" href="index.php">
                <?php render_tree_mark('brand-mark'); ?>
                <span class="brand-text">
                    <span class="brand-name">October</span>
                    <span class="brand-sub"><span lang="zh-Hans"><?= e($site['name_cn'] ?? '') ?></span> · <?= e($site['tagline'] ?? '') ?></span>
                </span>
            </a>
            <p class="footer-motto">Good food brings people together.</p>
        </div>

        <div class="footer-col">
            <p class="footer-heading">Find us</p>
            <?php if ($site['address']): ?>
                <p><?= e($site['address']) ?><?php if (!empty($site['eircode'])): ?>, <?= e($site['eircode']) ?><?php endif; ?></p>
            <?php endif; ?>
            <?php if ($site['maps_url']): ?>
                <p><a class="footer-link" href="<?= e($site['maps_url']) ?>" target="_blank" rel="noopener">Get directions</a></p>
            <?php endif; ?>
        </div>

        <div class="footer-col">
            <p class="footer-heading">Get in touch</p>
            <?php if ($site['phone']): ?>
                <p><a class="footer-link" href="tel:+353<?= e(ltrim(preg_replace('/[^0-9]/', '', $site['phone']), '0')) ?>"><?= e($site['phone']) ?></a></p>
            <?php endif; ?>
            <?php if ($site['email']): ?>
                <p><a class="footer-link" href="mailto:<?= e($site['email']) ?>"><?= e($site['email']) ?></a></p>
            <?php endif; ?>
            <?php if (!empty($site['instagram'])): ?>
                <p><a class="footer-link" href="<?= e($site['instagram']) ?>" target="_blank" rel="noopener">Instagram</a></p>
            <?php endif; ?>
            <?php if (booking_online()): ?><p><a class="footer-link" href="booking.php">Book a table</a></p><?php endif; ?>
        </div>

        <?php if (!empty($site['hours'])): ?>
            <div class="footer-col">
                <p class="footer-heading">Opening hours</p>
                <dl class="hours">
                    <?php foreach ($site['hours'] as $days => $time): ?>
                        <div><dt><?= e($days) ?></dt><dd><?= e($time) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
                <p class="footer-small">Lunch menu 12:30 – 16:00</p>
            </div>
        <?php endif; ?>
    </div>
    <p class="footer-legal">
        &copy; <?= date('Y') ?> <?= e($site['name']) ?>
        · <a href="privacy.php">Privacy</a>
        · <button type="button" class="link-button" data-cookie-settings>Cookie settings</button>
    </p>
</footer>

<?php if (!in_array($activePage ?? '', ['booking', 'order'], true)): ?>
<div class="mobile-cta" data-mobile-cta>
    <?php if (booking_online()): ?>
        <?php if ($site['phone']): ?>
            <a class="mobile-cta-call" href="tel:+353<?= e(ltrim(preg_replace('/[^0-9]/', '', $site['phone']), '0')) ?>"><?= icon('phone') ?> Call</a>
        <?php endif; ?>
        <a class="mobile-cta-book" href="booking.php">Book a table</a>
    <?php elseif ($site['phone']): ?>
        <a class="mobile-cta-book" href="tel:+353<?= e(ltrim(preg_replace('/[^0-9]/', '', $site['phone']), '0')) ?>"><?= icon('phone') ?> Call to book</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/cookie-banner.php';
render_cookie_banner();
?>

<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<?php foreach ($pageScripts ?? [] as $js): ?>
<script src="<?= e(asset($js)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
