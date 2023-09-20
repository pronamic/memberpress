<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class MeprPostStatesCtrl extends MeprBaseCtrl {

  public function load_hooks() {
    add_filter( 'display_post_states', array( $this, 'add_display_post_states' ), 10, 2 );
  }

  public function add_display_post_states( $post_states, $post ) {

    $mepr_options = MeprOptions::fetch();

    if ( $mepr_options->thankyou_page_id === $post->ID ) {
      $post_states['thankyou_page_id'] = __( 'MemberPress Thank You Page', 'memberpress' );
    }

    if ( $mepr_options->account_page_id === $post->ID ) {
      $post_states['account_page_id'] = __( 'MemberPress Account Page', 'memberpress' );
    }

    if ( $mepr_options->login_page_id === $post->ID ) {
      $post_states['login_page_id'] = __( 'MemberPress Login Page', 'memberpress'  );
    }

    return $post_states;

  }
}