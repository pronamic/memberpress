<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );
}

/**
 * This class handles the registrations and enqueues for MemberPress blocks
 */
class MeprBlocksCtrl extends MeprBaseCtrl {

  /**
   * Register all class actions and filters.
   */
  public function load_hooks() {

    // Only load block stuff when Gutenberg is active (e.g. WordPress 5.0+).
    if ( function_exists( 'register_block_type' ) ) {
      add_action( 'init', array( $this, 'register_block_types_serverside' ) );
      add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_block_scripts' ) );
      add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_scripts' ) );
      add_filter( 'mepr-is-product-page', array( $this, 'signup_block_enqueues' ), 10, 2 );
      add_filter( 'mepr_is_account_page', array( $this, 'account_block_enqueues' ), 10, 2 );
    }
  }

  /**
   * Render the frontend for the blocks on the server ("save" method must return null)
   *
   * @return void
   */
  public function register_block_types_serverside() {
    $mepr_options    = MeprOptions::fetch();

    // Membership signup form block
    register_block_type(
      'memberpress/membership-signup',
      array(
        'attributes'      => array(
          'membership' => array(
            'type' => 'string',
          ),
        ),
        'render_callback' => array( $this, 'render_membership_signup_block' ),
      )
    );

    // Account form block
    register_block_type(
      'memberpress/account-form',
      array(
        'attributes'      => array(),
        'render_callback' => array( $this, 'render_account_block' ),
      )
    );

    // Login form block
    register_block_type(
      'memberpress/login-form',
      array(
        'attributes'      => array(
          'use_redirect' => array(
            'type' => 'boolean',
          ),
        ),
        'render_callback' => array( $this, 'render_login_block' ),
      )
    );

    // Protected content block
    register_block_type(
      'memberpress/protected-content',
      array(
        'attributes'      => array(
          'rule'           => array(
            'type' => 'number',
          ),
          'ifallowed'      => array(
            'type' => 'string',
          ),
          'unauth'         => array(
            'type' => 'string',
          ),
          'unauth_message' => array(
            'type' => 'string',
          ),
        ),
        'render_callback' => array( $this, 'render_protected_content_block' ),
      )
    );

    // Pro Login Form
    register_block_type(
      'memberpress/pro-login-form',
      array(
        'api_version'     => 2,
        'attributes'      => array(
          'show_welcome_image' => array(
            'type' => 'boolean',
            'default' => $mepr_options->design_show_login_welcome_image
          ),
          'welcome_image'      => array(
            'type' => 'string',
            'default' => wp_get_attachment_url( $mepr_options->design_login_welcome_img )
          ),
          'admin_view'            => array(
            'type' => 'boolean',
          )
        ),
        'render_callback' => array( $this, 'render_pro_login_block' ),
        'editor_style'    => 'mp-pro-login',
      )
    );

    // Pricing Columns for Pro Template
    register_block_type(
      'memberpress/pro-pricing-table',
      array(
        'api_version'     => 2,
        'attributes'      => array(
          'show_title'             => array(
            'type' => 'boolean',
          ),
          'button_highlight_color' => array(
            'type'    => 'string',
            'default' => '#EF1010',
          ),
          'group_id'               => array(
            'type'    => 'string',
            'default' => '',
          ),
        ),
        'render_callback' => array( $this, 'render_pro_pricing_block' ),
        'editor_style'    => 'mp-pro-pricing',
      )
    );

    // Accounts Tab
    register_block_type(
      'memberpress/pro-account-tabs',
      array(
        'api_version'     => 2,
        'attributes'      => array(
          'show_welcome_image' => array(
            'type' => 'boolean',
            'default' => $mepr_options->design_show_account_welcome_image
          ),
          'welcome_image'      => array(
            'type'    => 'string',
            'default' => wp_get_attachment_url( $mepr_options->design_account_welcome_img )
          ),
        ),
        'editor_style'    => 'mp-pro-account',
        'render_callback' => array( $this, 'render_pro_account_block' ),
      )
    );

    register_block_type(
      'memberpress/checkout',
      array(
        'api_version'     => 2,
        'attributes'      => array(
          'show_welcome_image' => array(
            'type' => 'boolean',
          ),
          'membership_id'      => array(
            'type'    => 'string',
            'default' => '',
          ),
        ),
        'render_callback' => array( $this, 'render_checkout_block' ),
        'editor_style'    => 'mp-pro-checkout'
      )
    );
  }

  /**
   * Renders a membership's signup form
   *
   * @param array $props    Properties/data from the block
   *
   * @return string
   */
  public function render_membership_signup_block( $props ) {

    $membership_id = isset( $props['membership'] ) ? (int) $props['membership'] : 0;

    if ( $membership_id > 0 ) {
      ob_start();
      echo do_shortcode( "[mepr-membership-registration-form id='{$membership_id}']" );
      return ob_get_clean();
    }

    return _x( 'Uh oh, something went wrong. Not a valid Membership form.', 'ui', 'memberpress' );
  }

