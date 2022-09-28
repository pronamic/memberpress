<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>

<div class="mp_wrapper mp_users_subscriptions_list">
  <?php if(!empty($active_rows) || !empty($inactive_rows)): ?>
    <ul>
      <?php if(!empty($active_rows)): ?>
        <?php foreach($active_rows as $arow): ?>
          <?php $alt_row = ($alt_row)?'':'mp_users_subscriptions_list_alt'; ?>
          <li class="<?php echo $alt_row; ?>">
            <?php
              if(!empty($arow->access_url)) {
                echo '<a href="' . esc_url($arow->access_url) .'">' . esc_html($arow->membership) . '</a>';
              }
              else {
                echo esc_html($arow->membership);
              }
            ?><br/>
            <?php _ex('Expires', 'ui', 'memberpress'); ?>: <?php echo esc_html($arow->expires); ?>
            <?php if($arow->renewal_link): ?>
              <br/><a href="<?php echo esc_url($arow->renewal_link); ?>"><?php _ex('Renew', 'ui', 'memberpress'); ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if(!empty($inactive_rows)): ?>
        <?php foreach($inactive_rows as $irow): ?>
          <?php $alt_row = ($alt_row)?'':'mp_users_subscriptions_list_alt'; ?>
          <li class="<?php echo $alt_row; ?>">
            <?php echo esc_html($irow->membership); ?><br/>
            <?php _ex('Expired', 'ui', 'memberpress'); ?> - <a href="<?php echo esc_url($irow->purchase_link); ?>"><?php _ex('Subscribe', 'ui', 'memberpress'); ?></a>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  <?php else: ?>
  <p><?php _ex('You have no subscriptions', 'ui', 'memberpress'); ?></p>
  <?php endif; ?>
</div>
