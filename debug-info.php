<?php
/**
 * Debug info for WPCCM plugin
 * Add this to check if everything loads correctly
 */

add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
    <script>
    // //console.log('=== WPCCM Debug Info ===');
    // //console.log('jQuery loaded:', typeof jQuery !== 'undefined');
    // //console.log('WPCCM global:', typeof WPCCM !== 'undefined');
    if (typeof WPCCM !== 'undefined') {
        // //console.log('WPCCM options:', WPCCM.options);
        // //console.log('WPCCM categories:', WPCCM.categories);
        // //console.log('WPCCM texts:', WPCCM.texts);
    }
    // //console.log('Banner root element:', document.getElementById('wpccm-banner-root'));
    // //console.log('=== End WPCCM Debug ===');
    </script>
    <?php
}, 999);
