
<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div class="mp_wrapper mpcs-course-list">

  <?php printf('<h1 class="mepr_page_header">%s</h1>', esc_html_x( 'My Courses', 'ui', 'memberpress' )); ?>

  <?php foreach($my_courses as $course): ?>
    <div class="grid">
      <div class="col-1-2 grid-pad">
        <a href="<?php echo get_permalink($course->ID); ?>">
          <?php echo $course->post_title; ?>
        </a>
      </div>
      <div class="col-1-2 grid-pad">
        <?php if($show_bookmark === true): ?>
          <div class="course-progress">
            <div class="user-progress <?php echo $course->user_progress($current_user->ID) == 0 ? 'center-block' : '' ?>" data-value="<?php echo $course->user_progress($current_user->ID); ?>">
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

    <div class="flex-navigation">
      <div class="alignleft">
        <?php previous_posts_link( __('&laquo; Previous', 'memberpress-courses', 'memberpress') ); ?>
      </div>
      <div class="page-count">
        <?php echo $course_query->max_num_pages > 1 ? $paged .' / '. $course_query->max_num_pages : '' ?>
      </div>
      <div class="alignright">
        <?php next_posts_link( __('Next &raquo;', 'memberpress-courses', 'memberpress'), $course_query->max_num_pages); ?>
      </div>
    </div>

</div>
