<?php

/**
 * Adds MeprAccountLinksWidget widget.
 */
class MeprAccountLinksWidget extends WP_Widget
{
    /**
     * Register widget with WordPress.
     */
    public function __construct()
    {
        parent::__construct(
            'mepr_account_links_widget', // Base ID.
            'MemberPress Account Links', // Name.
            ['description' => __('Place account links on any page with a sidebar region', 'memberpress')] // Args.
        );
    }

    /**
     * Registers the widget.
     *
     * @return void
     */
    public static function register_widget()
    {
        if (MeprHooks::apply_filters('mepr-enable-legacy-widgets', !current_theme_supports('widgets-block-editor'))) {
            register_widget('MeprAccountLinksWidget');
        }
    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     *
     * @return void
     */
    public function widget($args, $instance)
    {
        $mepr_options = MeprOptions::fetch();
        extract($args);
        $title = MeprHooks::apply_filters('mepr-login-title', $instance['title']);

        echo $before_widget;
        echo $before_title . $title . $after_title;

        if (MeprUtils::is_user_logged_in()) {
            $account_url = $mepr_options->account_page_url();
            $logout_url  = MeprUtils::logout_url();
            MeprView::render('/account/logged_in_widget', get_defined_vars());
        } else {
            $login_url = MeprUtils::login_url();
            MeprView::render('/account/logged_out_widget', get_defined_vars());
        }

        echo $after_widget;
    }

    /**
     * Sanitize widget form values as they are saved.
     *
     * @see WP_Widget::update()
     *
     * @param array $new_instance Values just sent to be saved.
     * @param array $old_instance Previously saved values from database.
     *
     * @return array Updated safe values to be saved.
     */
    public function update($new_instance, $old_instance)
    {
        $instance          = [];
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : __('Login', 'memberpress');

        return $instance;
    }

    /**
     * Back-end widget form.
     *
     * @see WP_Widget::form()
     *
     * @param array $instance Previously saved values from database.
     *
     * @return void
     */
    public function form($instance)
    {
        $title = (!empty($instance['title'])) ? $instance['title'] : __('Account', 'memberpress');

        ?>
    <p>
      <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'memberpress'); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
    </p>
        <?php
    }
}
