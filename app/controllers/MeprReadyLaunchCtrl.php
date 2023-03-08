<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );
}

class MeprReadyLaunchCtrl extends MeprBaseCtrl {

  public function load_hooks() {
    add_action( 'mepr_display_options_tabs', 'MeprReadyLaunchCtrl::display_option_tab', 100 );
    add_action( 'mepr_display_options', 'MeprReadyLaunchCtrl::display_option_fields' );
    add_action( 'admin_enqueue_scripts', 'MeprReadyLaunchCtrl::enqueue_admin_scripts', 20 );
    add_action( 'wp_enqueue_scripts', 'MeprReadyLaunchCtrl::enqueue_scripts', 999999 );
    add_action( 'wp_head', 'MeprReadyLaunchCtrl::theme_style' );
    add_action( 'admin_head', 'MeprReadyLaunchCtrl::theme_style' );
    add_action( 'mepr_account_nav_content', 'MeprReadyLaunchCtrl::account_home_content', 10, 2 );
    add_action( 'mepr_before_account_render', 'MeprReadyLaunchCtrl::before_account_render', 10 );
    add_action( 'mepr_after_account_render', 'MeprReadyLaunchCtrl::after_account_render', 10, 2 );

    add_filter( 'mepr-validate-options', 'MeprReadyLaunchCtrl::validate_settings_fields' );
    add_filter( 'template_include', array( $this, 'override_page_templates' ), 999999 ); // High priority so we have the last say here
    add_filter( 'the_content', array( $this, 'thankyou_page_content' ), 99 );
    add_filter( 'mepr_render_address_fields', array( $this, 'placeholders_to_address_fields' ) ); // High priority so we have the last say here
    add_filter( 'mepr_view_paths_get_string', array( $this, 'add_view_paths' ), 10, 3 );
    add_filter( 'show_admin_bar', array( $this, 'remove_admin_bar' ) );
    add_filter( 'mepr-membership-cant-purchase-string', array( $this, 'cant_purchase_message' ) );
    add_filter( 'mepr-validate-account-ajax', array( $this, 'validate_account_fields' ), 10, 3 );

    add_action( 'wp_ajax_prepare_editable_field', 'MeprReadyLaunchCtrl::account_profile_editable_fields' );
    add_action( 'wp_ajax_load_more_subscriptions', array( $this, 'load_more_subscriptions' ) );
    add_action( 'wp_ajax_load_more_payments', array( $this, 'load_more_payments' ) );


    // Shortcodes!
    MeprHooks::add_shortcode( 'mepr-pro-login-form', array( $this, 'login_form_shortcode' ) );
    MeprHooks::add_shortcode( 'mepr-pro-pricing-table', array( $this, 'pricing_table_shortcode' ) );
    MeprHooks::add_shortcode( 'mepr-pro-account-tabs', array( $this, 'account_shortcode' ) );
    MeprHooks::add_shortcode( 'mepr-pro-checkout', array( $this, 'checkout_shortcode' ) );
  }

  /**
   * Renders Pro login form.
   *
   * @param array $atts Shortcode attributes.
   */
  public function login_form_shortcode( $atts = array() ) {
    // Show welcome image
    if ( isset( $atts['show_welcome_image'] ) ) {
      $show_welcome_image = filter_var( $atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN );
    }

    // Get welcome image
    if ( isset( $atts['welcome_image'] ) && ! empty( $atts['welcome_image'] ) ) {
      $welcome_image = $atts['welcome_image'];
    }

    add_filter( 'mepr_pro_templates_has_login_block', '__return_true' );
    $content = do_shortcode( '[mepr-login-form welcome_image="' . $welcome_image . '" show_welcome_image="' . $show_welcome_image . '" admin_view="' . $atts['admin_view'] . '"]' );

    return $content;
  }

  /**
   * Render pricing table shortcode
   *
   * @param array $atts shortcode args.
   * @return void
   */
  public function pricing_table_shortcode( $atts = array() ) {

    wp_enqueue_script( 'mepr-pro-pricing', MEPR_JS_URL . '/pro-templates/pricing.js', array( 'jquery' ), MEPR_VERSION, true );


    if ( ! isset( $atts['group_id'] ) || $atts['group_id'] <= 0 ) {
      return esc_html__( 'Please select group', 'memberpress' );
    }

    $group = new MeprGroup( $atts['group_id'] );

    if ( ! $group->ID ) {
      return esc_html__( 'No group found', 'memberpress' );
    }

    add_filter( 'mepr_pro_templates_has_pricing_block', '__return_true' );
    $content = do_shortcode( '[mepr-group-price-boxes group_id="' . $group->ID . '" show_title="' . $atts['show_title'] . '" button_highlight_color="' . $atts['button_highlight_color'] . '"] ' );
    return $content;
  }

