var main_view = jQuery('div#mepr-reports-main-view').attr('data-value');
var currency_symbol = jQuery('div#mepr-reports-currency-symbol').attr('data-value');
var mepr_current_product = jQuery('div#mepr-reports-current-data').data('current-product');
var mepr_current_month = jQuery('div#mepr-reports-current-data').data('current-month');
var mepr_current_year = jQuery('div#mepr-reports-current-data').data('current-year');

function mepr_all_time_info_blocks() {
  return new Promise((resolve, reject) => {
    var args = {
      action: 'mepr_all_time_info_blocks',
      product: mepr_current_product,
      month: mepr_current_month,
      year: mepr_current_year,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (json_object) {
      jQuery('#all-time-info-blocks').html(json_object.data.output);
      resolve();
    });
  });
}

function mepr_month_info_blocks() {
  return new Promise((resolve, reject) => {
    var args = {
      action: 'mepr_month_info_blocks',
      product: mepr_current_product,
      month: mepr_current_month,
      year: mepr_current_year,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (json_object) {
      jQuery('#monthly-info-blocks').html(json_object.data.output);
      resolve();
    });
  });
}

function mepr_month_info_table() {
  return new Promise((resolve, reject) => {
    var args = {
      action: 'mepr_month_info_table',
      product: mepr_current_product,
      month: mepr_current_month,
      year: mepr_current_year,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (json_object) {
      jQuery('#monthly-data-table').html(json_object.data.output);
      resolve();
    });
  });
}

function mepr_year_info_blocks() {
  return new Promise((resolve, reject) => {
    var args = {
      action: 'mepr_year_info_blocks',
      product: mepr_current_product,
      month: mepr_current_month,
      year: mepr_current_year,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (json_object) {
      jQuery('#yearly-info-blocks').html(json_object.data.output);
      resolve();
    });
  });
}

function mepr_year_info_table() {
  return new Promise((resolve, reject) => {
    var args = {
      action: 'mepr_year_info_table',
      product: mepr_current_product,
      month: mepr_current_month,
      year: mepr_current_year,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (json_object) {
      jQuery('#yearly-data-table').html(json_object.data.output);
      resolve();
    });
  });
}

function mepr_overall_info_blocks() {
  return new Promise((resolve, reject) => {
    var args = {
      action: 'mepr_overall_info_blocks',
      product: mepr_current_product,
      month: mepr_current_month,
      year: mepr_current_year,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (json_object) {
      jQuery('#overall_info_blocks').html(json_object.data.output);
      resolve();
    });
  });
}

function mepr_monthly_report() {
  return new Promise((resolve, reject) => {
    var month = jQuery('div#monthly-dropdowns-form select[name="month"]').val();
    var year = jQuery('div#monthly-dropdowns-form select[name="year"]').val();
    var product = jQuery('div#monthly-dropdowns-form select[name="product"]').val();
    var main_width = jQuery('div#'+main_view+'-reports-area').width() - 55;

    //Monthly Amounts Area Chart
    var args = {
      action: 'mepr_month_report',
      type: 'amounts',
      month: month,
      year: year,
      product: product,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (data) {
      var chartData = new google.visualization.DataTable(data);
      var chart = new google.visualization.AreaChart(document.getElementById('monthly-amounts-area-graph'));
      jQuery('#monthly-amounts-area-graph').removeClass('mepr-loading');
      var chartSettings = {
         height:'350',
         width: main_width,
         title:jQuery('div#mepr-reports-monthly-areas-title').attr('data-value'),
         hAxis:{
            title:jQuery('div#mepr-reports-monthly-htitle').attr('data-value')
         },
         vAxis:{
            format:currency_symbol
         }
      };

      chart.draw(chartData, chartSettings);
      resolve();
    });
  });
}

function mepr_yearly_report() {
  return new Promise((resolve, reject) => {
    var month = jQuery('div#monthly-dropdowns-form select[name="month"]').val();
    var year = jQuery('div#monthly-dropdowns-form select[name="year"]').val();
    var product = jQuery('div#monthly-dropdowns-form select[name="product"]').val();
    var main_width = jQuery('div#'+main_view+'-reports-area').width() - 55;

    //Yearly Amounts Area Chart
    var args = {
      action: 'mepr_year_report',
      type: 'amounts',
      year: year,
      product: product,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (data) {
      var $chart = jQuery('#yearly-amounts-area-graph'),
        chartSettings = {
          height: '350',
          width: main_width,
          title: jQuery('div#mepr-reports-yearly-areas-title').attr('data-value'),
          hAxis: {
            title: jQuery('div#mepr-reports-yearly-htitle').attr('data-value')
          },
          vAxis:{
            format: currency_symbol
          }
        };

        $chart.removeClass('mepr-loading');

      if($chart.is(':visible')) {
        var chartData = new google.visualization.DataTable(data);
        var chart = new google.visualization.AreaChart($chart[0]);

        chart.draw(chartData, chartSettings);
      }
      else {
        $chart.data({
          chartData: data,
          chartSettings: chartSettings
        });
      }
      resolve();
    });
  });
}