  /**
   * Renders the MP account form
   *
   * @return string
   */
  public function render_account_block() {
    ob_start();
    echo do_shortcode( '[mepr-account-form]' );
    return ob_get_clean();
  }

  /**
   * Renders the MP login form
   *
   * @param array $props    Properties/data from the block
   *
   * @return string
   */
  public function render_login_block( $props ) {
    $shortcode = isset( $props['use_redirect'] ) && true === $props['use_redirect'] ? "[mepr-login-form show_logged_in='false' use_redirect='true']" : '[mepr-login-form]';
    ob_start();
    echo do_shortcode( $shortcode );
    return ob_get_clean();
  }

  /**
   * Render the "dynamic" block
   *
   * @param array  $attributes   Properties/data from the block
   * @param string $content      Block content
   *
   * @return string
   */
  public function render_protected_content_block( $attributes, $content ) {

    $attributes['ifallowed'] = ! empty( $attributes['ifallowed'] ) ? $attributes['ifallowed'] : 'show';

    if ( ! isset( $attributes['unauth_message'] ) || '' === $attributes['unauth_message'] ) {
      $attributes['unauth_message'] = __( 'You are unauthorized to view this content.', 'memberpress' );
    }

    $content = MeprRulesCtrl::protect_shortcode_content( $attributes, $content );

    return $content;
  }

  /**
   * Renders the MP login form
   *
   * @param array $props    Properties/data from the block
   *
   * @return string
   */
  public function render_pro_login_block( $atts ) {
    wp_enqueue_style( 'mp-pro-login' );

    $show_welcome_image = filter_var( $atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN );

    $admin_view = isset($atts['admin_view']) ? filter_var( $atts['admin_view'], FILTER_VALIDATE_BOOLEAN ) : false;

    $welcome_image = isset( $atts['welcome_image'] ) ?
    esc_url_raw( $atts['welcome_image'] ) : '';

    $shortcode = "[mepr-pro-login-form
    show_welcome_image='$show_welcome_image'
    welcome_image='$welcome_image'
    admin_view='$admin_view']";