  /**
   * Render pricing table shortcode
   *
   * @param array $atts shortcode args.
   * @return void
   */
  public function account_shortcode( $atts = array() ) {
    wp_enqueue_script( 'alpinejs', 'https://unpkg.com/alpinejs@3.9.3/dist/cdn.min.js', array(), MEPR_VERSION, true );
    wp_enqueue_script( 'mepr-accountjs', MEPR_JS_URL . '/pro-templates/account.js', array( 'jquery' ), MEPR_VERSION, true );
    wp_localize_script(
      'mepr-accountjs',
      'MeprAccount',
      array(
        'ajax_url'    => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'mepr_account_update' ),
        'current_url' => MeprUtils::get_current_url_without_params(),
      )
    );

    // Show welcome image
    if ( isset( $atts['show_welcome_image'] ) ) {
      $show_welcome_image = filter_var( $atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN );
    }

    // Get welcome image
    if ( isset( $atts['welcome_image'] ) && ! empty( $atts['welcome_image'] ) ) {
      $welcome_image = $atts['welcome_image'];
    }

    add_filter( 'mepr_pro_templates_has_account_block', '__return_true' );
    $content = do_shortcode( '[mepr-account-form  welcome_image="' . $welcome_image . '" show_welcome_image="' . $show_welcome_image . '"]' );

    if ( MeprUtils::is_user_logged_in() ) {
      $content = "<div class='mp_wrapper alignwide wp-block wp-shortcode'>" . $content . '</div>';
    }

