<?php
/**
 * Shortcode: [hyttenummer]
 * Viser 3 tilfeldige hyttenummerbilder som dekorativ skillelinje.
 */

function cabin_divider_shortcode($atts) {
    $attachments = b_get_attachments_by_gallery_slug('91-hytter');
    if (count($attachments) < 3) {
        return '';
    }

    // Velg 3 tilfeldige
    $random_keys = array_rand($attachments, 3);

    ob_start();
    ?>
    <div class="b-cabin-divider" role="separator">
        <?php foreach ($random_keys as $key): ?>
            <?php echo wp_get_attachment_image($attachments[$key]->ID, 'thumbnail'); ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('hyttenummer', 'cabin_divider_shortcode');
