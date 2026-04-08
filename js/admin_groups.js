(function($) {
  $(document).ready(function() {
    // Function to reindex coupon form fields after reordering
    // Note: DOM IDs use unique identifiers and never change, only form field names need reindexing
    function reindexCouponFields() {
      $('#sortable-coupons li.coupon-item').each(function(index) {
        var $item = $(this);
        var fieldPrefix = '_mepr_group_coupons';
        // Match the first bracket pair after prefix which contains the index (e.g., [0], [1], [TEMPLATE_INDEX])
        var indexPattern = /^_mepr_group_coupons\[(\d+|TEMPLATE_INDEX)\]/;
        var newIndex = '[' + index + ']';

        // Update all form fields (input, select) with names starting with fieldPrefix
        // DOM IDs and data-box attributes remain unchanged (they use unique IDs)
        $item.find('input[name^="' + fieldPrefix + '"], select[name^="' + fieldPrefix + '"]').each(function() {
          var $field = $(this);
          var oldName = $field.attr('name');
          // Replace the first bracket pair (index) with new index
          var newName = oldName.replace(indexPattern, fieldPrefix + newIndex);
          $field.attr('name', newName);
        });
      });
    }

    //Make memberships sortable
    $(function() {
      $('#sortable-products').sortable();
      $('#sortable-coupons').sortable({
        update: function() {
          reindexCouponFields();
        }
      });
    });
    function sync_fallback_available_products() {
      var option;
      var available_products = $('#sortable-products select[name="_mepr_products[product][]"] :selected');
      var fallback_select = $('select[name="_mepr_fallback_membership"]');
      var selected_fallback = $(':selected', fallback_select).first().val();

      //Remove all options except the default
      $('option:gt(0)', fallback_select).remove();
      //Add the available product options
      available_products.each(function() {
        option = $("<option></option>")
          .attr("value", this.value)
          .text(this.text);
        //Set the selected value
        if(selected_fallback === this.value) {
          option.attr('selected', 'selected');
        }
        fallback_select.append(option);
      });

      return false;
    }
    function show_readylaunch_limit() {
      // Bail early if RL isn't enabled for pricing page.
      if(!MeprAdminGroups.readylaunch_enabled) {
        return;
      }

      var count = $('ol#sortable-products li').length;

      if(count > 5){
        $('#readylaunch-group-limit').show()
        // $("#readylaunch-group-limit").insertAfter("body");
        // $('#readylaunch-group-limit').show()
      } else{
        $('#readylaunch-group-limit').hide()
      }
    }
    show_readylaunch_limit();

    //Add new membership li
    $('a#add-new-product').click(function() {
      if($('ol#sortable-products li').length >= 5){show_readylaunch_limit()}
      $('ol#sortable-products').append($('div#hidden-line-item').html());
      sync_fallback_available_products();
      show_readylaunch_limit();
      return false;
    });
    //Remove a membership li
    $('body').on('click', 'a.remove-product-item', function() {
      $(this).parent().parent().remove();
      sync_fallback_available_products();
      show_readylaunch_limit();
      return false;
    });
    //Alert if membership already is assigned to another group
    $('body').on('change', 'select.group_products_dropdown', function() {
      var data = {
        action: 'mepr_is_product_already_in_group',
        product_id: $(this).val()
      };
      $.post(ajaxurl, data, function(response) {
        if(response != '') {
          alert(response); //Alerts the user to the fact that this Membership is already in a group
        }
      });
      sync_fallback_available_products();
    });

    //Add new coupon li
    $('a#add-new-coupon').click(function() {
      var newItem = $('div#hidden-coupon-line-item').html();
      var itemCount = $('ol#sortable-coupons li').length;
      // Generate unique ID for this new item (never changes)
      var uniqueId = 'coupon_' + Date.now() + '_' + Math.random().toString(36).slice(2, 11);
      // Replace template IDs and data-box attributes with unique IDs
      newItem = newItem.replace(/apply_schedule_box_template/g, 'apply_schedule_box_' + uniqueId);
      newItem = newItem.replace(/unapply_schedule_box_template/g, 'unapply_schedule_box_' + uniqueId);
      // Replace template array indices with actual index (for form field names only)
      // Match _mepr_group_coupons[TEMPLATE_INDEX] and replace with actual index
      newItem = newItem.replace(/_mepr_group_coupons\[TEMPLATE_INDEX\]/g, '_mepr_group_coupons[' + itemCount + ']');
      $('ol#sortable-coupons').append(newItem);
      reindexCouponFields();
      return false;
    });
    //Remove a coupon li
    $('body').on('click', 'a.remove-coupon-item', function() {
      $(this).parent().parent().remove();
      reindexCouponFields();
      return false;
    });
    //Toggle schedule date fields when checkboxes are clicked
    $('body').on('click', '.coupon-item .mepr-toggle-checkbox', function() {
      var $targetBox = $('#' + $(this).data('box'));
      if ($(this).is(':checked')) {
        $targetBox.removeClass('mepr-sub-box-hidden').slideDown('fast');
      } else {
        $targetBox.slideUp('fast', function() {
          $targetBox.addClass('mepr-sub-box-hidden');
        });
      }
    });

    //Change mouse pointer over li items
    $('body').on('mouseenter', '.mepr-sortable li', function() {
      $(this).addClass('mepr-hover');
    });
    $('body').on('mouseleave', '.mepr-sortable li', function() {
      $(this).removeClass('mepr-hover');
    });

    //hide pricing page theme box if Disable Pricing Page is on
    if($('#_mepr_group_pricing_page_disabled').is(":checked")) {
      $('#mepr_hidden_pricing_page_theme').hide();
    } else {
      $('#mepr_hidden_pricing_page_theme').show();
    }
    //hide alternate group url box if Disable Pricing Page is off
    if(!$('#_mepr_group_pricing_page_disabled').is(":checked")) {
      $('#mepr_hidden_alternate_group_url').hide();
    } else {
      $('#mepr_hidden_alternate_group_url').show();
    }
    $('#_mepr_group_pricing_page_disabled').click(function() {
      $('#mepr_hidden_pricing_page_theme').slideToggle('fast');
      $('#mepr_hidden_alternate_group_url').slideToggle('fast');
    });

    // ---- Live Preview Helpers ----
    var radiusMap = { none: '0', subtle: '6px', rounded: '12px' };
    var shadowMap = { none: 'none', subtle: '0 1px 3px rgba(0,0,0,0.1)', prominent: '0 4px 20px rgba(0,0,0,0.12)' };

    function updateLivePreview() {
      var $preview = $('#mepr-live-preview-inner');
      if (!$preview.length) return;

      var primary      = $('#mepr-opt-primary-color').val() || '#2563eb';
      var accent       = $('#mepr-opt-accent-color').val() || '#16a34a';
      var btnColor     = $('#mepr-opt-button-color').val() || '#2563eb';
      var btnText      = $('#mepr-opt-btn-text-color').val() || '#ffffff';
      var textColor    = $('#mepr-opt-text-color').val() || '#1e293b';
      var priceColor   = $('#mepr-opt-price-color').val() || '#0f172a';
      var hlText       = $('#mepr-opt-hl-text-color').val() || '#ffffff';
      var hlPrice      = $('#mepr-opt-hl-price-color').val() || '#ffffff';
      var hlBtn        = $('#mepr-opt-hl-btn-color').val() || '#16a34a';
      var hlBtnText    = $('#mepr-opt-hl-btn-text-color').val() || '#ffffff';
      var radius       = radiusMap[$('#mepr-opt-border-radius').val()] || '12px';
      var shadow       = shadowMap[$('#mepr-opt-shadow').val()] || '0 1px 3px rgba(0,0,0,0.1)';

      $preview.attr('style',
        '--mepr-primary:' + primary +
        ';--mepr-accent:' + accent +
        ';--mepr-btn-color:' + btnColor +
        ';--mepr-btn-text-color:' + btnText +
        ';--mepr-text-color:' + textColor +
        ';--mepr-price-color:' + priceColor +
        ';--mepr-hl-text-color:' + hlText +
        ';--mepr-hl-price-color:' + hlPrice +
        ';--mepr-hl-btn-color:' + hlBtn +
        ';--mepr-hl-btn-text-color:' + hlBtnText +
        ';--mepr-radius:' + radius +
        ';--mepr-shadow:' + shadow + ';'
      );

    }

    function setPreviewTemplate(template) {
      var $preview = $('#mepr-live-preview-inner');
      if (!$preview.length) return;
      // Remove old template class, add new one
      $preview.removeClass(function(i, cls) {
        return (cls.match(/mepr-tpl-\S+/g) || []).join(' ');
      }).addClass('mepr-tpl-' + template);
    }

    // ---- Modern/Legacy Template Type Toggle ----
    $('input[name="_mepr_group_template_type"]').on('change', function() {
      var type = $(this).val();
      $('.mepr-toggle-label').removeClass('active');
      $(this).closest('.mepr-toggle-label').addClass('active');

      if (type === 'modern') {
        $('#mepr-modern-templates-section').slideDown('fast');
        $('#mepr-legacy-themes-section').slideUp('fast');
        // Auto-select 'bold' if no modern template is chosen yet.
        if (!$('#mepr-modern-template-value').val()) {
          $('.mepr-template-card[data-template="bold"]').trigger('click');
        }
      } else {
        $('#mepr-modern-templates-section').slideUp('fast');
        $('#mepr-legacy-themes-section').slideDown('fast');
        // Clear modern template when switching to legacy
        $('#mepr-modern-template-value').val('');
      }
    });

    // Per-template default colors and which highlighted fields to show
    var templateDefaults = {
      'modern-cards': {
        hl_text_color: '#1e293b',
        hl_price_color: '#2563eb',
        hl_btn_color: '#2563eb',
        hl_btn_text_color: '#ffffff',
        showHlText: false,
        showHlPrice: true
      },
      'minimal': {
        hl_text_color: '#1e293b',
        hl_price_color: '#2563eb',
        hl_btn_color: '#2563eb',
        hl_btn_text_color: '#ffffff',
        showHlText: false,
        showHlPrice: true
      },
      'bold': {
        hl_text_color: '#ffffff',
        hl_price_color: '#ffffff',
        hl_btn_color: '#16a34a',
        hl_btn_text_color: '#ffffff',
        showHlText: true,
        showHlPrice: true
      }
    };

    function applyTemplateDefaults(template) {
      var defaults = templateDefaults[template];
      if (!defaults) return;

      // Only update highlighted colors if they haven't been customized (still at a known default)
      var knownDefaults = ['#ffffff', '#1e293b', '#2563eb', '#16a34a', '#0f172a'];

      var $hlText = $('#mepr-opt-hl-text-color');
      if (knownDefaults.indexOf($hlText.val()) > -1) {
        $hlText.val(defaults.hl_text_color);
      }
      var $hlPrice = $('#mepr-opt-hl-price-color');
      if (knownDefaults.indexOf($hlPrice.val()) > -1) {
        $hlPrice.val(defaults.hl_price_color);
      }
      var $hlBtn = $('#mepr-opt-hl-btn-color');
      if (knownDefaults.indexOf($hlBtn.val()) > -1) {
        $hlBtn.val(defaults.hl_btn_color);
      }
      var $hlBtnText = $('#mepr-opt-hl-btn-text-color');
      if (knownDefaults.indexOf($hlBtnText.val()) > -1) {
        $hlBtnText.val(defaults.hl_btn_text_color);
      }

      // Show/hide highlighted color fields based on template
      $('#mepr-opt-hl-text-color').closest('.mepr-color-field')[defaults.showHlText ? 'show' : 'hide']();
      $('#mepr-opt-hl-price-color').closest('.mepr-color-field')[defaults.showHlPrice ? 'show' : 'hide']();
    }

    // Apply on page load if a template is selected
    var currentTemplate = $('#mepr-modern-template-value').val();
    if (currentTemplate && templateDefaults[currentTemplate]) {
      // Only show/hide fields, don't override saved values
      var d = templateDefaults[currentTemplate];
      $('#mepr-opt-hl-text-color').closest('.mepr-color-field')[d.showHlText ? 'show' : 'hide']();
      $('#mepr-opt-hl-price-color').closest('.mepr-color-field')[d.showHlPrice ? 'show' : 'hide']();
    }

    // ---- Template Card Selection ----
    $('body').on('click', '.mepr-template-card', function() {
      var template = $(this).data('template');
      $('.mepr-template-card').removeClass('selected');
      $(this).addClass('selected');
      $('#mepr-modern-template-value').val(template);
      $('#mepr-template-options').slideDown('fast');
      $('#mepr-live-preview').slideDown('fast');
      applyTemplateDefaults(template);
      setPreviewTemplate(template);
      updateLivePreview();
    });

    // ---- Carousel toggle shows/hides arrow position option ----
    $('#mepr-carousel-enabled').on('change', function() {
      if ($(this).is(':checked')) {
        $('#mepr-arrows-outside-row').slideDown('fast');
      } else {
        $('#mepr-arrows-outside-row').slideUp('fast');
      }
    });

    // ---- Live preview updates on option changes ----
    $('#mepr-opt-primary-color, #mepr-opt-accent-color, #mepr-opt-button-color, #mepr-opt-btn-text-color, #mepr-opt-text-color, #mepr-opt-price-color, #mepr-opt-hl-text-color, #mepr-opt-hl-price-color, #mepr-opt-hl-btn-color, #mepr-opt-hl-btn-text-color').on('input change', updateLivePreview);
    $('#mepr-opt-border-radius, #mepr-opt-shadow').on('change', updateLivePreview);

    // Page Template Toggle
    if( $('#_mepr_use_custom_template').is(':checked') ) {
      $('#mepr-custom-page-template-select').show();
    }

    $('#_mepr_use_custom_template').click( function() {
      if($(this).is(':checked')) {
        $('#mepr-custom-page-template-select').slideDown();
      }
      else {
        $('#mepr-custom-page-template-select').slideUp();
      }
    });
  });
})(jQuery);