    return $content;
  }

  /**
   * Checkout Shortcode
   *
   * @param array $atts array of attributes.
   * @return string
   */
  public function checkout_shortcode( $atts = array() ) {
    wp_enqueue_script( 'mepr-signupjs', MEPR_JS_URL . '/pro-templates/signup.js', array( 'jquery' ), MEPR_VERSION, true );

    wp_localize_script(
      'mepr-signupjs',
      'MeprProTemplateSignup',
      array(
        'spc_enabled' => true,
      )
    );

    if ( ! isset( $atts['membership_id'] ) || $atts['membership_id'] <= 0 ) {
      return esc_html__( 'Please select membership', 'memberpress' );
    }

    $prd = new MeprProduct( $atts['membership_id'] );

    if ( ! $prd->ID ) {
      return esc_html__( 'No membership found', 'memberpress' );
    }

    add_filter( 'mepr_pro_templates_has_checkout_block', '__return_true' );
    $content = do_shortcode( '[mepr-membership-registration-form id="' . $prd->ID . '"]' );

    return $content;
  }

  /**
   * Override default template with the courses page template
   *
   * @param string $template current template
   * @return string $template modified template
   */
  public function override_page_templates( $template ) {
    global $post;
    $mepr_options    = MeprOptions::fetch();
    $logout_url      = MeprUtils::logout_url();
    $account_url     = $mepr_options->account_page_url();
    $logo            = esc_url( wp_get_attachment_url( $mepr_options->design_logo_img ) );
    $user            = MeprUtils::get_currentuserinfo();
    $wrapper_classes = '';

    if ( self::template_enabled( 'pricing' ) ) {
      $user              = MeprUtils::get_currentuserinfo();
      $has_welcome_image = $mepr_options->design_login_welcome_img;
      $group_ctrl        = MeprCtrlFactory::fetch( 'groups' );

      $template = \MeprView::file( '/pro-templates/layout/app' );
      include $template;
      exit;
    }

    if ( self::template_enabled( 'login' ) ) {
      if ( $post->ID == $mepr_options->login_page_id ) {
        $template = \MeprView::file( '/pro-templates/layout/guest' );
        include $template;
        exit;
      }
    }

    if ( self::template_enabled( 'account' ) ) {
      $is_account_page = true;
      $template        = MeprView::file( '/pro-templates/layout/app' );
      include $template;
      exit;
    }

    // Checkout Page Template
    if ( self::template_enabled( 'checkout' ) ) {
      $template = MeprView::file( '/pro-templates/layout/app' );
      include $template;
      exit;
    }

    if ( self::template_enabled( 'thankyou' ) ) {
      $template = MeprView::file( '/pro-templates/layout/app' );
      include $template;
      exit;
    }

    return $template;
  }

  /**
   * Gets the page content for thankyou page
   *
   * @param string $content
   * @return string
   */
  public function thankyou_page_content( $content ) {
    if ( self::template_enabled( 'thankyou' ) ) {
      $mepr_options      = MeprOptions::fetch();
      $hide_invoice      = $mepr_options->design_thankyou_hide_invoice;
      $invoice_message   = $mepr_options->design_thankyou_invoice_message;
      $has_welcome_image = $mepr_options->design_show_thankyou_welcome_image;
      $welcome_image     = esc_url( wp_get_attachment_url( $mepr_options->design_thankyou_welcome_img ) );

      $txn = isset( $_GET['trans_num'] ) ? MeprTransaction::get_one_by_trans_num( $_GET['trans_num'] ) : null;
      $txn = null === $txn && isset( $_GET['transaction_id'] ) ? MeprTransaction::get_one( $_GET['transaction_id'] ) : $txn;

      if ( $txn->txn_type == MeprTransaction::$subscription_confirmation_str ) {
        $sub = new MeprSubscription( $txn->subscription_id );
        $txn = $sub->latest_txn();
      }

      $amount  = strtok( MeprAppHelper::format_price_string( $txn, $txn->amount ), ' ' );
      $content = MeprView::get_string( '/pro-templates/thankyou', get_defined_vars() );
    }

    return $content;
  }

  /**
   * Add more data to the home tab content
   *
   * @param mixed $action Tab action.
   * @param array $atts Optional arguments.
   * @return void
   */
  public static function account_home_content( $action, $atts = array() ) {
    if ( ( self::template_enabled( 'account' ) && ( 'home' == $action || ! $action ) ) || has_block('memberpress/pro-account-tabs') ) {
      if( is_array($atts) ){
        extract( $atts, EXTR_SKIP );
      }
      $mepr_options = MeprOptions::fetch();
      $account_url  = $mepr_options->account_page_url();
      $delim        = MeprAppCtrl::get_param_delimiter_char( $account_url );

      $mepr_current_user = MeprUtils::get_currentuserinfo();

      $welcome_message = do_shortcode ( wp_kses_post( wpautop( $mepr_options->custom_message ) ) );

      $address_fields = MeprUsersHelper::get_address_fields( $mepr_current_user );
      $address_values = array();
      foreach ( $address_fields as $address_field ) {
        $value            = $mepr_current_user ? get_user_meta( $mepr_current_user->ID, $address_field->field_key, true ) : '';
        $address_values[] = $value;
      }

      static $unique_suffix = 0;
      $unique_suffix++;

      $first_name_value = '';
      if ( isset( $user_first_name ) ) {
        $first_name_value = esc_attr( stripslashes( $user_first_name ) );
      } elseif ( MeprUtils::is_user_logged_in() ) {
        $first_name_value = (string) $mepr_current_user->first_name;
      }

      $last_name_value = '';
      if ( isset( $user_last_name ) ) {
        $last_name_value = esc_attr( stripslashes( $user_last_name ) );
      } elseif ( MeprUtils::is_user_logged_in() ) {
        $last_name_value = (string) $mepr_current_user->last_name;
      }

      $custom_fields = MeprUsersHelper::get_custom_fields();
      ob_start();
      foreach ( $custom_fields as $custom_field ) {
        if ( isset( $custom_field->show_in_account ) && ! $custom_field->show_in_account ) {
          continue; }
        MeprUsersHelper::render_pro_templates_custom_field_values( $custom_field, $mepr_current_user );
      }
      $custom_fields_values = ob_get_clean();

      $has_welcome_image = $welcome_image = null;

      // Has welcome image? Priority given to shortcode.
      if ( isset( $atts['show_welcome_image'] ) ) {
        $show_welcome_image = filter_var( $atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN );
      } elseif ( isset( $mepr_options->design_account_welcome_img ) ) {
        $show_welcome_image = $mepr_options->design_account_welcome_img;
      }

      // Get welcome image? Priority given to shortcode.
      if ( isset( $atts['welcome_image'] ) && $atts['welcome_image'] > 0 ) {
        $welcome_image = wp_get_attachment_url( $atts['welcome_image'] );
      } elseif ( isset( $mepr_options->design_account_welcome_img ) ) {
        $welcome_image = wp_get_attachment_url( $mepr_options->design_account_welcome_img );
      }

      MeprView::render( '/account/home', get_defined_vars() );
    }

  }

  /**
   * Add a div wrapper to account page
   *
   * @return void
   */
  public static function before_account_render() {
    if( self::template_enabled( 'account' ) || has_block( 'memberpress/pro-account-tabs' ) ) {
      echo "<div class='mepr-account-container'>";
    }
  }

  /**
   * Close the div wrapper added by before_account_render
   *
   * @return void
   */
  public static function after_account_render() {
    if( self::template_enabled( 'account' ) || has_block( 'memberpress/pro-account-tabs' ) ) {
      echo "</div></div>";
    }
  }

  /**
   * Enqueues scripts for admin view
   *
   * @param string $hook current page hook.
   * @return void
   */
  public static function enqueue_admin_scripts( $hook ) {
    if ( strstr( $hook, 'memberpress-options' ) !== false ) {
      wp_enqueue_style( 'mp-pro-templates', MEPR_CSS_URL . '/admin-pro-templates.css', array(), MEPR_VERSION );

      // Let's localize data for our drag and drop settings.
      $plupload_args = array(
        'file_data_name'   => 'async-upload',
        'url'              => admin_url( 'admin-ajax.php' ),
        'filters'          => array(
          'max_file_size' => wp_max_upload_size() . 'b',
          'mime_types'    => array( array( 'extensions' => 'jpg,gif,png' ) ),
        ),
        'multi_selection'  => false, // Limit selection to just one.

        // additional post data to send to our ajax hook.
        'multipart_params' => array(
          '_wpnonce' => wp_create_nonce( 'media-form' ),
          'action'   => 'upload-attachment',            // the ajax action name.
        ),
      );
      wp_enqueue_style( 'wp-color-picker' );
      wp_enqueue_script( 'mp-pro-templates', MEPR_JS_URL . '/admin-pro-templates.js', array( 'mepr-uploader', 'plupload-all', 'wp-color-picker' ), MEPR_VERSION );
      wp_localize_script( 'mp-pro-templates', 'MeproTemplates', $plupload_args );
    }
  }

  /**
   * Enqueues scripts for frontend view
   *
   * @return void
   */
  public static function enqueue_scripts() {
    global $post;

    if ( MeprUser::is_account_page( $post ) ) {
      wp_enqueue_script( 'mepr-popper', 'https://unpkg.com/@popperjs/core@2', array(), MEPR_VERSION, true );
    }

    $handles = array( 'dashicons', 'jquery-ui-timepicker-addon' );

    // Login Scripts
    if ( self::template_enabled( 'login' ) ) {
      static::remove_styles( $handles );
      static::add_template_scripts( 'login' );
    }

    // Account Scripts
    if ( self::template_enabled( 'account' ) ) {
      static::remove_styles( $handles );
      static::add_template_scripts( 'account' );
    }

    // Pricing Scripts
    if ( self::template_enabled( 'pricing' ) ) {
      if (
        isset( $post ) &&
        is_a( $post, 'WP_Post' ) &&
        $post->post_type == MeprGroup::$cpt
      ) {
        static::remove_styles( $handles );
        static::add_template_scripts( 'pricing' );
      }
    }

    // Checkout Scripts
    if ( self::template_enabled( 'checkout' ) || self::template_enabled( 'thankyou' ) ) {
      static::remove_styles( $handles );
      static::add_template_scripts( 'checkout' );
    }
  }

  /**
   * Add Design tab toadmin memberpress page
   *
   * @return void
   */
  public static function display_option_tab() {                      ?>
    <a class="nav-tab" id="design" href="#"><?php _e( 'ReadyLaunch™', 'memberpress' ); ?></a>
    <?php
  }

  /**
   * Displays design option fields
   *
   * FILTER_VALIDATE_BOOLEAN is used to return boolean, it works as described below
   *
   * filter_var(true, FILTER_VALIDATE_BOOLEAN); // true
   * filter_var('true', FILTER_VALIDATE_BOOLEAN); // true
   * filter_var(1, FILTER_VALIDATE_BOOLEAN); // true
   * filter_var('1', FILTER_VALIDATE_BOOLEAN); // true
   * filter_var('on', FILTER_VALIDATE_BOOLEAN); // true
   * filter_var('yes', FILTER_VALIDATE_BOOLEAN); // true
   */
  public static function display_option_fields() {
    $mepr_options    = MeprOptions::fetch();
    $courses_options = \get_option( 'mpcs-options' );

    $groups                = MeprCptModel::all( 'MeprGroup' );
    $pricing_columns_limit = false;

    foreach ( $groups as $group ) {
      $products_count = count( $group->products() );
      if ( $products_count > 5 ) {
        $pricing_columns_limit = true;
        break;
      }
    }

    $data = array(
      'global'   => array(
        'logoId' => isset( $mepr_options->design_logo_img ) ? absint( $mepr_options->design_logo_img ) : '',
      ),
      'pricing'  => array(
        'enableTemplate' => isset( $mepr_options->design_enable_pricing_template ) ? filter_var( $mepr_options->design_enable_pricing_template, FILTER_VALIDATE_BOOLEAN ) : '',
      ),
      'checkout' => array(
        'enableTemplate' => isset( $mepr_options->design_enable_checkout_template ) ? filter_var( $mepr_options->design_enable_checkout_template, FILTER_VALIDATE_BOOLEAN ) : '',
      ),
      'login'    => array(
        'enableTemplate'   => isset( $mepr_options->design_enable_login_template ) ? filter_var( $mepr_options->design_enable_login_template, FILTER_VALIDATE_BOOLEAN ) : '',
        'showWelcomeImage' => isset( $mepr_options->design_show_login_welcome_image ) ? filter_var( $mepr_options->design_show_login_welcome_image, FILTER_VALIDATE_BOOLEAN ) : '',
        'welcomeImageId'   => isset( $mepr_options->design_login_welcome_img ) ? absint( $mepr_options->design_login_welcome_img ) : '',
      ),
      'thankyou' => array(
        'enableTemplate'   => isset( $mepr_options->design_enable_thankyou_template ) ? filter_var( $mepr_options->design_enable_thankyou_template, FILTER_VALIDATE_BOOLEAN ) : '',
        'showWelcomeImage' => isset( $mepr_options->design_show_thankyou_welcome_image ) ? filter_var( $mepr_options->design_show_thankyou_welcome_image, FILTER_VALIDATE_BOOLEAN ) : '',
        'hideInvoice'      => isset( $mepr_options->design_thankyou_hide_invoice ) ? filter_var( $mepr_options->design_thankyou_hide_invoice, FILTER_VALIDATE_BOOLEAN ) : '',
        'welcomeImageId'   => isset( $mepr_options->design_thankyou_welcome_img ) ? absint( $mepr_options->design_thankyou_welcome_img ) : '',
      ),
      'account'  => array(
        'enableTemplate'   => isset( $mepr_options->design_enable_account_template ) ? filter_var( $mepr_options->design_enable_account_template, FILTER_VALIDATE_BOOLEAN ) : '',
        'showWelcomeImage' => isset( $mepr_options->design_show_account_welcome_image ) ? filter_var( $mepr_options->design_show_account_welcome_image, FILTER_VALIDATE_BOOLEAN ) : '',
        'welcomeImageId'   => isset( $mepr_options->design_account_welcome_img ) ? absint( $mepr_options->design_account_welcome_img ) : '',
      ),
      'courses'  => array(
        'enableTemplate'        => isset( $courses_options['classroom-mode'] ) ? filter_var( $courses_options['classroom-mode'], FILTER_VALIDATE_BOOLEAN ) : '',
        'showProtectedCourses' => isset( $courses_options['show-protected-courses'] ) ? filter_var( $courses_options['show-protected-courses'], FILTER_VALIDATE_BOOLEAN ) : '',
        'removeInstructorLink' => isset( $courses_options['remove-instructor-link'] ) ? filter_var( $courses_options['remove-instructor-link'], FILTER_VALIDATE_BOOLEAN ) : '',
        'logoId'               => isset( $courses_options['classroom-logo'] ) ? absint( $courses_options['classroom-logo'] ) : '',
      ),
    );

    MeprView::render( '/admin/pro-templates/options', get_defined_vars() );
  }

  /**
   * Validates all Design tab admin settings
   *
   * @param array $errors The errors array.
   * @return array
   */
  public static function validate_settings_fields( $errors ) {
    $params       = $_POST;
    $mepr_options = MeprOptions::fetch();

    // When LoginTemplate is enabled and ShowWelcomeImage is checked but dude forgot to upload the image.
    if (
      isset( $params[ $mepr_options->design_enable_login_template_str ] ) &&
      isset( $params[ $mepr_options->design_show_login_welcome_image_str ] ) &&
      absint( $params[ $mepr_options->design_login_welcome_img_str ] ) == 0
    ) {
      $errors[] = esc_html__( 'Welcome Image should be uploaded if Show Welcome Image button is checked', 'memberpress' );
    }

    return $errors;
  }

  /**
   * Dequeues and deregisters styles unrelated to pro mode templates.
   *
   * @param array $allowed_handles CSS Handles that won't be deregistered and dequeued when using Pro Mode.
   * @return void
   */
  public static function remove_styles( $allowed_handles = array() ) {
    global $wp_styles;
    $allowed_handles         = apply_filters( 'mepr_design_style_handles', $allowed_handles );
    $allowed_handle_prefixes = apply_filters( 'mepr_design_style_handle_prefixes', array( 'mepr-', 'mp-', 'mpca-', 'mpcs-', 'mpgft-', 'ca-course' ) );

    // Remove styles.
    foreach ( $wp_styles->queue as $style ) {
      $handle = $wp_styles->registered[ $style ]->handle;
      if ( ! in_array( $handle, $allowed_handles ) ) {

        foreach ( $allowed_handle_prefixes as $prefix ) {
          if ( strpos( $handle, $prefix ) === 0 ) {
            continue 2;
          }
        }

        wp_deregister_style( $handle );
        wp_dequeue_style( $handle );
      }
    }
  }

  /**
   * Add scripts to full page template
   *
   * @param string $page the template page.
   * @return void
   */
  public static function add_template_scripts( $page = '' ) {
    global $post;

    wp_enqueue_style( 'mp-pro-theme', MEPR_CSS_URL . '/pro-templates/theme.css', null, MEPR_VERSION );

    if ( 'login' === $page ) {
      wp_enqueue_style( 'mp-pro-login', MEPR_CSS_URL . '/pro-templates/login.css', null, MEPR_VERSION );
    }

    if ( 'account' === $page ) {
      wp_enqueue_style( 'mp-pro-login', MEPR_CSS_URL . '/pro-templates/login.css', null, MEPR_VERSION );
      wp_register_style( 'mp-pro-fonts', MEPR_CSS_URL . '/pro-templates/fonts.css', null, MEPR_VERSION );
      wp_enqueue_style( 'mp-pro-account', MEPR_CSS_URL . '/pro-templates/account.css', array( 'mp-pro-fonts' ), MEPR_VERSION );
      wp_enqueue_script( 'alpinejs', 'https://unpkg.com/alpinejs@3.9.3/dist/cdn.min.js', array(), MEPR_VERSION, true );
      wp_enqueue_script( 'mepr-accountjs', MEPR_JS_URL . '/pro-templates/account.js', array( 'jquery' ), MEPR_VERSION, true );

      wp_localize_script(
        'mepr-accountjs',
        'MeprAccount',
        array(
          'ajax_url'    => admin_url( 'admin-ajax.php' ),
          'nonce'       => wp_create_nonce( 'mepr_account_update' ),
          'account_url' => MeprUtils::get_current_url_without_params(),
        )
      );
    }

    if ( 'pricing' == $page ) {
      wp_enqueue_style( 'mp-pro-pricing', MEPR_CSS_URL . '/pro-templates/pricing.css', null, MEPR_VERSION );
      wp_enqueue_script( 'mepr-pro-pricing', MEPR_JS_URL . '/pro-templates/pricing.js', array( 'jquery' ), MEPR_VERSION, true );
      wp_enqueue_script( 'alpinejs', 'https://unpkg.com/alpinejs@3.9.3/dist/cdn.min.js', array(), MEPR_VERSION, true );
    }

    if ( 'checkout' == $page ) {
      wp_enqueue_style( 'mp-pro-checkout', MEPR_CSS_URL . '/pro-templates/checkout.css', null, MEPR_VERSION );
      wp_enqueue_script( 'mepr-signupjs', MEPR_JS_URL . '/pro-templates/signup.js', array( 'jquery' ), MEPR_VERSION, true );
      wp_enqueue_script( 'alpinejs', 'https://unpkg.com/alpinejs@3.9.3/dist/cdn.min.js', array(), MEPR_VERSION, true );

      wp_localize_script(
        'mepr-signupjs',
        'MeprProTemplateSignup',
        array(
          'spc_enabled' => true,
        )
      );
    }

    if ( 'thankyou' == $page ) {
      wp_enqueue_style( 'mp-pro-checkout', MEPR_CSS_URL . '/pro-templates/checkout.css', null, MEPR_VERSION );
    }
  }

  /**
   * Add pro templates path to memberpress view path
   *
   * @param  mixed $paths MemberPress paths
   *
   * @return mixed
   */
  function add_view_paths( $paths, $slug, $vars ) {
    $paths = array_merge( $paths, MeprView::paths() );
    $options = MeprOptions::fetch();

    $design_checkout_enabled = isset( $options->design_enable_checkout_template ) ? filter_var( $options->design_enable_checkout_template, FILTER_VALIDATE_BOOLEAN ) : null;

    if (
      self::template_enabled( 'checkout' ) ||
      self::template_enabled( 'thankyou' ) ||
      has_block( 'memberpress/checkout' ) ||
      apply_filters( 'mepr_pro_templates_has_checkout_block', false ) ||
      ( $design_checkout_enabled && isset( $_POST['action'] ) && 'mepr_update_spc_invoice_table' == $_POST['action'] )
    ) {
      $paths = $this->add_view_path_for_slug( $paths, $slug, array( '/checkout/invoice', '/checkout/form', '/checkout/spc_form', '/shared/errors' ) );
      // $paths = $this->add_view_path_for_slug( $paths, $slug, array() );
    }

    if (
      self::template_enabled( 'login' ) ||
      apply_filters( 'mepr_pro_templates_has_login_block', false ) ||
      has_block( 'memberpress/pro-login-form' )
    ) {
      $paths = $this->add_view_path_for_slug( $paths, $slug, array() );
    }

    if (
      self::template_enabled( 'pricing' ) ||
      apply_filters( 'mepr_pro_templates_has_pricing_block', false ) ||
      has_block( 'memberpress/pro-pricing-table' )
    ) {
      $paths = $this->add_view_path_for_slug( $paths, $slug, array( '/groups/front_groups_page' ) );
    }

    if (
      self::template_enabled( 'account' ) ||
      has_block( 'memberpress/pro-account-tabs' ) ||
      apply_filters( 'mepr_pro_templates_has_account_block', false ) ||
      ( isset( $_POST['action'] ) && in_array( $_POST['action'], array( 'load_more_subscriptions', 'load_more_payments' ) ) )
    ) {
      $paths = $this->add_view_path_for_slug( $paths, $slug, array() );
    }

    if( self::template_enabled( 'courses' ) ){
      $paths = $this->add_view_path_for_slug( $paths, $slug, array( '/account/courses_list' ) );
    }

    return $paths;
  }


  public function add_view_path_for_slug( $paths, $slug, $allowed_slugs = array() ) {
    if ( in_array( $slug, $allowed_slugs ) || empty( $allowed_slugs ) ) {
      array_splice( $paths, 1, 0, MEPR_PATH . '/app/views/pro-templates' );
    }
    return $paths;
  }

  /**
   * Only remove admin bar when on readylaunch pro templates
   *
   * @return bool
   */
  public function remove_admin_bar( $show ){
    if (
      self::template_enabled( 'pricing' ) ||
      self::template_enabled( 'login' ) ||
      self::template_enabled( 'account' ) ||
      self::template_enabled( 'checkout' ) ||
      self::template_enabled( 'thankyou' )
    ) { // full page templates
      $show = false;
    }

    return $show;
  }

  /**
  * Change the cant purchase message template
  *
  * @param string $str purchase message
  * @return string
  */
  public function cant_purchase_message( $str ){
    $errors[] = $str;
    $str = MeprView::get_string('/shared/errors', get_defined_vars());
    return '<div class="flex-centered">' . $str . '</div>';
  }

  /**
   * Checks if we should override the page template
   *
   * @param string      $template template name.
   * @param MeprOptions $options MeprOptions object.
   * @return boolean
   */
  public static function template_enabled( $template ) {
    global $post;
    global $wp_query;

    $page_name      = $template . '_page_id';
    $attribute_name = 'design_enable_' . $template . '_template';
    $options        = MeprOptions::fetch();
    $courses_options = \get_option( 'mpcs-options' );

    if ( 'pricing' === $template ) {
      return isset( $post ) &&
        is_a( $post, 'WP_Post' ) &&
        $post->post_type == MeprGroup::$cpt &&
        isset( $options->$attribute_name ) &&
        filter_var( $options->$attribute_name, FILTER_VALIDATE_BOOLEAN );
    }

    if ( 'checkout' === $template ) {
      return isset( $options->$attribute_name ) &&
        filter_var( $options->$attribute_name, FILTER_VALIDATE_BOOLEAN ) &&
        ( isset( $post ) && is_a( $post, 'WP_Post' ) && $post->post_type == MeprProduct::$cpt );
    }

    if ( 'courses' === $template && is_array($courses_options) ) {
      return isset($courses_options['classroom-mode']) &&
        filter_var( $courses_options['classroom-mode'], FILTER_VALIDATE_BOOLEAN ) &&
        MeprUser::is_account_page($post) &&
        isset($_GET['action']) && $_GET['action'] == 'courses';
    }

    return isset( $wp_query ) &&
      isset( $options->$page_name ) &&
      is_page( $options->$page_name ) &&
      isset( $options->$attribute_name ) &&
      filter_var( $options->$attribute_name, FILTER_VALIDATE_BOOLEAN );
  }

  /**
   * AJAX: Get the editable field HTML
   *
   * @return void.
   */
  public function account_profile_editable_fields() {
    $user      = MeprUtils::get_currentuserinfo();
    $field_key = isset( $_POST['field'] ) ? wp_unslash( sanitize_text_field( $_POST['field'] ) ) : '';

    $custom_fields = MeprUsersHelper::get_custom_fields();

    $key   = array_search( $field_key, array_column( $custom_fields, 'field_key' ) );
    $field = $custom_fields[ $key ];
    $value = $user ? get_user_meta( $user->ID, $field->field_key, true ) : '';

    ob_start();
    echo '<div><label class="mepr_modal_form__label">' . esc_html( $field->field_name ) . '</label></div>';
    echo MeprUsersHelper::render_custom_field( $field, $value );
    $content = ob_get_clean();

    wp_send_json_success( $content );
  }

  public function validate_account_fields($errors, $user, $field_key ){
    $mepr_options = MeprOptions::fetch();

    $errors = MeprUsersCtrl::validate_extra_profile_fields( null, true, $user, false, false, $field_key );

    // validate first name and last name
    if(isset($_POST['first_name']) || isset($_POST['last_name'])){
      if($mepr_options->require_fname_lname && (empty($_POST['first_name']) || empty($_POST['last_name']))) {
        $errors[] = __('You must enter both your First and Last name', 'memberpress');
      }
    }

    if(isset($_POST['user_email'])){
      if(empty($_POST['user_email']) || !is_email(stripslashes($_POST['user_email']))) {
        $errors[] = __('You must enter a valid email address', 'memberpress');
      }

      //Old email is not the same as the new, so let's make sure no else has it
      // $user = MeprUtils::get_currentuserinfo(); //Old user info is here since we haven't stored the new stuff yet
      if($user !== false && $user->user_email != stripslashes($_POST['user_email']) && email_exists(stripslashes($_POST['user_email']))) {
        $errors[] = __('This email is already in use by another member', 'memberpress');
      }
    }

    return $errors;
  }

  public function load_more_subscriptions() {
    // Check for nonce security!
    if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( $_POST['nonce'], 'mepr_account_update' ) ) {
      die( 'Busted!' );
    }

    $count     = isset( $_POST['count'] ) ? absint( wp_unslash( $_POST['count'] ) ) : 1;
    $acct_ctrl = MeprCtrlFactory::fetch( 'account' );

    ob_start();

    $acct_ctrl->subscriptions(
      '',
      array(),
      array(
        'mode'  => 'pro-templates',
        'count' => $count,
      )
    );

    $content = ob_get_clean();
    wp_send_json_success( $content );
  }

  public function load_more_payments() {
    // Check for nonce security!
    if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( $_POST['nonce'], 'mepr_account_update' ) ) {
      die( 'Busted!' );
    }

    $count     = isset( $_POST['count'] ) ? absint( wp_unslash( $_POST['count'] ) ) : 1;
    $acct_ctrl = MeprCtrlFactory::fetch( 'account' );

    ob_start();
    $acct_ctrl->payments(
      array(
        'mode'  => 'pro-templates',
        'count' => $count,
      )
    );

    $content = ob_get_clean();
    wp_send_json_success( $content );
  }

  public function placeholders_to_address_fields( $fields ) {
    foreach ( $fields as $key => $field ) {
      $fields[ $key ]->placeholder = $field->field_name;
    }

    return $fields;
  }

  public static function theme_style() {
    $mepr_options = MeprOptions::fetch();

    $primary_color  = ! empty( $mepr_options->design_primary_color ) ? $mepr_options->design_primary_color : '#06429e';
    $text_color     = self::getContrastColor( $primary_color );
    $current_screen = is_admin() ? get_current_screen() : '';

    $is_block_editor = (
      isset( $current_screen ) && ! empty( $current_screen ) &&
      method_exists( $current_screen, 'is_block_editor' ) &&
      $current_screen->is_block_editor()
    );

    if (
      self::template_enabled( 'pricing' ) ||
      self::template_enabled( 'login' ) ||
      self::template_enabled( 'account' ) ||
      self::template_enabled( 'checkout' ) ||
      self::template_enabled( 'thankyou' ) ||
      $is_block_editor
    ) { // full page templates
      $html  = '<style type="text/css">';
      $html .= sprintf( 'body.mepr-guest-layout{background:%s!important}', $primary_color );
      $html .= sprintf( '.app-layout .site-header, .guest-layout .site-header{background:%s!important}', $primary_color );
      $html .= sprintf( '#mepr-account-nav{background:%s!important}', $primary_color );
      $html .= sprintf( '.mepr-price-menu .mepr-price-boxes .mepr-most-popular{background:%s!important}', $primary_color );
      $html .= sprintf( '#mepr-account-nav .mepr-nav-item a{color:rgba(%s)}', self::hexToRgb( $text_color, 0.7 ) );
      $html .= sprintf( '#mepr-account-nav .mepr-nav-item a:hover{color:%s}', $text_color );
      $html .= sprintf( '.app-layout .profile-menu__text, .guest-layout .profile-menu__text, .app-layout .profile-menu__arrow_down, .guest-layout .profile-menu__arrow_down{color:%s}', $text_color );
      $html .= sprintf( '.app-layout .profile-menu__text--small, .guest-layout .profile-menu__text--small{color:rgba(%s)}', self::hexToRgb( $text_color, 0.7 ) );
      $html .= '</style>';

      echo $html;
    }

    if ( is_singular() && has_block( 'memberpress/pro-account-tabs' ) ) {
      $html  = '<style type="text/css">';
      $html .= sprintf( '#mepr-account-nav{background:%s!important}', $primary_color );
      $html .= sprintf( '#mepr-account-nav .mepr-nav-item a{color:rgba(%s)}', self::hexToRgb( $text_color, 0.7 ) );
      $html .= '</style>';

      echo $html;
    }
  }

  public static function getContrastColor( $hexColor ) {
    $hexColor = trim( $hexColor );
    $tmp_hexColor = trim( $hexColor, '#' );
    if( ! ctype_xdigit( $tmp_hexColor ) ) { // Validate HEX code.
      $hexColor = '#FFFFFF'; // Fallback to white color.
    }

    // hexColor RGB
    $R1 = hexdec( substr( $hexColor, 1, 2 ) );
    $G1 = hexdec( substr( $hexColor, 3, 2 ) );
    $B1 = hexdec( substr( $hexColor, 5, 2 ) );

    // Black RGB
    $blackColor   = '#000000';
    $R2BlackColor = hexdec( substr( $blackColor, 1, 2 ) );
    $G2BlackColor = hexdec( substr( $blackColor, 3, 2 ) );
    $B2BlackColor = hexdec( substr( $blackColor, 5, 2 ) );

    // Calc contrast ratio
    $L1 = 0.2126 * pow( $R1 / 255, 2.2 ) +
      0.7152 * pow( $G1 / 255, 2.2 ) +
      0.0722 * pow( $B1 / 255, 2.2 );

    $L2 = 0.2126 * pow( $R2BlackColor / 255, 2.2 ) +
      0.7152 * pow( $G2BlackColor / 255, 2.2 ) +
      0.0722 * pow( $B2BlackColor / 255, 2.2 );

    $contrastRatio = 0;
    if ( $L1 > $L2 ) {
      $contrastRatio = (int) ( ( $L1 + 0.05 ) / ( $L2 + 0.05 ) );
    } else {
      $contrastRatio = (int) ( ( $L2 + 0.05 ) / ( $L1 + 0.05 ) );
    }

    // If contrast is more than 5, return black color
    if ( $contrastRatio > 5 ) {
      return '#000000';
    } else {
      // if not, return white color.
      return '#FFFFFF';
    }
  }

  public static function hexToRgb( $hex, $alpha = false ) {
    $hex      = str_replace( '#', '', $hex );
    $length   = strlen( $hex );
    $rgb['r'] = hexdec( $length == 6 ? substr( $hex, 0, 2 ) : ( $length == 3 ? str_repeat( substr( $hex, 0, 1 ), 2 ) : 0 ) );
    $rgb['g'] = hexdec( $length == 6 ? substr( $hex, 2, 2 ) : ( $length == 3 ? str_repeat( substr( $hex, 1, 1 ), 2 ) : 0 ) );
    $rgb['b'] = hexdec( $length == 6 ? substr( $hex, 4, 2 ) : ( $length == 3 ? str_repeat( substr( $hex, 2, 1 ), 2 ) : 0 ) );
    if ( $alpha ) {
      $rgb['a'] = $alpha;
    }
    return implode( ',', $rgb );
  }
} //End class
