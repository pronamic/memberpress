<div id="mepro-login-hero" class="<?php $has_welcome_image ? esc_attr_e( 'with-sidebar', 'memberpress' ) : ''; ?>">
  <div class="mepro-boxed">
  <div class="mepro-login-contents">
    <?php echo $content; ?>
  </div>
  <?php if ( $has_welcome_image ) : ?>
  <figure class="mepro-login-hero-image">
    <img src="<?php echo esc_url( $welcome_image ); ?>" />
  </figure>
  <?php endif; ?>
  </div>
</div>
