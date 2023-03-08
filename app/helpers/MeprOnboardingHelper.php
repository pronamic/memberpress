<?php
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You are not allowed to call this page directly.' );}

class MeprOnboardingHelper {
  public static function is_step1($hook) {
    if ( $hook == 'memberpress_page_memberpress-onboarding' && isset($_GET['step']) && 1 === intVal($_GET['step']) )  {
      return true;
    }

    return false;
  }

  public static function maybe_set_steps_completed($step){
    $steps_completed = self::get_steps_completed();
    if( $step > $steps_completed ){
      self::set_steps_completed($step);
    }

    if( $steps_completed == 7 ){
      update_option('mepr_onboarding_complete', '1');
    }
  }

  public static function set_steps_completed($step){
    update_option( 'mepr_onboarding_steps_completed', $step, false );

    if( $step == 0 ){
      self::unmark_content_steps_skipped();
    }
  }

  public static function get_steps_completed(){
    return get_option( 'mepr_onboarding_steps_completed', 0 );
  }

  public static function set_selected_features($features){
    update_option('mepr_onboarding_features', $features, false);
  }

  public static function get_selected_features_data(){
    $metadata = get_option('mepr_onboarding_features', true);
    $data = is_array($metadata) ? $metadata : array();
    return $data;
  }

  public static function get_selected_features(){
    $data = self::get_selected_features_data();
    $features = (isset($data['features']) && is_array($data['features'])) ? $data['features'] : array();
    return $features;
  }

  public static function get_mepr_edition_features($edition, $request_type='') {
    // raw data.
    $dataset = require(MEPR_DATA_PATH.'/features/editions.php');

    $data = array();
    if(!isset($dataset[$edition])){
      return $data;
    }

    // mepr edition data
    $data = $dataset[$edition];
    $valid_types = array('payments','addons');

    $type = '';
    if($request_type != ''){
      if(in_array($request_type, $valid_types, true)){
        $type  = $request_type;
      }
    }

    if(isset($data[$type])){
      return $data[$type];
    }
    return $data;
  }

  public static function is_addon_selectable($plugin_slug) {
    $plugins = get_plugins();
    $plugin_file_slug = $plugin_slug . '.php';
    $is_installed = ! empty($plugins[$plugin_file_slug]);
    $selectable = true;
    if($is_installed){
      if(is_plugin_active($plugin_file_slug)){ // if addon is already installed and active, it must not be selectable.
          $selectable = false;
      }
    }

    return $selectable;
  }

  public static function features_addons_selectable_list(){
    return array(
      'memberpress-courses' => MeprOnboardingHelper::is_addon_selectable('memberpress-courses/main'),
      'memberpress-downloads' => MeprOnboardingHelper::is_addon_selectable('memberpress-downloads/main'),
      'memberpress-buddypress' => MeprOnboardingHelper::is_addon_selectable('memberpress-buddypress/main'),
      'memberpress-gifting' => MeprOnboardingHelper::is_addon_selectable('memberpress-gifting/memberpress-gifting'),
      'memberpress-corporate' => MeprOnboardingHelper::is_addon_selectable('memberpress-corporate/main'),
      'memberpress-developer-tools' => MeprOnboardingHelper::is_addon_selectable('memberpress-developer-tools/main'),
      'easy-affiliate' => MeprOnboardingHelper::is_addon_selectable('easy-affiliate/easy-affiliate'),
    );
  }

  public static function set_content_post_id($id){
    update_option( 'mepr_onboarding_content_post_id', $id, false );

    if(count(self::get_skipped_steps())){
      self::unmark_content_steps_skipped();
      self::set_steps_completed(3);
    }
  }

  public static function get_content_post_id(){
    return get_option( 'mepr_onboarding_content_post_id', 0 );
  }

  public static function mark_content_steps_skipped(){
    update_option( 'mepr_onboarding_content_steps_skipped', 1, false );
  }

  public static function unmark_content_steps_skipped(){
    update_option( 'mepr_onboarding_content_steps_skipped', 0, false );
  }

  public static function set_membership_post_id($id){
    update_option( 'mepr_onboarding_membership_post_id', $id, false );

    if(count(self::get_skipped_steps()) && $id > 0){
      self::unmark_content_steps_skipped();
      $content_id = self::get_content_post_id();
      if($content_id > 0){
        self::set_steps_completed(3);
      }
    }
  }

  public static function get_membership_post_id(){
    return get_option( 'mepr_onboarding_membership_post_id', 0 );
  }

