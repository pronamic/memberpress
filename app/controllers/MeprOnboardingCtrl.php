<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprOnboardingCtrl extends MeprBaseCtrl {
  public function load_hooks() {
    add_filter('submenu_file', 'MeprOnboardingCtrl::highlight_menu_item');
    add_action('admin_enqueue_scripts', 'MeprOnboardingCtrl::admin_enqueue_scripts');
    add_action('admin_notices', 'MeprOnboardingCtrl::remove_all_admin_notices', 0);
    add_action('wp_ajax_mepr_onboarding_save_features', 'MeprOnboardingCtrl::save_features');
    add_action('wp_ajax_mepr_onboarding_save_new_content', 'MeprOnboardingCtrl::save_new_content');
    add_action('wp_ajax_mepr_onboarding_save_new_membership', 'MeprOnboardingCtrl::save_new_membership');
    add_action('wp_ajax_mepr_onboarding_get_membership', 'MeprOnboardingCtrl::get_membership');
    add_action('wp_ajax_mepr_onboarding_search_content', 'MeprOnboardingCtrl::search_content');
    add_action('wp_ajax_mepr_onboarding_set_content', 'MeprOnboardingCtrl::set_content');
    add_action('wp_ajax_mepr_onboarding_unset_content', 'MeprOnboardingCtrl::unset_content');
    add_action('wp_ajax_mepr_onboarding_mark_content_steps_skipped', 'MeprOnboardingCtrl::mark_content_steps_skipped');
    add_action('wp_ajax_mepr_onboarding_mark_steps_complete', 'MeprOnboardingCtrl::mark_steps_complete');
    add_action('wp_ajax_mepr_onboarding_add_stripe_payment_method', 'MeprOnboardingCtrl::add_stripe_payment_method');
    add_action('wp_ajax_mepr_onboarding_add_paypal_payment_method', 'MeprOnboardingCtrl::add_paypal_payment_method');
    add_action('wp_ajax_mepr_onboarding_add_authorize_payment_method', 'MeprOnboardingCtrl::add_authorize_payment_method');
    add_action('wp_ajax_mepr_onboarding_save_authorize_config', 'MeprOnboardingCtrl::save_authorize_config');
    add_action('wp_ajax_mepr_onboarding_add_offline_payment_method', 'MeprOnboardingCtrl::add_offline_payment_method');
    add_action('wp_ajax_mepr_onboarding_remove_payment_method', 'MeprOnboardingCtrl::remove_payment_method');
    add_action('wp_ajax_mepr_onboarding_save_new_rule', 'MeprOnboardingCtrl::save_new_rule');
    add_action('wp_ajax_mepr_onboarding_get_rule', 'MeprOnboardingCtrl::get_rule');
    add_action('wp_ajax_mepr_onboarding_unset_rule', 'MeprOnboardingCtrl::unset_rule');
    add_action('wp_ajax_mepr_onboarding_unset_membership', 'MeprOnboardingCtrl::unset_membership');
    add_action('wp_ajax_mepr_onboarding_install_correct_edition', 'MeprOnboardingCtrl::install_correct_edition');
    add_action('wp_ajax_mepr_onboarding_install_addons', 'MeprOnboardingCtrl::install_addons');
    add_action('wp_ajax_mepr_onboarding_load_complete_step', 'MeprOnboardingCtrl::load_complete_step');
    add_action('wp_ajax_mepr_onboarding_load_create_new_content', 'MeprOnboardingCtrl::load_create_new_content');
    add_action('wp_ajax_mepr_onboarding_load_finish_step', 'MeprOnboardingCtrl::load_finish_step');
    add_action('wp_ajax_mepr_onboarding_finish', 'MeprOnboardingCtrl::finish');
    add_action('mepr_license_activated', 'MeprOnboardingCtrl::license_activated');
    add_action('mepr_license_deactivated', 'MeprOnboardingCtrl::license_deactivated');
    add_action('admin_menu', 'MeprOnboardingCtrl::validate_step');
    add_action('load-memberpress_page_memberpress-onboarding', 'MeprOnboardingCtrl::settings_redirect');
    add_action('admin_notices', 'MeprOnboardingCtrl::admin_notice');
  }

  public static function route() {
    global $wpdb;

    $wpdb->query("INSERT INTO {$wpdb->options} (option_name, option_value) VALUES('mepr_onboarded', '1') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value);");

    $step = isset($_GET['step']) ? (int) $_GET['step'] : 0;

    if($step) {
      $steps = [
        [
          'title' => __('Activate License', 'memberpress'),
          'content' => MEPR_VIEWS_PATH . '/admin/onboarding/license.php',
          'nav' => MEPR_VIEWS_PATH . '/admin/onboarding/nav/license.php',
          'step' => 1,
        ],
        [
          'title' => __('Enable Features', 'memberpress'),
          'content' => MEPR_VIEWS_PATH . '/admin/onboarding/features.php',
          'nav' => MEPR_VIEWS_PATH . '/admin/onboarding/nav/features.php',
          'step' => 2,
        ],
        [
          'title' => __('Create or Select Content', 'memberpress'),
          'content' => MEPR_VIEWS_PATH . '/admin/onboarding/content.php',
          'nav' => MEPR_VIEWS_PATH . '/admin/onboarding/nav/content.php',
          'step' => 3,
        ],
        [
          'title' => __('Create Membership', 'memberpress'),
          'content' => MEPR_VIEWS_PATH . '/admin/onboarding/membership.php',
          'nav' => MEPR_VIEWS_PATH . '/admin/onboarding/nav/membership.php',
          'step' => 4,
        ],
        [
          'title' => __('Protect Content', 'memberpress'),
          'content' => MEPR_VIEWS_PATH . '/admin/onboarding/rules.php',
          'nav' => MEPR_VIEWS_PATH . '/admin/onboarding/nav/rules.php',
          'step' => 5,
        ],
        [
          'title' => __('Payment Options', 'memberpress'),
          'content' => MEPR_VIEWS_PATH . '/admin/onboarding/payments.php',
          'nav' => MEPR_VIEWS_PATH . '/admin/onboarding/nav/payments.php',
          'step' => 6,
        ],
        [
          'title' => __('Finish Setup', 'memberpress'),
          'content' => MEPR_VIEWS_PATH . '/admin/onboarding/finish.php',
          'nav' => MEPR_VIEWS_PATH . '/admin/onboarding/nav/finish.php',
          'step' => 7,
        ],
        [
          'title' => __('Complete', 'memberpress'),
          'content' => MEPR_VIEWS_PATH . '/admin/onboarding/complete.php',
          'nav' => MEPR_VIEWS_PATH . '/admin/onboarding/nav/complete.php',
          'step' => 8,
        ],
      ];

      MeprView::render('/admin/onboarding/wizard', compact('step', 'steps'));
    }
    else {
      MeprView::render('/admin/onboarding/welcome');
    }
  }

  public static function admin_enqueue_scripts() {
    if(self::is_onboarding_page()) {
      wp_enqueue_style('memberpress-onboarding', MEPR_CSS_URL . '/admin-onboarding.css', [], MEPR_VERSION);
      wp_enqueue_script('memberpress-onboarding', MEPR_JS_URL . '/admin_onboarding.js', ['jquery'], MEPR_VERSION, true);
      wp_localize_script('memberpress-onboarding', 'MeprOnboardingL10n', [
        'step' => isset($_GET['step']) ? (int) $_GET['step'] : 0,
        'ajax_url' => admin_url('admin-ajax.php'),
        'onboarding_url' => admin_url('admin.php?page=memberpress-onboarding'),
        'features' => self::get_features(),
        'save_features_nonce' => wp_create_nonce('mepr_onboarding_save_features'),
        'save_new_content_nonce' => wp_create_nonce('mepr_onboarding_save_new_content'),
        'save_new_rule_nonce' => wp_create_nonce('mepr_onboarding_save_new_rule'),
        'save_new_membership_nonce' => wp_create_nonce('mepr_onboarding_save_new_membership'),
        'get_membership_nonce' => wp_create_nonce('mepr_onboarding_get_membership'),
        'get_rule_nonce' => wp_create_nonce('mepr_onboarding_get_rule'),
        'install_correct_edition' => wp_create_nonce('mepr_onboarding_install_correct_edition'),
        'install_addons' => wp_create_nonce('mepr_onboarding_install_addons'),
        'load_complete_step' => wp_create_nonce('mepr_onboarding_load_complete_step'),
        'load_create_new_content' => wp_create_nonce('mepr_onboarding_load_create_new_content'),
        'load_finish_step' => wp_create_nonce('mepr_onboarding_load_finish_step'),
        'set_content_nonce' => wp_create_nonce('mepr_onboarding_set_content'),
        'unset_content_nonce' => wp_create_nonce('mepr_onboarding_unset_content'),
        'unset_rule_nonce' => wp_create_nonce('mepr_onboarding_unset_rule'),
        'unset_membership_nonce' => wp_create_nonce('mepr_onboarding_unset_membership'),
        'mark_content_steps_skipped_nonce' => wp_create_nonce('mepr_onboarding_mark_content_steps_skipped'),
        'mark_steps_complete_nonce' => wp_create_nonce('mepr_onboarding_mark_steps_complete'),
        'search_content_nonce' => wp_create_nonce('mepr_onboarding_search_content'),
        'add_payment_method_nonce' => wp_create_nonce('mepr_add_payment_method'),
        'remove_payment_method_nonce' => wp_create_nonce('mepr_remove_payment_method'),
        'save_authorize_config_nonce' => wp_create_nonce('mepr_save_authorize_config'),
        'deactivate_confirm' => __('Are you sure? MemberPress will not be functional if this License Key is deactivated.', 'memberpress'),
        'deactivate_license_nonce' => wp_create_nonce('mepr_deactivate_license'),
        'an_error_occurred' => __('An error occurred', 'memberpress'),
        'content_id' => MeprOnboardingHelper::get_content_post_id(),
        'membership_id' => MeprOnboardingHelper::get_membership_post_id(),
        'membership_rule_id' => MeprOnboardingHelper::get_rule_post_id(),
        'course_name' => __('Course Name', 'memberpress'),
        'page_title' => __('Page Title', 'memberpress'),
        'course' => __('Course', 'memberpress'),
        'page' => __('Page', 'memberpress'),
        'may_take_couple_minutes' => __('This may take a couple of minutes', 'memberpress'),
        'finish_nonce' => wp_create_nonce('mepr_onboarding_finish'),
        'memberships_url' => admin_url('edit.php?post_type=memberpressproduct'),
        'error_installing_addon' => __('An error occurred when installing an add-on, please download and install the add-ons manually.', 'memberpress'),
        'edition_url_param' => isset($_GET['edition']) ? sanitize_text_field(wp_unslash($_GET['edition'])) : '',
      ]);

      wp_enqueue_script('paypal-partner-sdk', 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js', [], MEPR_VERSION, true);
    }
  }

  private static function get_features() {
    return [
      'memberpress-courses' => 'MemberPress Courses',
      'memberpress-downloads' => 'MemberPress Downloads',
      'memberpress-buddypress' => 'MemberPress BuddyPress',
      'memberpress-developer-tools' => 'MemberPress Developer Tools',
      'memberpress-gifting' => 'MemberPress Gifting',
      'memberpress-corporate' => 'MemberPress Corporate Accounts',
      'easy-affiliate' => 'Easy Affiliate',
    ];
  }

  public static function remove_all_admin_notices() {
    if(self::is_onboarding_page()) {
      remove_all_actions('admin_notices');
    }
  }

  public static function highlight_menu_item($submenu_file) {
    remove_submenu_page('memberpress', 'memberpress-onboarding');

    if(self::is_onboarding_page()) {
      $submenu_file = 'edit.php?post_type=memberpressproduct';
    }

    return $submenu_file;
  }

  public static function is_onboarding_page() {
    $id = MeprUtils::get_current_screen_id();

     return !empty($id) && is_string($id) && preg_match('/_page_memberpress-onboarding$/', $id);
  }

  private static function validate_request($nonce_action) {
    if(!MeprUtils::is_post_request()) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    if(!MeprUtils::is_logged_in_and_an_admin()) {
      wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    if(!check_ajax_referer($nonce_action, false, false)) {
      wp_send_json_error(__('Security check failed.', 'memberpress'));
    }
  }

  private static function get_request_data($nonce_action) {
    self::validate_request($nonce_action);

    if(!isset($_POST['data']) || !is_string($_POST['data'])) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    $data = json_decode(wp_unslash($_POST['data']), true);

    if(!is_array($data)) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    return $data;
  }

  public static function save_features() {
    $data = self::get_request_data('mepr_onboarding_save_features');

    $valid_features = self::get_features();
    $features = [];

    foreach($data as $feature) {
      if(array_key_exists($feature, $valid_features)) {
        $features[] = $feature;
      }
    }

    $addons_installed = array();
    $data = array();
    $data['features'] = $features;
    $data['addons_not_installed'] = array();

    if(!empty($features)){
      $license_addons = MeprUpdateCtrl::addons(true, true, true);

      // lets try to install and activate add-on.
      foreach( $features as $addon_slug ){
        $response = self::maybe_install_activate_addons($license_addons, $addon_slug);
        if( -1 === (int) $response ){
          $data['addons_not_installed'][] = $addon_slug;
        }
      }
    }

    MeprOnboardingHelper::set_selected_features($data);
    MeprOnboardingHelper::maybe_set_steps_completed(2);

    wp_send_json_success($data);
  }

  public static function maybe_install_activate_addons($license_addons, $addon_slug) {
    $return_value = -1;

    if(isset($license_addons->$addon_slug)) {
      $addon_info = $license_addons->$addon_slug;

      $plugin_url = $addon_info->url;

      $installed = isset($addon_info->extra_info->directory) && is_dir(WP_PLUGIN_DIR . '/' . $addon_info->extra_info->directory);
      $active = isset($addon_info->extra_info->main_file) && is_plugin_active($addon_info->extra_info->main_file);

      if($installed && $active) { // already installed and active.
        return 1;
      }
      elseif($installed && !$active) { // already installed and inactive.

        if(isset($addon_info->extra_info->main_file)) {
          self::maybe_install_dependent_plugin($addon_slug);
          $result = activate_plugins(wp_unslash($addon_info->extra_info->main_file));
          return (int) is_wp_error($result);
        }
        else {
          return 0;
        }
      }
      else {
        return (int) self::download_and_activate_addon($addon_info, $plugin_url, $addon_slug);
      }
    }

    // Check if EA is installed or active.
    if('easy-affiliate' == $addon_slug) {
      $installed = is_dir(WP_PLUGIN_DIR . '/easy-affiliate');
      $active = is_plugin_active('easy-affiliate/easy-affiliate.php');

      if($installed && $active) { // already installed and active.
        return 1;
      }
      elseif($installed && !$active) { // already installed and inactive.
        $result = activate_plugins('easy-affiliate/easy-affiliate.php');
        return (int) is_wp_error($result);
      }
      else {
        $mepr_options = MeprOptions::fetch();

        if(empty($mepr_options->mothership_license)) {
          return 0;
        }

        $domain = defined('MEPR_ONBOARDING_MP_URL') ? MEPR_ONBOARDING_MP_URL : 'https://memberpress.com';
        $url = $domain . '/wp-admin/admin-ajax.php?action=mepr_onboarding_get_ea_license';

        $response = wp_remote_post(
          $url,
          [
            'body' => [
              'key' => $mepr_options->mothership_license
            ]
          ]
        );

        $code = wp_remote_retrieve_response_code($response);

        if($code == 200) {
          $data = json_decode(wp_remote_retrieve_body($response), true);

          if(isset($data['success']) && is_bool($data['success'])) {
            if($data['success']) {
              // Install Easy Affiliate
              $result = self::download_and_activate_plugin($data['data']['download_url']);

              if($result && class_exists('EasyAffiliate\\Lib\\CtrlFactory')) {
                try {
                  $ctrl = EasyAffiliate\Lib\CtrlFactory::fetch('UpdateCtrl');
                  $ctrl->activate_license($data['data']['license_key']);
                }
                catch(Exception $e) {
                  // ignore
                }
              }

              return (int) $result;
            }
          }
        }
      }
    }

    return $return_value;
  }

  public static function maybe_install_dependent_plugin($addon_slug) {
    if('memberpress-buddypress' === (string)$addon_slug){
      $buddypress_plugin = 'https://downloads.wordpress.org/plugin/buddypress.latest-stable.zip';
      $buddypress_main_file = 'buddypress/bp-loader.php';
      $buddyboss_main_file = 'buddyboss-platform/bp-loader.php';

      $bboss_installed = is_dir(WP_PLUGIN_DIR . '/' . 'buddyboss-platform');
      $bboss_active = is_plugin_active($buddyboss_main_file);

      if($bboss_installed && $bboss_active) {
        return 1;
      }

      // if buddyboss is installed but not active, let's activate.
      if($bboss_installed && !$bboss_active) {
        $result = activate_plugins(wp_unslash($buddyboss_main_file));
        delete_transient('_bp_activation_redirect');
        return $result;
      }

      $bp_installed = is_dir(WP_PLUGIN_DIR . '/' . 'buddypress');
      $bp_active = is_plugin_active($buddypress_main_file);
      if($bp_installed && !$bp_active) {
        $result = activate_plugins(wp_unslash($buddypress_main_file));
        delete_transient('_bp_activation_redirect');
        return $result;
      }else{
        $result = (int) self::download_and_activate_plugin($buddypress_plugin);
        delete_transient('_bp_activation_redirect');
        return $result;
      }
    }
  }

  public static function save_new_content() {
    $data = self::get_request_data('mepr_onboarding_save_new_content');

    if(empty($data['type']) || empty($data['title']) || !in_array($data['type'], ['course', 'page'], true)) {
      wp_send_json_error(esc_html__('Bad request.', 'memberpress'));
    }

    if(!current_user_can('publish_posts')) {
      wp_send_json_error(esc_html__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    $type = sanitize_text_field($data['type']);
    $title = sanitize_text_field($data['title']);

    $post_id = wp_insert_post([
      'post_type' => $type == 'course' ? 'mpcs-course' : 'page',
      'post_title' => wp_slash($title), // post_title is expected to be slashed
      'post_status' => 'publish',
    ], true);

    if(is_wp_error($post_id)) {
      wp_send_json_error($post_id->get_error_message());
    }

    $post = get_post($post_id);

    if(!$post instanceof WP_Post) {
      wp_send_json_error(esc_html__('Post not found.', 'memberpress'));
    }

    MeprOnboardingHelper::set_content_post_id($post_id);
    MeprOnboardingHelper::set_rule_post_id(0);
    MeprOnboardingHelper::maybe_set_steps_completed(2);

    wp_send_json_success([
      'heading' => $post->post_type == 'mpcs-course' ? esc_html__('Course Name', 'memberpress') : esc_html__('Page Title', 'memberpress'),
      'post' => $post,
      'rule_data' => MeprOnboardingHelper::get_rules_step_data(),
    ]);
  }

  public static function get_content_search_results_html($search_query = '') {
    $posts = array();
    $post_types = ['page'];
    if(MeprOnboardingHelper::is_courses_addon_applicable()){
      $post_types = ['mpcs-course', 'page'];
    }
    if('' == $search_query){
      $content_id = MeprOnboardingHelper::get_content_post_id();

      $args = [
        'post_type' => $post_types,
        'post_status' => 'publish',
        'numberposts' => 6,
        'post__not_in' => array($content_id),
        'orderby' => 'modified',
        'order' => 'DESC',
      ];

      $posts = get_posts($args);

      if($content_id){
        $content_post = get_post($content_id);
        $posts[] = $content_post;
      }
    }else{

      $args = [
        'post_type' => $post_types,
        'post_status' => 'publish',
        'numberposts' => 6,
        'orderby' => 'modified',
        'order' => 'DESC',
        's' => $search_query,
      ];

      $posts = get_posts($args);
    }

    return MeprView::get_string('/admin/onboarding/content-search-results', compact('posts', 'search_query'));
  }

  public static function search_content() {
    $data = self::get_request_data('mepr_onboarding_search_content');

    if(!isset($data['search']) || !is_string($data['search'])) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    $search = sanitize_text_field($data['search']);

    wp_send_json_success(self::get_content_search_results_html($search));
  }

  public static function license_activated() {

    if( ! isset($_GET['page']) || ! isset($_GET['step']) ){
      return;
    }

    MeprOnboardingHelper::maybe_set_steps_completed(1);

    if( 'memberpress-onboarding' === (string) $_GET['page'] && 1 === (int) $_GET['step'] ){

      // to rebuild the mepr_license_info transient.
       MeprUpdateCtrl::manually_queue_update();

      $editions = MeprUtils::is_incorrect_edition_installed();

      if(is_array($editions) && $editions['license']['index'] > $editions['installed']['index'] ){
        $li = get_site_transient('mepr_license_info');
        $result = MeprOptionsCtrl::install_plugin_silently($li['url'], array('overwrite_package' => true));
        if($result === true) {
          do_action('mepr_plugin_edition_changed');
        }
      }
    }
  }

  public static function license_deactivated() {
      MeprOnboardingHelper::set_steps_completed(0);
  }

  public static function validate_step() {

    if( ! isset($_GET['page']) || ! isset($_GET['step']) ){
      return;
    }

    $current_step = (int) $_GET['step'];
    if( 'memberpress-onboarding' === (string) $_GET['page'] && 0 < $current_step ){

       if( $current_step == 4 ){
        $content_id = MeprOnboardingHelper::get_content_post_id();

        if( 0 === (int) $content_id ){
          wp_safe_redirect(admin_url('admin.php?page=memberpress-onboarding&step=3'));
          return;
        }
      }

      if( $current_step == 5 ){
        $content_id = MeprOnboardingHelper::get_content_post_id();
        $membership_post_id = MeprOnboardingHelper::get_membership_post_id();

        if( 0 === (int) $content_id ){
          wp_safe_redirect(admin_url('admin.php?page=memberpress-onboarding&step=3'));
          return;
        }

        if( 0 === (int) $membership_post_id ){
          wp_safe_redirect(admin_url('admin.php?page=memberpress-onboarding&step=4'));
          return;
        }
      }

      $steps_completed =  MeprOnboardingHelper::get_steps_completed();
      $next_applicable_step = $steps_completed + 1;

      if( $current_step > $next_applicable_step ){
        $link_step = $steps_completed + 1;
        wp_safe_redirect(admin_url('admin.php?page=memberpress-onboarding&step='.(int)$link_step));
      }
    }
  }

  private static function download_and_activate_plugin($plugin_url){

    // Prepare variables
    $url = esc_url_raw(
      add_query_arg(
        array(
          'page' => 'memberpress-addons',
          'onboarding' => '1',
        ),
        admin_url('admin.php')
      )
    );

    $creds = request_filesystem_credentials($url, '', false, false, null);

    // Check for file system permissions
    if(false === $creds) {
      return false;
    }

    if(!WP_Filesystem($creds)) {
      return false;
    }

    // We do not need any extra credentials if we have gotten this far, so let's install the plugin
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    // Do not allow WordPress to search/download translations, as this will break JS output
    remove_action('upgrader_process_complete', array('Language_Pack_Upgrader', 'async_upgrade'), 20);

    // Create the plugin upgrader with our custom skin
    $installer = new Plugin_Upgrader(new MeprAddonInstallSkin());

    $plugin = wp_unslash($plugin_url);
    $installer->install($plugin);

    // Flush the cache and return the newly installed plugin basename
    wp_cache_flush();

    if($installer->plugin_info()) {
      $plugin_basename = $installer->plugin_info();

      // Activate the plugin silently
      $activated = activate_plugin($plugin_basename);

      if(!is_wp_error($activated)) {
        return true;
      } else {
        return false;
      }
    }

    return false;
  }

  private static function download_and_activate_addon($addon_info,$plugin_url, $addon_slug = ''){

    if(!$addon_info->installable){
      return -1; // upgrade required.
    }

    // Prepare variables
    $url = esc_url_raw(
      add_query_arg(
        array(
          'page' => 'memberpress-addons',
          'onboarding' => '1',
        ),
        admin_url('admin.php')
      )
    );

    $creds = request_filesystem_credentials($url, '', false, false, null);

    // Check for file system permissions
    if(false === $creds) {
      return false;
    }

    if(!WP_Filesystem($creds)) {
      return false;
    }

    // We do not need any extra credentials if we have gotten this far, so let's install the plugin
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    // Do not allow WordPress to search/download translations, as this will break JS output
    remove_action('upgrader_process_complete', array('Language_Pack_Upgrader', 'async_upgrade'), 20);

    // Create the plugin upgrader with our custom skin
    $installer = new Plugin_Upgrader(new MeprAddonInstallSkin());

    $plugin = wp_unslash($plugin_url);
    $installer->install($plugin);

    // Flush the cache and return the newly installed plugin basename
    wp_cache_flush();

    if($installer->plugin_info()) {
      $plugin_basename = $installer->plugin_info();

      self::maybe_install_dependent_plugin($addon_slug);

      // Activate the plugin silently
      $activated = activate_plugin($plugin_basename);

      if(!is_wp_error($activated)) {
        return true;
      } else {
        return false;
      }
    }

    return false;
  }

  public static function set_content() {
    $data = self::get_request_data('mepr_onboarding_set_content');

    if(!current_user_can('publish_posts')) {
      wp_send_json_error(esc_html__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    if(empty($data['content_id'])) {
      wp_send_json_error(esc_html__('Bad request.', 'memberpress'));
    }

    $content_id = absint($data['content_id']);
    $post = get_post($content_id);

    if(!$post instanceof WP_Post){
      wp_send_json_error(esc_html__('Invalid request.', 'memberpress'));
    }

    if(!in_array($post->post_type,array('page','mpcs-course'))){
      wp_send_json_error(esc_html__('Invalid content.', 'memberpress'));
    }

    MeprOnboardingHelper::set_content_post_id($content_id);
    MeprOnboardingHelper::set_rule_post_id(0);
    MeprOnboardingHelper::maybe_set_steps_completed(3);

    wp_send_json_success([
      'rule_data' => MeprOnboardingHelper::get_rules_step_data(),
    ]);
  }

  public static function unset_content() {
    $data = self::get_request_data('mepr_onboarding_unset_content');
    MeprOnboardingHelper::set_content_post_id(0);
    MeprOnboardingHelper::set_rule_post_id(0);
  }

  public static function unset_rule() {
    $data = self::get_request_data('mepr_onboarding_unset_rule');
    MeprOnboardingHelper::set_rule_post_id(0);
  }

  public static function unset_membership() {
    $data = self::get_request_data('mepr_onboarding_unset_membership');
    MeprOnboardingHelper::set_membership_post_id(0);
  }

  public static function mark_content_steps_skipped() {
    $data = self::get_request_data('mepr_onboarding_mark_content_steps_skipped');
    MeprOnboardingHelper::mark_content_steps_skipped();
    MeprOnboardingHelper::maybe_set_steps_completed(5);
  }

  public static function mark_steps_complete() {
    $data = self::get_request_data('mepr_onboarding_mark_steps_complete');
    MeprOnboardingHelper::maybe_set_steps_completed($data['step']);
  }

  public static function save_new_membership() {
    $data = self::get_request_data('mepr_onboarding_save_new_membership');

    if(empty($data['type']) || empty($data['title']) || !in_array($data['type'], ['onetime', 'months','years'], true)) {
      wp_send_json_error(esc_html__('Bad request.', 'memberpress'));
    }

    if(!current_user_can('publish_posts')) {
      wp_send_json_error(esc_html__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    $type = sanitize_text_field($data['type']);
    $title = sanitize_text_field($data['title']);
    $price = sanitize_text_field($data['price']);

    $is_recurring =  in_array($type, ['months','years'], true);

    if( $is_recurring && (float) $price <= 0.0 ){
      wp_send_json_error(esc_html__('Price must be greater than zero for the Billing.', 'memberpress'));
    }

    $product_period_type = 'lifetime';
    if( $is_recurring ){
      $product_period_type = $type;
    }

    $post_id = wp_insert_post([
      'post_type' => 'memberpressproduct',
      'post_title' => wp_slash($title), // post_title is expected to be slashed
      'post_status' => 'publish',
    ], true);

    if(is_wp_error($post_id)) {
      wp_send_json_error($post_id->get_error_message());
    }

    $post = get_post($post_id);

    if(!$post instanceof WP_Post) {
      wp_send_json_error(esc_html__('Post not found.', 'memberpress'));
    }

    $product = new MeprProduct($post_id);

    $product->price = MeprUtils::format_currency_us_float($price);
    $product->pricing_title = $title;
    $product->period = 1;
    $product->period_type = $product_period_type;
    $product->pricing_display = 'auto';
    $product->tax_class = 'standard';
    $product->pricing_button_txt = esc_html__('Sign Up', 'memberpress');
    $product->store_meta();

    MeprOnboardingHelper::set_membership_post_id($post_id);
    MeprOnboardingHelper::maybe_set_steps_completed(4);

    wp_send_json_success(MeprOnboardingHelper::prepare_product_data($product));
  }

  public static function get_membership() {
    $data = self::get_request_data('mepr_onboarding_get_membership');

    if(empty($data['membership_id'])) {
      wp_send_json_error(esc_html__('Bad request.', 'memberpress'));
    }

    if(!current_user_can('publish_posts')) {
      wp_send_json_error(esc_html__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    $post_id = sanitize_text_field($data['membership_id']);
    $post = get_post($post_id);

    if(!$post instanceof WP_Post) {
      wp_send_json_error(esc_html__('Post not found.', 'memberpress'));
    }

    $product = new MeprProduct($post_id);
    wp_send_json_success(MeprOnboardingHelper::prepare_product_data($product));
  }

  public static function add_stripe_payment_method() {
    self::validate_request('mepr_add_payment_method');

    $mepr_options = MeprOptions::fetch();
    $gateway = new MeprStripeGateway();

    if(isset($mepr_options->integrations[$gateway->id])) {
      wp_send_json_error('Gateway already exists');
    }

    $integration = [
      $gateway->id => [
        'id' => $gateway->id,
        'saved' => '1',
        'label' => 'Stripe',
        'gateway' => 'MeprStripeGateway',
        'use_label' => true,
        'use_icon' => true,
        'use_desc' => true,
        'api_keys' => [
          'test' => [
            'public' => '',
            'secret' => '',
          ],
          'live' => [
            'public' => '',
            'secret' => '',
          ],
        ],
        'connect_status' => '',
        'service_account_id' => '',
        'service_account_name' => '',
        'test_mode' => false,
        'stripe_wallet_enabled' => 'on',
      ]
    ];

    $mepr_options->integrations = array_merge($mepr_options->integrations, $integration);
    $mepr_options->store(false);

    update_option('mepr_onboarding_payment_gateway', $gateway->id);

    $account_email = get_option('mepr_authenticator_account_email');
    $secret = get_option('mepr_authenticator_secret_token');
    $site_uuid = get_option('mepr_authenticator_site_uuid');

    if($account_email && $secret && $site_uuid) {
      $stripe_connect_url = MeprStripeGateway::get_stripe_connect_url($gateway->id, true);
    }
    else {
      $stripe_connect_url = MeprAuthenticatorCtrl::get_auth_connect_url(true, $gateway->id, [], admin_url('admin.php?page=memberpress-onboarding&step=6'));
    }

    MeprOnboardingHelper::maybe_set_steps_completed(6);
    wp_send_json_success($stripe_connect_url);
  }

  public static function add_paypal_payment_method() {
    $data = self::get_request_data('mepr_add_payment_method');

    $sandbox = isset($data['sandbox']) && $data['sandbox'];
    $auth_code = isset($data['auth_code']) ? sanitize_text_field($data['auth_code']) : '';
    $shared_id = isset($data['shared_id']) ? sanitize_text_field($data['shared_id']) : '';
    $gateway_id = isset($data['gateway_id']) ? sanitize_text_field($data['gateway_id']) : '';

    if(empty($auth_code) || empty($shared_id) || empty($gateway_id)) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    try {
      update_option('mepr_onboarding_payment_gateway', $gateway_id);

      $ctrl = MeprCtrlFactory::fetch('MeprPayPalConnectCtrl');
      $ctrl->handle_update_creds($sandbox, $auth_code, $shared_id, $gateway_id);

      MeprOnboardingHelper::maybe_set_steps_completed(6);

      wp_send_json_success(MeprOnboardingHelper::get_payment_gateway_html());
    }
    catch(Exception $e) {
      wp_send_json_error($e->getMessage());
    }
  }

  public static function add_authorize_payment_method() {
    $data = self::get_request_data('mepr_add_payment_method');

    $mepr_options = MeprOptions::fetch();
    $gateway = new MeprAuthorizeGateway();

    if(isset($mepr_options->integrations[$gateway->id])) {
      wp_send_json_error('Gateway already exists');
    }

    if( 1 === (int) $data['upgrade_required'] && ! MeprOnboardingHelper::is_pro_license() ){
      update_option('mepr_onboarding_payment_gateway', 'MeprAuthorizeGateway');
      MeprOnboardingHelper::maybe_set_steps_completed(6);
      wp_send_json_success([
        'payment_gateway_html' => MeprOnboardingHelper::get_payment_gateway_html(),
        'webhook_url' => $gateway->notify_url('whk'),
      ]);
    }

    $integration = [
      $gateway->id => [
        'id' => $gateway->id,
        'saved' => '1',
        'label' => 'Authorize.net',
        'gateway' => 'MeprAuthorizeGateway',
        'use_label' => true,
        'use_icon' => true,
        'use_desc' => true,
        'login_name' => '',
        'transaction_key' => '',
        'signature_key' => '',
      ]
    ];

    $mepr_options->integrations = array_merge($mepr_options->integrations, $integration);
    $mepr_options->store(false);

    update_option('mepr_onboarding_payment_gateway', $gateway->id);
    MeprOnboardingHelper::maybe_set_steps_completed(6);

    wp_send_json_success([
      'payment_gateway_html' => MeprOnboardingHelper::get_payment_gateway_html(),
      'webhook_url' => $gateway->notify_url('whk'),
    ]);
  }

  public static function add_offline_payment_method() {
    self::validate_request('mepr_add_payment_method');

    $mepr_options = MeprOptions::fetch();

    if(!empty($mepr_options->integrations)) {
      // Bail successfully if we already have a payment method
      wp_send_json_success();
    }

    $gateway = new MeprArtificialGateway();

    if(isset($mepr_options->integrations[$gateway->id])) {
      wp_send_json_error('Gateway already exists');
    }

    $integration = [
      $gateway->id => [
        'id' => $gateway->id,
        'saved' => '1',
        'label' => 'Offline Payment',
        'gateway' => 'MeprArtificialGateway',
        'use_label' => true,
        'use_icon' => true,
        'use_desc' => true,
      ]
    ];

    $mepr_options->integrations = array_merge($mepr_options->integrations, $integration);
    $mepr_options->store(false);

    MeprOnboardingHelper::maybe_set_steps_completed(6);

    wp_send_json_success();
  }

  public static function remove_payment_method() {
    $data = self::get_request_data('mepr_remove_payment_method');

    $saved_gateway_id = get_option('mepr_onboarding_payment_gateway');
    $gateway_id = isset($data['gateway_id']) ? sanitize_text_field($data['gateway_id']) : '';

    if( $gateway_id === 'MeprAuthorizeGateway' ){
      MeprOnboardingHelper::maybe_set_steps_completed(5);
      delete_option('mepr_onboarding_payment_gateway');
      wp_send_json_success(1);
      return;
    }

    if(empty($gateway_id) || empty($data['gateway_id']) || $gateway_id != $data['gateway_id']) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    $mepr_options = MeprOptions::fetch();
    $gateway = $mepr_options->payment_method($saved_gateway_id);

    if(!$gateway instanceof MeprStripeGateway && !$gateway instanceof MeprPayPalCommerceGateway && !$gateway instanceof MeprAuthorizeGateway) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    // Don't delete a gateway that has a transaction or subscription
    $mepr_db = MeprDb::fetch();
    $transaction_count = (int) $mepr_db->get_count($mepr_db->transactions, ['gateway' => $gateway_id]);
    $subscription_count = (int) $mepr_db->get_count($mepr_db->subscriptions, ['gateway' => $gateway_id]);

    if($transaction_count > 0 || $subscription_count > 0) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    $integrations = $mepr_options->integrations;
    unset($integrations[$gateway_id]);
    $mepr_options->integrations = $integrations;
    $mepr_options->store(false);

    if($gateway instanceof MeprStripeGateway) {
      try {
        $ctrl = MeprCtrlFactory::fetch('MeprStripConnectCtrl');
        $ctrl->disconnect($gateway->id, 'remote');
      }
      catch(Exception $e) {
        // ignore
      }
    }
    elseif($gateway instanceof MeprPayPalCommerceGateway) {
      $jwt = MeprAuthenticatorCtrl::generate_jwt([
        'site_uuid' => get_option('mepr_authenticator_site_uuid')
      ]);

      $options = [
        'method'  => 'DELETE',
        'headers' => MeprUtils::jwt_header($jwt, MEPR_PAYPAL_SERVICE_DOMAIN),
        'body' => [
          'method-id' => $gateway->id,
        ],
      ];

      if(apply_filters('mepr_onboarding_paypal_sandbox', false)) {
        $endpoint = "/sandbox/credentials/{$gateway->id}";
      }
      else {
        $endpoint = "/credentials/{$gateway->id}";
      }

      wp_remote_request(MEPR_PAYPAL_SERVICE_URL . $endpoint, $options);
    }

    delete_option('mepr_onboarding_payment_gateway');
    MeprOnboardingHelper::set_steps_completed(5);

    wp_send_json_success();
  }

  public static function save_authorize_config() {
    $data = self::get_request_data('mepr_save_authorize_config');

    $gateway_id = isset($data['gateway_id']) ? sanitize_text_field($data['gateway_id']) : '';

    if(empty($gateway_id)) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    $mepr_options = MeprOptions::fetch();
    $gateway = $mepr_options->payment_method($gateway_id);
    $integrations = $mepr_options->integrations;

    if(!$gateway instanceof MeprAuthorizeGateway || !isset($integrations[$gateway->id]) || !is_array($integrations[$gateway->id])) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    $login_name = isset($data['login_name']) ? sanitize_text_field($data['login_name']) : '';
    $transaction_key = isset($data['transaction_key']) ? sanitize_text_field($data['transaction_key']) : '';
    $signature_key = isset($data['signature_key']) ? sanitize_text_field($data['signature_key']) : '';

    $errors = [];

    if(empty($login_name)) {
      $errors[] = 'login-name';
    }

    if(empty($transaction_key)) {
      $errors[] = 'transaction-key';
    }

    if(empty($signature_key)) {
      $errors[] = 'signature-key';
    }

    if(!empty($errors)) {
      wp_send_json_error(['errors' => $errors]);
    }

    $integrations[$gateway->id]['login_name'] = $login_name;
    $integrations[$gateway->id]['transaction_key'] = $transaction_key;
    $integrations[$gateway->id]['signature_key'] = $signature_key;

    $mepr_options->integrations = $integrations;
    $mepr_options->store(false);

    wp_send_json_success(MeprOnboardingHelper::get_payment_gateway_html());
  }

  public static function save_new_rule() {
    $data = self::get_request_data('mepr_onboarding_save_new_rule');

    if(empty($data['content']) || empty($data['membershipname'])) {
      wp_send_json_error(esc_html__('Bad request.', 'memberpress'));
    }

    if(!current_user_can('publish_posts')) {
      wp_send_json_error(esc_html__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    $rule_data = MeprOnboardingHelper::get_rules_step_data();

    if(empty($rule_data['content_title']) || empty($rule_data['membership_title'])) {
      wp_send_json_error(esc_html__('Bad request.', 'memberpress'));
    }

    $content_id = $rule_data['content_id'];
    $membership_id = $rule_data['membership_id'];

    $rule_title = sprintf(esc_html__('A Single %s', 'memberpress'),$rule_data['content_type']) . ': ' . $rule_data['content_title'];

    $post_id = wp_insert_post([
      'post_type' => 'memberpressrule',
      'post_title' => wp_slash($rule_title),
      'post_status' => 'publish',
    ], true);

    if(is_wp_error($post_id)) {
      wp_send_json_error($post_id->get_error_message());
    }

    $post = get_post($post_id);

    if(!$post instanceof WP_Post) {
      wp_send_json_error(esc_html__('Post not found.', 'memberpress'));
    }

    MeprOnboardingHelper::set_rule_post_id($post_id);

    $rule = new MeprRule($post_id);
    $rule->mepr_type = sanitize_text_field($rule_data['mepr_type']);
    $rule->mepr_content = sanitize_text_field($rule_data['content_id']);
    $rule->store_meta();

    // Delete rules first then add them back below
    MeprRuleAccessCondition::delete_all_by_rule($post_id);

    // Let's store the access rules
    $rule_access_condition = new MeprRuleAccessCondition(0);
    $rule_access_condition->rule_id = $post_id;
    $rule_access_condition->access_type = 'membership';
    $rule_access_condition->access_operator = 'is';
    $rule_access_condition->access_condition = $rule_data['membership_id'];
    $rule_access_condition->store();


    MeprOnboardingHelper::maybe_set_steps_completed(5);

    wp_send_json_success([
      'rule_data' => $rule_data,
    ]);
  }

  public static function get_rule() {
    $data = self::get_request_data('mepr_onboarding_get_rule');

    if(empty($data['membership_rule_id'])) {
      wp_send_json_error(esc_html__('Bad request.', 'memberpress'));
    }

    if(!current_user_can('publish_posts')) {
      wp_send_json_error(esc_html__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    $post_id = sanitize_text_field($data['membership_rule_id']);
    $post = get_post($post_id);

    if(!$post instanceof WP_Post) {
      wp_send_json_error(esc_html__('Post not found.', 'memberpress'));
    }

    wp_send_json_success(MeprOnboardingHelper::get_rules_step_data());
  }

  public static function install_correct_edition() {
    self::validate_request('mepr_onboarding_install_correct_edition');
    $li = get_site_transient('mepr_license_info');

    if(!empty($li) && is_array($li) && !empty($li['url']) && MeprUtils::is_url($li['url'])) {
      $result = self::install_plugin_silently($li['url'], array('overwrite_package' => true));

      if($result instanceof WP_Error) {
        wp_send_json_error($result->get_error_message());
      }
      elseif($result === true) {
        do_action('mepr_plugin_edition_changed');
        wp_send_json_success(__('The correct edition of MemberPress has been installed successfully.', 'memberpress'));
      }
      else {
        wp_send_json_error(__('Failed to install the correct edition of MemberPress, please download it from memberpress.com and install it manually.', 'memberpress'));
      }
    }

    wp_send_json_error(__('License data not found', 'memberpress'));
  }

  private static function install_plugin_silently($url, $args) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $skin = new Automatic_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader($skin);

    if(!$skin->request_filesystem_credentials(false, WP_PLUGIN_DIR)) {
      return new WP_Error('no_filesystem_access', __('Failed to get filesystem access', 'memberpress'));
    }

    return $upgrader->install($url, $args);
  }

  public static function install_addons() {
    $data = self::get_request_data('mepr_onboarding_install_addons');

    if(empty($data['addon_slug'])) {
      wp_send_json_error(__('Bad request.', 'memberpress'));
    }

    if(!current_user_can('publish_posts')) {
      wp_send_json_error(__('Sorry, you don\'t have permission to do this.', 'memberpress'));
    }

    $features_data = MeprOnboardingHelper::get_selected_features_data();
    if(!isset($features_data['addons_installed'])){
      $features_data['addons_installed'] = array();
    }

    if(!isset($features_data['addons_upgrade_failed'])){
      $features_data['addons_upgrade_failed'] = array();
    }

    if(!empty($features_data['addons_not_installed'])) {
      if(in_array($data['addon_slug'], $features_data['addons_not_installed'], true)) {
        $license_addons = MeprUpdateCtrl::addons(true, true, true);

        // lets try to install and activate add-on.
        foreach ($features_data['addons_not_installed'] as $i => $addon_slug) {
          if($addon_slug == $data['addon_slug']) {
            $response = self::maybe_install_activate_addons($license_addons, $addon_slug);
            $next_addon = isset($features_data['addons_not_installed'][$i + 1]) ? $features_data['addons_not_installed'][$i + 1] : '';

            if(1 === (int) $response) {
              $features_data['addons_installed'][] = $addon_slug;
              $features_data['addons_installed'] = array_unique($features_data['addons_installed']);

              MeprOnboardingHelper::set_selected_features($features_data);
              wp_send_json_success(array('addon_slug' => $addon_slug, 'message' => '', 'status' => 1, 'next_addon' => $next_addon));
            }
            else {
              $features_data['addons_upgrade_failed'][] = $addon_slug;
              $features_data['addons_upgrade_failed'] = array_unique($features_data['addons_upgrade_failed']);

              MeprOnboardingHelper::set_selected_features($features_data);
              wp_send_json_success(array('addon_slug' => $addon_slug, 'message' => esc_html__('Unable to install. Please download and install manually.', 'memberpress'), 'status' => 0, 'next_addon' => $next_addon));
            }
          }
        }
      }
    }
  }

  public static function load_complete_step() {
    $data = self::get_request_data('mepr_onboarding_load_complete_step');

    wp_send_json_success(['html' => MeprOnboardingHelper::get_completed_step_urls_html()]);
  }

  public static function load_create_new_content() {
    $data = self::get_request_data('mepr_onboarding_load_create_new_content');

    wp_send_json_success(['html' =>  MeprView::get_string('/admin/onboarding/parts/content_popup', get_defined_vars())]);
  }

  public static function load_finish_step() {
    $data = self::get_request_data('mepr_onboarding_load_finish_step');
    wp_send_json_success(['html' =>  MeprView::get_string('/admin/onboarding/parts/finish', get_defined_vars())]);
  }

  public static function finish() {
    self::validate_request('mepr_onboarding_finish');

    update_option('mepr_onboarding_complete', '1');

    wp_send_json_success();
  }

  public static function settings_redirect() {
    if(!is_user_logged_in() || wp_doing_ajax() || !is_admin() || is_network_admin() || !MeprUtils::is_mepr_admin() || MeprUtils::is_post_request()) {
      return;
    }

    global $wpdb;

    wp_cache_flush();
    $wpdb->flush();

    $onboarding_complete = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'mepr_onboarding_complete'");

    if($onboarding_complete === '1') {
      nocache_headers();
      wp_redirect(admin_url('admin.php?page=memberpress-options'), 307);
      exit;
    }
  }

  public static function admin_notice() {
    if(!MeprUtils::is_memberpress_admin_page() || !MeprUtils::is_logged_in_and_an_admin()) {
      return;
    }

    if(!get_option('mepr_onboarded') || get_option('mepr_onboarding_complete') == '1' || get_transient('mepr_dismiss_notice_continue_onboarding')) {
      return;
    }
    ?>
    <div class="notice notice-info mepr-notice-dismiss-daily is-dismissible" data-notice="continue_onboarding">
      <p>
        <?php
        printf(
          // translators: %1$s open link tag, %2$s: close link tag
          esc_html__("Hey, it looks like you started setting up MemberPress but didn't finish, %1\$sclick here to continue%2\$s.", 'memberpress'),
          '<a href="' . esc_url(admin_url('admin.php?page=memberpress-onboarding&step=1')) . '">',
          '</a>'
        );
        ?>
      </p>
    </div>
    <?php
  }
}