function mepr_monthly_report_txn() {
  return new Promise((resolve, reject) => {
    var month = jQuery('div#monthly-dropdowns-form select[name="month"]').val();
    var year = jQuery('div#monthly-dropdowns-form select[name="year"]').val();
    var product = jQuery('div#monthly-dropdowns-form select[name="product"]').val();
    var main_width = jQuery('div#'+main_view+'-reports-area').width() - 55;

    //Monthly Transactions Area Chart
    var args = {
      action: 'mepr_month_report',
      type: 'transactions',
      month: month,
      year: year,
      product: product,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (data) {
      var $chart = jQuery('#monthly-transactions-area-graph'),
        chartSettings = {
          height: '350',
          width: main_width,
          title: jQuery('div#mepr-reports-monthly-transactions-title').attr('data-value'),
          hAxis: {
            title: jQuery('div#mepr-reports-monthly-htitle').attr('data-value')
          }
        };

        $chart.removeClass('mepr-loading');

      if($chart.is(':visible')) {
        var chartData = new google.visualization.DataTable(data);
        var chart = new google.visualization.AreaChart($chart[0]);

        chart.draw(chartData, chartSettings);
      }
      else {
        $chart.data({
          chartData: data,
          chartSettings: chartSettings
        });
      }
      resolve();
    });
  });
}

function mepr_yearly_report_txn() {
  return new Promise((resolve, reject) => {
    var month = jQuery('div#monthly-dropdowns-form select[name="month"]').val();
    var year = jQuery('div#monthly-dropdowns-form select[name="year"]').val();
    var product = jQuery('div#monthly-dropdowns-form select[name="product"]').val();
    var main_width = jQuery('div#'+main_view+'-reports-area').width() - 55;

    //Yearly Transactions Area Chart
    var args = {
      action: 'mepr_year_report',
      type: 'transactions',
      year: year,
      product: product,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (data) {
      var $chart = jQuery('#yearly-transactions-area-graph'),
        chartSettings = {
          height: '350',
          width: main_width,
          title: jQuery('div#mepr-reports-yearly-transactions-title').attr('data-value'),
          hAxis: {
            title: jQuery('div#mepr-reports-yearly-htitle').attr('data-value')
          }
        };

      $chart.removeClass('mepr-loading');

      if($chart.is(':visible')) {
        var chartData = new google.visualization.DataTable(data);
        var chart = new google.visualization.AreaChart($chart[0]);

        chart.draw(chartData, chartSettings);
      }
      else {
        $chart.data({
          chartData: data,
          chartSettings: chartSettings
        });
      }
      resolve();
    });
  });
}

function mepr_pie_report_monthly() {
  return new Promise((resolve, reject) => {
    var month = jQuery('div#monthly-dropdowns-form select[name="month"]').val();
    var year = jQuery('div#monthly-dropdowns-form select[name="year"]').val();
    var product = jQuery('div#monthly-dropdowns-form select[name="product"]').val();
    var main_width = jQuery('div#'+main_view+'-reports-area').width() - 55;

    // Pie charts are hidden for per-membership reporting, so bail early.
    if(product !== 'all') {
      resolve();
      return;
    }

    //Monthly Pie Chart Totals
    var args = {
      action: 'mepr_pie_report',
      type: 'monthly',
      month: month,
      year: year,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (data) {
      resolve();

      var chartData = new google.visualization.DataTable(data);
      var chart = new google.visualization.PieChart(document.getElementById('monthly-pie-chart-area'));

      var chartSettings = {
        width: 360,
        height: 250,
        chartArea:{width:"100%"},
        title:jQuery('div#mepr-reports-pie-title').attr('data-value')
      };

      chart.draw(chartData, chartSettings);
    });
  });
}

function mepr_pie_report_yearly() {
  return new Promise((resolve, reject) => {
    var month = jQuery('div#yearly-dropdowns-form select[name="month"]').val();
    var year = jQuery('div#yearly-dropdowns-form select[name="year"]').val();
    var product = jQuery('div#yearly-dropdowns-form select[name="product"]').val();
    var main_width = jQuery('div#'+main_view+'-reports-area').width() - 55;

    // Pie charts are hidden for per-membership reporting, so bail early.
    if(product !== 'all') {
      resolve();
      return;
    }

    //Yearly Pie Chart Totals
    var args = {
      action: 'mepr_pie_report',
      type: 'yearly',
      year: year,
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (data) {
      resolve();
      try {

        var chartData = new google.visualization.DataTable(data);
        var chart = new google.visualization.PieChart(document.getElementById('yearly-pie-chart-area'));

        var chartSettings = {
          width: 360,
          height: 250,
          chartArea:{width:"100%"},
          title:jQuery('div#mepr-reports-pie-title').attr('data-value')
        };

        chart.draw(chartData, chartSettings);
      } catch( ex ) {

      }
    });
  });
}