  public static function get_skipped_steps(){
    $is_skipped = get_option( 'mepr_onboarding_content_steps_skipped', 0 );
    if($is_skipped){
      return array(3,4,5);
    }
    return array();
  }

  public static function get_payment_gateway_data() {
    $gateway_id = get_option('mepr_onboarding_payment_gateway');

    if(empty($gateway_id)) {
      return false;
    }

    $mepr_options = MeprOptions::fetch();
    $gateway = $mepr_options->payment_method($gateway_id, true, true);

    if($gateway instanceof MeprStripeGateway) {
      return [
        'id' => $gateway->id,
        'key' => 'stripe',
        'logo_url' => MEPR_IMAGES_URL . '/stripe-logo.png',
        'connected' => !empty($gateway->settings->public_key) && !empty($gateway->settings->secret_key),
        'account' => $gateway->service_account_name,
      ];
    }
    elseif($gateway instanceof MeprPayPalCommerceGateway) {
      return [
        'id' => $gateway->id,
        'key' => 'paypal',
        'logo_url' => MEPR_IMAGES_URL . '/PayPal_with_Tagline.svg',
        'connected' => !empty($gateway->settings->test_client_id) || !empty($gateway->settings->live_client_id),
        'account' => '',
      ];
    }
    elseif($gateway instanceof MeprAuthorizeGateway) {
      return [
        'id' => $gateway->id,
        'key' => 'authorize',
        'logo_url' => MEPR_IMAGES_URL . '/onboarding/authorize.net.svg',
        'connected' => !empty($gateway->settings->login_name) && !empty($gateway->settings->transaction_key) && !empty($gateway->settings->signature_key),
        'account' => '',
      ];
    }
    elseif($gateway_id == 'MeprAuthorizeGateway') {
      return [
        'id' => 'MeprAuthorizeGateway',
        'key' => 'authorize',
        'logo_url' => MEPR_IMAGES_URL . '/onboarding/authorize.net.svg',
        'connected' => false,
        'account' => '',
      ];
    }

    return false;
  }

  public static function get_payment_gateway_html() {
    $data = self::get_payment_gateway_data();

    if(empty($data)) {
      return '';
    }

    $mepr_db = MeprDb::fetch();
    $transaction_count = (int) $mepr_db->get_count($mepr_db->transactions, ['gateway' => $data['id']]);

    ob_start();
    ?>
    <div class="mepr-wizard-payment-gateway mepr-wizard-payment-gateway-<?php echo esc_attr($data['key']); ?>" data-gateway-id="<?php echo esc_attr($data['id']); ?>">
      <div>
        <div class="mepr-wizard-payment-gateway-logo">
          <img src="<?php echo esc_url($data['logo_url']); ?>" alt="">
        </div>
      </div>
      <div>
        <?php if($data['connected']) : ?>
          <div class="mepr-wizard-payment-gateway-status">
            <?php
              if(!empty($data['account'])) {
                printf(
                  // translators: %s: account name
                  esc_html__('Connected to: %s', 'memberpress'),
                  '<span>' . esc_html($data['account']) . '</span>'
                );
              }
              else {
                esc_html_e('Connected', 'memberpress');
              }
            ?>
          </div>
        <?php endif; ?>
        <?php if($data['id'] == 'MeprAuthorizeGateway') : ?>
          <div class="mepr-wizard-payment-gateway-status">
            <?php esc_html_e('Pro feature', 'memberpress'); ?>
          </div>
        <?php endif; ?>
        <?php if($transaction_count == 0) : ?>
          <div id="mepr-wizard-payment-gateway-expand-menu">
            <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/expand-menu.svg'); ?>" alt="">
          </div>
          <div id="mepr-wizard-payment-gateway-menu" class="mepr-hidden">
            <div id="mepr-wizard-payment-gateway-delete"><?php esc_html_e('Remove', 'memberpress'); ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php

    return ob_get_clean();
  }

  public static function prepare_product_data($product){
    $billing_types = array(
      'lifetime' => esc_html__('One-time', 'memberpress'),
      'months' => esc_html__('Recurring (Monthly)', 'memberpress'),
      'years' => esc_html__('Recurring (Anually)', 'memberpress')
    );

    return [
      'title' => $product->post_title,
      'billing' => $billing_types[$product->period_type],
      'price_string' => MeprAppHelper::format_price_string($product, $product->price),
    ];
  }

