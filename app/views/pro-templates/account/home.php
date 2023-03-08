<?php
$show_welcome_image = isset( $atts['show_welcome_image'] ) ? $atts['show_welcome_image'] : $mepr_options->design_show_account_welcome_image;
$welcome_image      = isset( $atts['welcome_image'] ) ? $atts['welcome_image'] : wp_get_attachment_url( $mepr_options->design_account_welcome_img );
?>

<h1 class="mepr_page_header"><?php echo esc_html_x( 'Profile', 'ui', 'memberpress' ); ?></h1>

<div class="mepr-account-welcome-message <?php echo $welcome_image ? 'has-welcome-image'  : ''  ?>"><?php echo $welcome_message ?></div>

<div class="mepr-profile-wrapper">
  <div id="mepr-profile-details">

  <dl class="mepr-profile-details__list">
    <dt class="">
    <?php echo esc_html_x( 'Name', 'ui', 'memberpress' ); ?>
    <button data-name="name" class="mepr-profile-details__button btn btn-link">
      <svg class="mepr-profile-details__icon" width="15" height="16" viewBox="0 0 15 16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M14.1578 2.99018L12.6403 1.47272C11.9097 0.74209 10.7013 0.74209 9.97069 1.47272L1.03453 10.3527L0.360107 14.5397C0.275804 14.9894 0.66922 15.3828 1.11884 15.2985L5.30591 14.624L14.1859 5.68789C14.9165 4.95726 14.9165 3.74891 14.1578 2.99018ZM5.98033 9.67825C6.09274 9.79065 6.26134 9.84685 6.42995 9.84685C6.57046 9.84685 6.73906 9.79065 6.85147 9.67825L10.1955 6.33421L11.0104 7.14915L6.26134 11.9263V10.7461H4.91249V9.39724H3.73224L8.50943 4.64815L9.32437 5.46308L5.98033 8.80711C5.72742 9.06002 5.72742 9.42534 5.98033 9.67825ZM2.6644 13.8091L1.84947 12.9942L2.21478 10.9428L2.7206 10.4089H3.90085V11.7577H5.2497V12.938L4.71578 13.4438L2.6644 13.8091ZM13.3147 4.81675L11.9378 6.19371L9.46487 3.72081L10.8418 2.34385C11.0947 2.09094 11.5163 2.09094 11.7692 2.34385L13.2866 3.86131C13.5676 4.14233 13.5676 4.56384 13.3147 4.81675Z" fill="#777777" />
      </svg>
    </button>
    </dt>
    <dd class="mepr-profile-details__content"><?php echo esc_html( $mepr_current_user->full_name() ); ?></dd>
    <dt class="">
    <?php echo esc_html_x( 'Email', 'ui', 'memberpress' ); ?>
    <button data-name="user_email" class="mepr-profile-details__button btn btn-link">
      <svg class="mepr-profile-details__icon" width="15" height="16" viewBox="0 0 15 16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M14.1578 2.99018L12.6403 1.47272C11.9097 0.74209 10.7013 0.74209 9.97069 1.47272L1.03453 10.3527L0.360107 14.5397C0.275804 14.9894 0.66922 15.3828 1.11884 15.2985L5.30591 14.624L14.1859 5.68789C14.9165 4.95726 14.9165 3.74891 14.1578 2.99018ZM5.98033 9.67825C6.09274 9.79065 6.26134 9.84685 6.42995 9.84685C6.57046 9.84685 6.73906 9.79065 6.85147 9.67825L10.1955 6.33421L11.0104 7.14915L6.26134 11.9263V10.7461H4.91249V9.39724H3.73224L8.50943 4.64815L9.32437 5.46308L5.98033 8.80711C5.72742 9.06002 5.72742 9.42534 5.98033 9.67825ZM2.6644 13.8091L1.84947 12.9942L2.21478 10.9428L2.7206 10.4089H3.90085V11.7577H5.2497V12.938L4.71578 13.4438L2.6644 13.8091ZM13.3147 4.81675L11.9378 6.19371L9.46487 3.72081L10.8418 2.34385C11.0947 2.09094 11.5163 2.09094 11.7692 2.34385L13.2866 3.86131C13.5676 4.14233 13.5676 4.56384 13.3147 4.81675Z" fill="#777777" />
      </svg>
    </button>
    </dt>
    <dd class="mepr-profile-details__content"><?php echo esc_html( $mepr_current_user->user_email ); ?></dd>
    <?php if($mepr_options->show_address_fields){ ?>
    <dt class="">
    <?php echo esc_html_x( 'Billing Address', 'ui', 'memberpress' ); ?>
    <button data-name="billing_address" class="mepr-profile-details__button btn btn-link">
      <svg class="mepr-profile-details__icon" width="15" height="16" viewBox="0 0 15 16" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M14.1578 2.99018L12.6403 1.47272C11.9097 0.74209 10.7013 0.74209 9.97069 1.47272L1.03453 10.3527L0.360107 14.5397C0.275804 14.9894 0.66922 15.3828 1.11884 15.2985L5.30591 14.624L14.1859 5.68789C14.9165 4.95726 14.9165 3.74891 14.1578 2.99018ZM5.98033 9.67825C6.09274 9.79065 6.26134 9.84685 6.42995 9.84685C6.57046 9.84685 6.73906 9.79065 6.85147 9.67825L10.1955 6.33421L11.0104 7.14915L6.26134 11.9263V10.7461H4.91249V9.39724H3.73224L8.50943 4.64815L9.32437 5.46308L5.98033 8.80711C5.72742 9.06002 5.72742 9.42534 5.98033 9.67825ZM2.6644 13.8091L1.84947 12.9942L2.21478 10.9428L2.7206 10.4089H3.90085V11.7577H5.2497V12.938L4.71578 13.4438L2.6644 13.8091ZM13.3147 4.81675L11.9378 6.19371L9.46487 3.72081L10.8418 2.34385C11.0947 2.09094 11.5163 2.09094 11.7692 2.34385L13.2866 3.86131C13.5676 4.14233 13.5676 4.56384 13.3147 4.81675Z" fill="#777777" />
      </svg>
    </button>
    </dt>
    <dd class="mepr-profile-details__content"><?php echo wp_kses_post( implode( '<br>', $address_values ) ); ?></dd>
    <?php } ?>
    <?php
    echo $custom_fields_values;
    MeprHooks::do_action( 'mepr-account-home-fields', $mepr_current_user );
    ?>
  </dl>

  <div class="mepr-profile-wrapper__footer">
    <a class="mepr-button btn-outline btn btn-outline" href="<?php echo esc_url_raw( MeprHooks::apply_filters( 'mepr-account-nav-payments-link', $account_url . $delim . 'action=payments' ) ); ?>"><?php echo esc_html_x( 'View Payments', 'ui', 'memberpress' ); ?></a>
    <a class="mepr-button btn-outline btn btn-outline" href="<?php echo esc_url_raw( MeprHooks::apply_filters( 'mepr-account-nav-subscriptions-link', $account_url . $delim . 'action=subscriptions' ) ); ?>"><?php echo esc_html_x( 'View Subscriptions', 'ui', 'memberpress' ); ?></a>
  </div>
  </div>

  <?php if ( $show_welcome_image && $welcome_image ) : ?>
  <div id="mepr-profile-image">
    <img src="<?php echo esc_url_raw( $welcome_image ); ?>" />
  </div>
  <?php endif; ?>
