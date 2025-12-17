jQuery(document).ready(function($) {
  function mepr_process_table_search() {
    var loc = window.location.href;

    loc = loc.replace(/[&\?]search=[^&]*/gi, '');
    loc = loc.replace(/[&\?]search-field=[^&]*/gi, '');
    loc = loc.replace(/[&\?]paged=[^&]*/gi, ''); // Show first page when search button is clicked

    var search = encodeURIComponent($('#cspf-table-search').val());
    var search_field = $('#cspf-table-search-field').val();

    loc = loc + '&search=' + search + '&search-field=' + search_field;

    // Clean up
    if(!/\?/.test(loc) && /&/.test(loc)) {
      loc = loc.replace(/&/,'?'); // not global, just the first
    }

    window.location = loc;
  }

  $("#cspf-table-search").keyup(function(e) {
    // Apparently 13 is the enter key
    if(e.which == 13) {
      e.preventDefault();
      mepr_process_table_search();
    }
  });

  $("#cspf-table-search-submit").on('click', function(e) {
    e.preventDefault();
    mepr_process_table_search();
  });

  $(".current-page").keyup(function(e) {
    // Apparently 13 is the enter key
    if(e.which == 13) {
      e.preventDefault();
      var loc = window.location.href;
      loc = loc.replace(/&paged=[^&]*/gi, '');

      if($(this).val() != '')
        window.location = loc + '&paged=' + escape($(this).val());
      else
        window.location = loc;
    }
  });

  $("#cspf-table-perpage").change(function(e) {
    var loc = window.location.href;
    loc = loc.replace(/&perpage=[^&]*/gi, '');

    if($(this).val() != '')
      window.location = loc + '&perpage=' + $(this).val();
    else
      window.location = loc;
  });

  $("#mepr_search_filter").click( function() {
    var loc = window.location.href;
    if(!validateDateRange()) {
      return false;
    }
    $('.mepr_filter_field').each( function() {
      var arg = $(this).attr('id');
      console.log(arg);
      var re = new RegExp("[&\?]" + arg + "=[^&]*","gi");
      console.log(re);
      loc = loc.replace(re, '');
      loc = loc + '&' + arg + "=" + $(this).val();
    } );

    // Clean up
    if(!/\?/.test(loc) && /&/.test(loc)) {
      loc = loc.replace(/&/,'?'); // not global, just the first
    }

    window.location = loc;
  } );

  // Show Hide Date Range Filters.
  $('#date_range_filter').on('change', function() {
    var selectedValue = $(this).val();
    var dateRangeFilterRow = $('.mepr_date_range_filters_row');
    var dateFieldRow = $('.mepr_filter_field_row');

    if (selectedValue === 'custom') {
      dateRangeFilterRow.show();
      dateFieldRow.show();
    } else {
      dateRangeFilterRow.hide();
      if (selectedValue === 'all') {
        resetDateRange();
        dateFieldRow.hide();
      } else {
        setDateRange(selectedValue);
        dateFieldRow.show();
      }
    }
  });

  // Validate date range before proceeding.
  $('#mepr_date_filter').click(function() {
    if (!validateDateRange()) {
      return false;
    }

    $('#mepr_search_filter').click();
  });

  // Function to validate date range.
  function validateDateRange() {
    var startDate = $('#date_start').val();
    var endDate = $('#date_end').val();

    // If both dates are empty or undefined, no validation needed.
    if (!startDate && !endDate) {
      return true;
    }

    if (!startDate || !endDate) {
      alert(MeprTableControls.date_field_required);
      return false;
    }

    // Validate date format (YYYY-MM-DD).
    var dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(startDate) || !dateRegex.test(endDate)) {
      alert(MeprTableControls.invalid_date_format);
      return false;
    }

    // Check if end date is before start date
    if (endDate < startDate) {
      alert(MeprTableControls.end_date_before_start);
      return false;
    }

    return true;
  }

  // Format date.
  function formatDate(date) {
    return date.getFullYear() + '-' +
           String(date.getMonth() + 1).padStart(2, '0') + '-' +
           String(date.getDate()).padStart(2, '0');
  }

  // Set date range based on preset selection, this sets the date range in the site timezone (not UTC or Browser timezone).
  function setDateRange(selectedValue) {
    var startDate = $('#date_start');
    var endDate = $('#date_end');
    var start, end;
    var siteTimezone = MeprTxn.site_timezone || 'UTC';
    var now = new Date();

    // Create a date object representing "now" in the site timezone
    var siteNow = new Date(now.toLocaleString("en-US", {timeZone: siteTimezone}));
    var today = new Date(siteNow.getFullYear(), siteNow.getMonth(), siteNow.getDate());

    var milliSecondsPerDay = 24 * 60 * 60 * 1000;
    var milliSecondsPerSecond = 1000;

    switch(selectedValue) {
      case 'today':
        start = today;
        end = new Date(today.getTime() + milliSecondsPerDay - milliSecondsPerSecond);
        break;
      case 'yesterday':
        start = new Date(today.getTime() - milliSecondsPerDay);
        end = new Date(today.getTime() - milliSecondsPerSecond);
        break;
      case 'this_week':
        start = new Date(today.getTime() - today.getDay() * milliSecondsPerDay);
        end = new Date(start.getTime() + 7 * milliSecondsPerDay - milliSecondsPerSecond);
        break;
      case 'last_week':
        start = new Date(today.getTime() - (today.getDay() + 7) * milliSecondsPerDay);
        end = new Date(start.getTime() + 7 * milliSecondsPerDay - milliSecondsPerSecond);
        break;
      case 'this_month':
        start = new Date(today.getFullYear(), today.getMonth(), 1);
        end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        break;
      case 'last_month':
        start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        end = new Date(today.getFullYear(), today.getMonth(), 0);
        break;
      case 'this_year':
        start = new Date(today.getFullYear(), 0, 1);
        end = new Date(today.getFullYear(), 11, 31);
        break;
      case 'last_year':
        start = new Date(today.getFullYear() - 1, 0, 1);
        end = new Date(today.getFullYear() - 1, 11, 31);
        break;
      case 'custom':
        return;
    }

    if (start && end) {
      resetDateRange();
      startDate.val(formatDate(start));
      endDate.val(formatDate(end));
    }
  }

  // Reset date range.
  function resetDateRange() {
    $('#date_start').val('');
    $('#date_end').val('');
  }
});