  public static function get_rules_step_data(){

    $membership_id = self::get_membership_post_id();
    $content_id = self::get_content_post_id();

    $content = get_post( $content_id );
    $membership = get_post( $membership_id );

    $data = [
        'content_title' => '',
        'content_id' => '',
        'membership_title' => '',
        'membership_id' => '',
        'mepr_type' => '',
        'content_type' => '',
    ];

    if( ! $content && ! $membership ){
      return $data;
    }

    if( $content ){
      $data['content_title'] = $content->post_title;
      $data['content_id'] = $content_id;
      $data['mepr_type'] = $content->post_type == 'mpcs-course' ? 'single_mpcs-course' : 'single_page';
      $data['content_type'] = $content->post_type == 'mpcs-course' ? esc_html__('Course', 'memberpress') : esc_html__('Page', 'memberpress');
    }

    if( $membership ){
      $data['membership_title'] = $membership->post_title;
      $data['membership_id'] = $membership_id;
    }

    return $data;
  }

  public static function set_rule_post_id($id){
    update_option( 'mepr_onboarding_rule_post_id', $id, false );
  }

  public static function get_rule_post_id(){
    return get_option( 'mepr_onboarding_rule_post_id', 0 );
  }

  public static function features_list(){
    return array(
      'memberpress-courses' => esc_html__('Course Creator', 'memberpress'),
      'memberpress-downloads' => esc_html__('Digital Downloads', 'memberpress'),
      'memberpress-buddypress' => esc_html__('Member Community', 'memberpress'),
      'memberpress-developer-tools' => esc_html__('Zapier Integration', 'memberpress'),
      'memberpress-gifting' => esc_html__('Gifting', 'memberpress'),
      'memberpress-corporate' => esc_html__('Corporate Accounts', 'memberpress'),
      'easy-affiliate' => esc_html__('Affiliate Program', 'memberpress'),
    );
  }

   public static function is_developer_license(){
    $li = get_site_transient('mepr_license_info');
    if($li){
      if(in_array($li['product_slug'],['developer'],true)){
        return true;
      }
    }
    return false;
  }

  public static function is_pro_license(){
    $li = get_site_transient('mepr_license_info');
    $is_pro = false;

    if($li) {
      $is_pro = MeprUtils::is_pro_edition($li['product_slug']);
    }

    return $is_pro;
  }

  public static function get_license_type() {
    $li = get_site_transient('mepr_license_info');
    if($li) {
      if(MeprUtils::is_pro_edition($li['product_slug'])) {
        return 'memberpress-pro-5';
      }

      if(in_array($li['product_slug'],['memberpress-plus','memberpress-plus-2'],true)) {
        return 'memberpress-plus';
      }

      return $li['product_slug'];
    }

    return false;
  }

  public static function get_completed_step_urls_html() {
    ob_start();
    ?>
    <?php
    $membership_post_id = MeprOnboardingHelper::get_membership_post_id();
    $mepr_options = MeprOptions::fetch();

    // Auto genrate required page.
    if(!is_numeric($mepr_options->login_page_id) || $mepr_options->login_page_id == 0) {
      $mepr_options->login_page_id = MeprAppHelper::auto_add_page(__('Login', 'memberpress'));
    }

    if(!is_numeric($mepr_options->thankyou_page_id) || $mepr_options->thankyou_page_id == 0) {
      $mepr_options->thankyou_page_id = MeprAppHelper::auto_add_page(__('Thank You', 'memberpress'), esc_html__('Your subscription has been set up successfully.', 'memberpress'));
    }

    if(!is_numeric($mepr_options->account_page_id) || $mepr_options->account_page_id == 0) {
      $mepr_options->account_page_id = MeprAppHelper::auto_add_page(__('Account', 'memberpress'));
    }

    ?>
      <div class="mepr-wizard-selected-content-column">
        <div class="mepr-wizard-notification-warning">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
          </svg>
          MemberPress ReadyLaunch™ is currently enabled. It controls page styling and allows you to create beautiful pages effortlessly. If you'd rather use your own theme, just go to MemberPress > Settings > ReadyLaunch™, and disable the templates there.</div>
      </div>
      <hr>
    <?php

    if($membership_post_id > 0):
        $membership_url = get_the_permalink($membership_post_id);
      ?>
      <div class="mepr-wizard-selected-content-column">
        <div class="mepr-wizard-selected-content-heading"><?php esc_html_e('Membership registration','memberpress'); ?></div>
        <div class="mepr-wizard-selected-content-name"><a href="<?php echo esc_url($membership_url); ?>"><?php echo esc_html($membership_url); ?></a></div>
      </div>
      <hr>
    <?php endif; ?>

    <?php if($mepr_options->login_page_id > 0):
        $login_page_url = get_the_permalink($mepr_options->login_page_id);
      ?>
      <div class="mepr-wizard-selected-content-column">
        <div class="mepr-wizard-selected-content-heading"><?php esc_html_e('MemberPress login','memberpress'); ?></div>
        <div class="mepr-wizard-selected-content-name"><a href="<?php echo esc_url($login_page_url); ?>"><?php echo esc_html($login_page_url); ?></a></div>
      </div>
      <hr>
    <?php endif; ?>

    <?php if($mepr_options->account_page_id > 0):
        $account_page_url = get_the_permalink($mepr_options->account_page_id);
      ?>
      <div class="mepr-wizard-selected-content-column">
        <div class="mepr-wizard-selected-content-heading"><?php esc_html_e('Manage account','memberpress'); ?></div>
        <div class="mepr-wizard-selected-content-name"><a href="<?php echo esc_url($account_page_url); ?>"><?php echo esc_html($account_page_url); ?></a></div>
      </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
  }