function mepr_pie_report_alltime() {
  return new Promise((resolve, reject) => {
    var product = jQuery('div#all-time-dropdowns-form select[name="product"]').val();

    // Pie charts are hidden for per-membership reporting, so bail early.
    if(product !== 'all') {
      resolve();
      return;
    }

    //All-Time Pie Chart Totals
    var args = {
      action: 'mepr_pie_report',
      type: 'all-time',
      report_nonce: MeprReportData.report_nonce
    };

    jQuery.getJSON(ajaxurl, args, function (data) {
      var chartData = new google.visualization.DataTable(data);
      var chart = new google.visualization.PieChart(document.getElementById('all-time-pie-chart-area'));

      var chartSettings = {
        width: 360,
        height: 250,
        chartArea:{width:"100%"},
        title: jQuery('div#mepr-reports-pie-title').attr('data-value')
      };
      chart.draw(chartData, chartSettings);
      resolve();
    });
  });
}

function mepr_load_nav_tab(main_view) {

  mepr_overall_info_blocks();

  if( 'monthly' === main_view ) {
    mepr_month_info_blocks()
    .then(() => mepr_pie_report_monthly())
    .then(() => mepr_month_info_table())
    .then(() => mepr_monthly_report())
    .catch((error) => {

    });
  }

  if( 'yearly' === main_view ) {
    mepr_pie_report_yearly()
    .then(() => mepr_year_info_blocks())
    .then(() => mepr_yearly_report())
    .then(() => mepr_year_info_table())
    .catch((error) => {

    });
  }

  if( 'all-time' === main_view ) {
     mepr_pie_report_alltime()
    .then(() => mepr_all_time_info_blocks())
    .catch((error) => {

    });
  }

  jQuery('a#'+main_view).addClass('loaded');
}

var drawReportingCharts = function () {
  mepr_load_nav_tab(main_view);
}

google.charts.load('current', { packages: ['corechart'] });
google.charts.setOnLoadCallback(drawReportingCharts);

(function($) {
  $(document).ready(function() {
    //SHOW CHOSEN AREA
    $('.main-nav-tab').removeClass('nav-tab-active');
    $('a#'+main_view).addClass('nav-tab-active');
    $('div#'+main_view+'-reports-area').show();
    $('div#monthly-amounts-area-graph').show();
    $('div#yearly-amounts-area-graph').show();

    //MAIN NAV TABS CONTROL
    $('a.main-nav-tab').click(function() {
      if($(this).hasClass('nav-tab-active'))
        return false;

      if(!$(this).hasClass('loaded')){
        mepr_load_nav_tab($(this).attr('id'));
      }

      var $chosen = $('div.' + $(this).attr('id'));

      $('a.main-nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');

      $('div.mepr_reports_area').hide();
      $chosen.show();

      $chosen.find('.monthly_graph_area, .yearly_graph_area').each(function () {
        maybeDrawHiddenAreaChart($(this));
      });

      return false;
    });

    //MONTHLY NAV TABS CONTROL
    $('a.monthly-nav-tab').click(function() {
      if($(this).hasClass('nav-tab-active'))
        return false;

      mepr_monthly_report_txn();

      var $chosen = $('div.' + $(this).attr('id'));

      $('a.monthly-nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');

      $('div.monthly_graph_area').hide();
      $chosen.show();

      maybeDrawHiddenAreaChart($chosen);

      return false;
    });

    //YEARLY NAV TABS CONTROL
    $('a.yearly-nav-tab').click(function() {
      if($(this).hasClass('nav-tab-active'))
        return false;

      mepr_yearly_report_txn();

      var $chosen = $('div.' + $(this).attr('id'));

      $('a.yearly-nav-tab').removeClass('nav-tab-active');
      $(this).addClass('nav-tab-active');

      $('div.yearly_graph_area').hide();
      $chosen.show();

      maybeDrawHiddenAreaChart($chosen);

      return false;
    });

    function maybeDrawHiddenAreaChart($chart) {
      if($chart.is(':visible') && $chart.data('chartData') && $chart.data('chartSettings')) {
        var chart = new google.visualization.AreaChart($chart[0]);

        chart.draw(
          new google.visualization.DataTable($chart.data('chartData')),
          $chart.data('chartSettings')
        );

        $chart.removeData(['chartData', 'chartSettings']);
      }
    }
  });
})(jQuery);
