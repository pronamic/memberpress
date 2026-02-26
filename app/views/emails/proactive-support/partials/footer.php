<?php
if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">
    <tr>
        <td style="font-size:12px;color:#6b6b6b;">
            <?php
            printf(
                '<a href="%s">%s</a>',
                esc_url($opt_out_url),
                esc_html__('Opt out of proactive support', 'memberpress')
            );
            ?>
        </td>
    </tr>
</table>