</div>

<!-- Modal -->
<div class="mepr_modal" aria-labelledby="mepr-login-modal" id="mepr-account-modal" role="dialog" aria-modal="true">
  <div class="mepr_modal__overlay"></div>
  <div class="mepr_modal__content_wrapper">
  <div class="mepr_modal__content">
    <div class="mepr_modal__box">
    <button type="button" class="mepr_modal__close">&#x2715;</button>
    <div>

      <form class="mepr_modal_form mepr-account-form mepr-form" action="" enctype="multipart/form-data" novalidate>

        <div class="mp_wrapper">
          <div class="mepr_pro_error hidden" id="mepr_jump">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
              <line x1="12" y1="9" x2="12" y2="13" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
            <ul>
            <!-- JS will populate this -->
            </ul>
          </div>
        </div>

        <div class="mepr_modal__content-placeholder">
          <!-- JS will populate this -->
        </div>



    <?php
    if ( ( ! MeprUtils::is_user_logged_in() ||
        ( MeprUtils::is_user_logged_in() && $mepr_options->show_fields_logged_in_purchases ) ) &&
       $mepr_options->show_fname_lname ) :
      ?>

    <div class="mp-form-row mepr_name">

    <label for="user_first_name<?php echo $unique_suffix; ?>">
      <?php
      _ex( 'Name:', 'ui', 'memberpress' );
      echo ( $mepr_options->require_fname_lname ) ? '*' : '';
      ?>
    </label>
    <div class="mp-form-row-group">
      <input type="text" name="user_first_name" id="user_first_name<?php echo $unique_suffix; ?>"
      class="mepr-form-input" value="<?php echo esc_attr( $first_name_value ); ?>" placeholder="<?php _ex( 'First Name', 'ui', 'memberpress' ); echo ( $mepr_options->require_fname_lname ) ? '*' : ''; ?>
      " <?php echo ( $mepr_options->require_fname_lname ) ? 'required' : ''; ?> />
      <input type="text" name="user_last_name" id="user_last_name<?php echo $unique_suffix; ?>"
      class="mepr-form-input" value="<?php echo esc_attr( $last_name_value ); ?>" placeholder="<?php _ex( 'Last Name', 'ui', 'memberpress' ); echo ( $mepr_options->require_fname_lname ) ? '*' : ''; ?>
      " <?php echo ( $mepr_options->require_fname_lname ) ? 'required' : ''; ?> />
    </div>

    <!-- <div class="mp-form-row mepr_first_name<?php // echo ($mepr_options->require_fname_lname) ? ' mepr-field-required' : ''; ?>"> -->
    <!-- <div class="mp-form-label"> -->
    <!-- <label for="user_first_name<?php // echo $unique_suffix; ?>">
      <?php
      _ex( 'First Name:', 'ui', 'memberpress' );
      echo ( $mepr_options->require_fname_lname ) ? '*' : '';
      ?>
    </label> -->
    <span class="cc-error"><?php _ex( 'First Name Required', 'ui', 'memberpress' ); ?></span>
    <!-- </div> -->

    <!-- </div> -->
    <!-- <div class="mp-form-row mepr_last_name<?php echo ( $mepr_options->require_fname_lname ) ? ' mepr-field-required' : ''; ?>"> -->
    <!-- <div class="mp-form-label"> -->
    <!-- <label for="user_last_name<?php echo $unique_suffix; ?>">
                          <?php
                          _ex( 'Last Name:', 'ui', 'memberpress' );
                          echo ( $mepr_options->require_fname_lname ) ? '*' : '';
                          ?>
    </label> -->
    <span class="cc-error"><?php _ex( 'Last Name Required', 'ui', 'memberpress' ); ?></span>
    <!-- </div> -->

    <!-- </div> -->
    </div>
  <?php endif ?>


      <div class="mp-form-row mepr_email mepr-field-required">
        <div class="mp-form-label">
        <label for="user_email"><?php echo esc_html_x( 'Email:*', 'ui', 'memberpress' ); ?></label>
        <span class="cc-error"><?php echo esc_html_x( 'Invalid Email', 'ui', 'memberpress' ); ?></span>
        </div>
        <input type="email" id="user_email" name="user_email" class="mepr-form-input" value="<?php echo esc_attr( $mepr_current_user->user_email ); ?>" required />
      </div>

      <div id="mp-address-group-label" class="mp-form-label">
        <label for="billing_address"><?php echo esc_html_x( 'Change your billing address:*', 'ui', 'memberpress' ); ?></label>
      </div>

      <div class="mp-address-group">
        <div class="mp-form-row mepr_custom_field mepr_mepr-address-label">
        <div class="mp-form-label">
          <label for="billing_address"><?php echo esc_html_x( 'Change your billing address:*', 'ui', 'memberpress' ); ?></label>
        </div>
        </div>

        <?php
        if($mepr_options->show_address_fields){
          MeprUsersHelper::render_address_fields();
        }
        ?>
      </div>
      <?php
        MeprUsersHelper::render_custom_fields( null, 'account', '', false );
      ?>
      <input class="btn btn-primary" type="submit" value="<?php echo esc_html_x( 'Save Changes', 'ui', 'memberpress' ); ?>">
      </form>
    </div>
    </div>
  </div>
  </div>
</div>
