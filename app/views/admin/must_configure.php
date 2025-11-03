<?php
defined('ABSPATH') || exit;
?>
<div class="error" style="padding: 10px;">
    <?php
    printf(
        // Translators: %1$s: open bold tag, %2$s: close bold tag, %3$s: open link tag, %4$s: close link tag.
        esc_html__('%1$sMemberPress hasn\'t been configured yet.%2$s Go to the MemberPress %3$soptions page%4$s to get it setup.', 'memberpress'),
        '<b>',
        '</b>',
        '<a href="' . esc_url(admin_url('admin.php?page=memberpress-options')) . '">',
        '</a>'
    );
    ?>
</div>