    ob_start();
    echo do_shortcode( $shortcode );
    return ob_get_clean();
  }

  /**
   * Renders the MP login form
   *
   * @param array $props    Properties/data from the block
   *
   * @return string
   */
  public function render_pro_pricing_block( $atts ) {
    wp_enqueue_style( 'mp-pro-pricing' );

    $show_title = isset( $atts['show_title'] ) &&
    filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN ) ?
    true : false;

    $button_highlight_color = isset( $atts['button_highlight_color'] ) ?
    sanitize_text_field( $atts['button_highlight_color'] ) : '';

    $group_id = isset( $atts['group_id'] ) ?
    absint( $atts['group_id'] ) : '';

    $shortcode = "[mepr-pro-pricing-table
    show_title='$show_title'
    button_highlight_color='$button_highlight_color'
    group_id='$group_id']";

    ob_start();
    echo do_shortcode( $shortcode );
    return ob_get_clean();
  }


  /**
   * Renders the MP login form
   *
   * @param array $props    Properties/data from the block
   *
   * @return string
   */
  public function render_pro_account_block( $atts ) {
    wp_enqueue_style( 'mp-pro-account' );

    $show_welcome_image = isset( $atts['show_welcome_image'] ) &&
    filter_var( $atts['show_welcome_image'], FILTER_VALIDATE_BOOLEAN ) ?
    true : false;

    $welcome_image = isset( $atts['welcome_image'] ) ?
    esc_url_raw( $atts['welcome_image'] ) : '';

    $shortcode = "[mepr-pro-account-tabs
    show_welcome_image='$show_welcome_image'
    welcome_image='$welcome_image']";

    ob_start();
    echo do_shortcode( $shortcode );
    return ob_get_clean();
  }

  /**
   * Renders the MP login form
   *
   * @param array $props    Properties/data from the block
   *
   * @return string
   */
  public function render_checkout_block( $atts ) {
    wp_enqueue_style( 'mp-pro-checkout' );

    $membership_id = isset( $atts['membership_id'] ) ?
    absint( $atts['membership_id'] ) : '';

    $shortcode = "[mepr-pro-checkout membership_id='$membership_id']";
    ob_start();
    echo do_shortcode( $shortcode );
    return ob_get_clean();
  }


  /**
   * Enqueue the necessary JS in the editor
   *
   * @return void
   */
  public function enqueue_editor_block_scripts() {
    $asset_file = include MEPR_JS_PATH . '/build/blocks.asset.php';

    $dependencies = array_unique(
      array_merge(
        array(
          'wp-blocks',
          'wp-i18n',
          'wp-editor',
        ), // legacy dependencies
        (array) $asset_file['dependencies']
      )
    );

    wp_enqueue_script(
      'memberpress/blocks',
      MEPR_JS_URL . '/build/blocks.js',
      $dependencies,
      $asset_file['version'],
      true
    );

    $membership_options = array();
    $rule_options       = array();

    // Assemble MP Products into an options array
    foreach ( MeprCptModel::all( 'MeprProduct' ) as $membership ) {
      $membership_options[] = array(
        'label' => $membership->post_title,
        'value' => $membership->ID,
      );
    }

    // Assemble MP Rules into an options array
    foreach ( MeprCptModel::all( 'MeprRule' ) as $rule ) {

      $rule_options[] = array(
        'label'    => $rule->post_title,
        'value'    => $rule->ID,
        'ruleLink' => get_edit_post_link( $rule->ID, null ),
      );
    }

    // Assemble MP Groups into an options array
    $groups = array();
    foreach ( MeprCptModel::all( 'MeprGroup' ) as $group ) {
      $groups[] = array(
        'label' => $group->post_title,
        'value' => $group->ID,
      );
    }

    // Make the data available to the script
    wp_localize_script(
      'memberpress/blocks',
      'memberpressBlocks',
      array(
        'memberships'              => $membership_options,
        'rules'                    => $rule_options,
        'groups'                   => $groups,
        'redirect_url_setting_url' => menu_page_url( 'memberpress-options', false ) . '#mepr-accounts',
      )
    );

  }

  /**
   * Enqueue the necessary scripts / styles for each block
   *
   * @return void
   */
  public function enqueue_block_scripts() {

    // Register account scripts
    wp_register_style( 'mp-pro-fonts', MEPR_CSS_URL . '/pro-templates/fonts.css', null, MEPR_VERSION );
    wp_register_style( 'mp-pro-login', MEPR_CSS_URL . '/pro-templates/login.css', null, MEPR_VERSION );
    wp_register_style( 'mp-pro-account', MEPR_CSS_URL . '/pro-templates/account.css', array( 'mp-pro-fonts', 'mp-pro-login' ), MEPR_VERSION );

    // Register pricing scripts
    wp_register_style( 'mp-pro-pricing', MEPR_CSS_URL . '/pro-templates/pricing.css', null, MEPR_VERSION );

    // Register checkout scripts
    $prereqs = MeprHooks::apply_filters( 'mepr-signup-styles', array() );
    wp_register_style( 'mp-signup', MEPR_CSS_URL . '/signup.css', $prereqs, MEPR_VERSION );
    wp_register_style( 'mp-pro-checkout', MEPR_CSS_URL . '/pro-templates/checkout.css', array( 'mp-signup' ), MEPR_VERSION );
  }

  /**
   * Filter to add the necessary frontend enqueues for Membership Signup block
   *
   * @param mixed  $return   MeprProduct object if scripts will be enqueued, else false
   * @param object $post      WP_Post
   *
   * @return boolean
   */
  public function signup_block_enqueues( $return, $post ) {

    if ( ! isset( $post->post_content ) ) {
      return $return;
    }

    // We don't want to mess with enqueues on MemberPress products since the files are already properly enqueued there
    if ( ! is_object( $return ) || ! is_a( $return, 'MeprProduct' ) ) {
      $membership = false;

      // Check that the signup form block is added
      $match = preg_match( '/(?:wp:memberpress\/membership-signup\s)(\{(?:[^{}]|(?R))*\})/', $post->post_content, $matches );

      if ( 1 === $match && isset( $matches[1] ) && isset( json_decode( $matches[1], true )['membership'] ) ) {
        $membership = new MeprProduct( json_decode( $matches[1], true )['membership'] );
      } elseif ( preg_match(
        '~(?:wp:memberpress\/checkout\s)+{\"membership_id\"\:[\"\\\'](\d+)[\"\\\']~',
        $post->post_content,
        $m
      ) ) {
        $membership = new MeprProduct( $m[1] );
      }

      // Valid membership
      if ( isset( $membership->ID ) && $membership->ID > 0 ) {
        $return = $membership; // Return the MeprProduct instead of just boolean true (backward compatibility)
      }
    }

    return $return;
  }

  /**
   * Filter to add the necessary frontend enqueues for the Account Form block
   *
   * @param boolean $return  Whether the page is an "Account" page
   * @param object  $post    WP_Post
   *
   * @return boolean
   */
  public function account_block_enqueues( $return, $post ) {

    if ( ! isset( $post->post_content ) ) {
      return $return;
    }

    // Post is an "Account" page if it has the Account Form block
    if ( has_block( 'memberpress/account-form', $post ) ) {
      $return = true;
    }

    return $return;
  }

} // End MeprBlocksCtrl
