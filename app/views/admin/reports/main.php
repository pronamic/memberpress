<?php if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
} ?>

<div class="wrap">
  <h2><?php esc_html_e('Reports', 'memberpress'); ?></h2>

  <div id="overall_info_blocks">
      <?php MeprView::render('/admin/reports/skeleton_info_blocks', ['count' => 7]); ?>
  </div>

  <h2 class="nav-tab-wrapper">
    <a class="nav-tab main-nav-tab" href="#" id="monthly"><?php esc_html_e('Monthly', 'memberpress'); ?></a>
    <a class="nav-tab main-nav-tab" href="#" id="yearly"><?php esc_html_e('Yearly', 'memberpress'); ?></a>
    <a class="nav-tab main-nav-tab" href="#" id="all-time"><?php esc_html_e('All-Time', 'memberpress'); ?></a>
  </h2>

<!-- MONTHLY AREA STUFF HERE -->
  <div id="monthly-reports-area" class="monthly mepr_reports_area">
    <div id="monthly-dropdowns-form" class="dropdown_form">
      <?php MeprReportsHelper::get_monthly_dropdowns_form(); ?>
    </div>

    <?php if ($curr_product === 'all') : ?>
      <div id="monthly-pie-chart-area" class="pie_chart_area">
        <?php MeprView::render('/admin/reports/skeleton_pie'); ?>
      </div>
    <?php endif; ?>

    <div id="monthly-info-blocks" class="info_blocks_area">
      <?php MeprView::render('/admin/reports/skeleton_info_blocks', ['count' => 8]); ?>
    </div>

    <div class="float_block_separator"></div>

    <div id="monthly-area-graphs" class="area_graphs">
      <h2 class="nav-tab-wrapper">
        <a class="nav-tab monthly-nav-tab nav-tab-active" href="#" id="mamounts"><?php esc_html_e('Amounts', 'memberpress'); ?></a>
        <a class="nav-tab monthly-nav-tab" href="#" id="mtransactions"><?php esc_html_e('Transactions', 'memberpress'); ?></a>
      </h2>

      <div id="monthly-amounts-area-graph" class="mamounts monthly_graph_area mepr-loading">
        <?php MeprView::render('/admin/reports/svg_loader'); ?>
      </div>

      <div id="monthly-transactions-area-graph" class="mtransactions monthly_graph_area mepr-loading">
        <?php MeprView::render('/admin/reports/svg_loader'); ?>
      </div>
    </div>

    <div id="monthly-data-table">
      <?php MeprView::render('/admin/reports/skeleton_table'); ?>
    </div>
  </div>

<!-- YEARLY AREA STUFF HERE -->
  <div id="yearly-reports-area" class="yearly mepr_reports_area">
    <div id="yearly-dropdowns-form" class="dropdown_form">
      <?php MeprReportsHelper::get_yearly_dropdowns_form(); ?>
    </div>

    <?php if ($curr_product === 'all') : ?>
      <div id="yearly-pie-chart-area" class="pie_chart_area">
        <?php MeprView::render('/admin/reports/skeleton_pie'); ?>
      </div>
    <?php endif; ?>

    <div id="yearly-info-blocks" class="info_blocks_area">
      <?php MeprView::render('/admin/reports/skeleton_info_blocks', ['count' => 8]); ?>
    </div>

    <div class="float_block_separator"></div>

    <div id="yearly-area-graphs" class="area_graphs">
      <h2 class="nav-tab-wrapper">
        <a class="nav-tab yearly-nav-tab nav-tab-active" href="#" id="yamounts"><?php esc_html_e('Amounts', 'memberpress'); ?></a>
        <a class="nav-tab yearly-nav-tab" href="#" id="ytransactions"><?php esc_html_e('Transactions', 'memberpress'); ?></a>
      </h2>

      <div id="yearly-amounts-area-graph" class="yamounts yearly_graph_area mepr-loading">
        <?php MeprView::render('/admin/reports/svg_loader'); ?>
      </div>

      <div id="yearly-transactions-area-graph" class="ytransactions yearly_graph_area mepr-loading">
        <?php MeprView::render('/admin/reports/svg_loader'); ?>
      </div>
    </div>

    <div id="yearly-data-table">
      <?php MeprView::render('/admin/reports/skeleton_table'); ?>
    </div>
  </div>

<!-- ALL-TIME AREA STUFF HERE -->
  <div id="all-time-reports-area" class="all-time mepr_reports_area">
    <div id="all-time-dropdowns-form" class="dropdown_form">
      <?php MeprReportsHelper::get_all_time_dropdowns_form(); ?>
    </div>

    <?php if ($curr_product === 'all') : ?>
      <div id="all-time-pie-chart-area" class="pie_chart_area">
        <?php MeprView::render('/admin/reports/skeleton_pie'); ?>
      </div>
    <?php endif; ?>

    <div id="all-time-info-blocks" class="info_blocks_area">
      <?php MeprView::render('/admin/reports/skeleton_info_blocks', ['count' => 8]); ?>
    </div>

    <div class="float_block_separator"></div>
  </div>

<!-- HIDDEN STUFF FOR JS HERE -->
  <div id="mepr-reports-hidden-stuff">
    <div id="mepr-reports-main-view" data-value="<?php echo (isset($_GET['main-view']) && !empty($_GET['main-view'])) ? esc_attr(sanitize_text_field(wp_unslash($_GET['main-view']))) : 'monthly'; ?>"></div>
    <div id="mepr-reports-current-data"
      data-current-product="<?php echo esc_attr($curr_product); ?>"
      data-current-month="<?php echo esc_attr($curr_month); ?>"
      data-current-year="<?php echo esc_attr($curr_year); ?>"
    ></div>
    <div id="mepr-reports-pie-title" data-value="<?php esc_attr_e('Total Transactions By Membership', 'memberpress'); ?>"></div>
    <div id="mepr-reports-monthly-areas-title" data-value="<?php esc_attr_e('Amounts By Day Of Month', 'memberpress'); ?>"></div>
    <div id="mepr-reports-monthly-transactions-title" data-value="<?php esc_attr_e('Transactions By Day Of Month', 'memberpress'); ?>"></div>
    <div id="mepr-reports-monthly-htitle" data-value="<?php esc_attr_e('Day Of Month', 'memberpress'); ?>"></div>
    <div id="mepr-reports-yearly-areas-title" data-value="<?php esc_attr_e('Amounts By Month Of Year', 'memberpress'); ?>"></div>
    <div id="mepr-reports-yearly-transactions-title" data-value="<?php esc_attr_e('Transactions By Month Of Year', 'memberpress'); ?>"></div>
    <div id="mepr-reports-yearly-htitle" data-value="<?php esc_attr_e('Month Of Year', 'memberpress'); ?>"></div>
    <div id="mepr-reports-currency-symbol" data-value="<?php echo esc_attr($mepr_options->currency_symbol); ?>"></div>
  </div>
</div>