  public static function is_upgrade_required( $atts ){

    $addons_installed = isset( $atts['addons_installed'] ) ? $atts['addons_installed'] : array();
    $addons_not_installed = isset( $atts['addons_not_installed'] ) ? $atts['addons_not_installed'] : array();
    $payment_gateway = isset( $atts['payment_gateway'] ) ? $atts['payment_gateway'] : '';
    $license_type = self::get_license_type();

    if(!is_array($addons_installed)){
      $addons_installed = array();
    }

    if(!is_array($addons_not_installed)){
      $addons_not_installed = array();
    }

    // Check if license type is not pro or developer and Auth.net payment gateway selected.
    if( ! (MeprUtils::is_pro_edition($license_type) || $license_type == 'developer') && $payment_gateway == 'MeprAuthorizeGateway' ){
      return 'memberpress-pro-5'; // upgrade to pro required.
    }

    // if there are no addons required installation bail out.
    if( empty($addons_not_installed) ){
      return false;
    }

    foreach( $addons_not_installed as $k => $addon_slug ){
      if( in_array($addon_slug,$addons_installed, true) ){
        unset($addons_not_installed[$k]); // already installed.
      }
    }

    // No more addons instalation required? Bailout.
    if( empty($addons_not_installed) ){
      return false;
    }

    if(  in_array($license_type,['developer','memberpress-plus','memberpress-plus-2'], true) ){
      return 'memberpress-pro-5'; // upgrade to pro required.
    }

    $pro_addons = self::get_mepr_edition_features( 'memberpress-pro-5', 'addons' );
    $plus_addons = self::get_mepr_edition_features( 'memberpress-plus-2', 'addons' );

    // Time to check what kind of plan we should offer based on features selection.
    $pro_count = 0;
    $plus_count = 0;
    foreach( $addons_not_installed as $addon_slug ){
      if( in_array($addon_slug,$pro_addons,true) ){
        $pro_count++;
      }

      if( in_array($addon_slug,$plus_addons,true) ){
        $plus_count++;
      }
    }

    if($pro_count > $plus_count){
      return 'memberpress-pro-5'; // upgrade to pro required.
    }else{
      return 'memberpress-plus-2'; // upgrade to plus required.
    }
  }

  public static function get_upgrade_cta_data($type){
    $data = array(
      'memberpress-pro-5' => array(
          'token' => esc_html__('Pro','memberpress'),
          'url' => 'https://memberpress.com/ipob/upgrade-pro/',
          'label' => esc_html__('Upgrade to Pro','memberpress'),
          'heading' => esc_html__('To unlock selected features, upgrade to Pro.', 'memberpress')
      ),
      'memberpress-plus-2' => array(
          'token' => esc_html__('Plus','memberpress'),
          'url' => 'https://memberpress.com/ipob/upgrade-plus/',
          'label' => esc_html__('Upgrade to Plus','memberpress'),
          'heading' => esc_html__('To unlock selected features, upgrade to Plus.', 'memberpress')
      )
    );

    $data = apply_filters('mepr_onboarding_cta_data', $data);

    $cta_data = array();
    if(isset($data[$type])){
      $cta_data = $data[$type];
    }

    return $cta_data;
  }

  public static function is_courses_addon_applicable(){
    if(is_plugin_active('memberpress-courses/main.php')){
        return true;
    }else{
      return false;
    }
  }
} //End class
