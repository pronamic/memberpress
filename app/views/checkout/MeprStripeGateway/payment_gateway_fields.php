<?php

if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

if($mepr_options->show_fname_lname && MeprHooks::apply_filters('mepr_stripe_populate_name_fields', true)) {
  printf(
    '<input type="hidden" name="card-first-name" value="%s" />',
    esc_attr($user->first_name)
  );

  printf(
    '<input type="hidden" name="card-last-name" value="%s" />',
    esc_attr($user->last_name)
  );
}

if($mepr_options->show_address_fields && MeprHooks::apply_filters('mepr_stripe_populate_address_fields', true)) {
  foreach($mepr_options->address_fields as $address_field) {
    printf(
      '<input type="hidden" name="%s" value="%s" />',
      esc_attr(str_replace('mepr-', 'card-', $address_field->field_key)),
      esc_attr(get_user_meta($user->ID, $address_field->field_key, true))
    );
  }
}
