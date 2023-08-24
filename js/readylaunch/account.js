jQuery(document).ready(function ($) {

  // Add all our events here
  $(document).on("click", ".mepr-profile-details__button", openModal);
  $(document).on("click", ".mepr_modal", closeModal);
  $(document).on("click", ".mepr_modal__close", { force: true }, closeModal);
  $(document).on('submit', '.mepr_modal_form', saveChanges);
  $(document).on("add-disabled-attr", disableElements)
  $(document).on("remove-disabled-attr", reEnableElements)
  $(document).on("click", "#load-more-subscriptions", loadMoreSubscriptions)
  $(document).on("click", "#load-more-payments", loadMorePayments)

  /**
   * Opens Modal Window
   * @param {Object} event object.
   */
  function openModal(event) {
    var $modal = $("#mepr-account-modal");

    $modal.show();
    var fieldName = $(this).data('name');

    // get the current row.
    if ('billing_address' == fieldName) {
      var $currentRow = $modal.find($("input[id^='mepr-address'], select[id^='mepr-address']")).closest('.mp-form-row');
      $modal.find('#mp-address-group-label').show()
    } else if ('name' == fieldName) {
      var $currentRow = $modal.find($("input[id^='user_first_name'], select[id^='user_last_name']")).closest('.mp-form-row');
    } else {
      var $currentRow = $modal.find('#' + fieldName).closest('.mp-form-row');
    }

    // add diabled class to all form elements except those of current row.
    // this will prevent JS errors (eg ...is not focusable) when submitting form.
    $modal.find('.mp-form-row')
      .not($currentRow)
      .find('input, textarea, button, select')
      .addClass('--disabled')
      .trigger('add-disabled-attr');

    $currentRow.show();
  }

  /**
   * Closes Modal Window
   * @param {Object} event object.
   */
  function closeModal(event) {
    var $modal = $("#mepr-account-modal");

    var forceDelete = event.data != undefined && 'force' in event.data;
    if (!event.target.closest('.mepr_modal__box') || forceDelete) {

      // get the current row.
      var $currentRow = $modal.find('.mp-form-row:visible');

      // remove diabled attr from elements with --disabled class
      $modal.find('.mp-form-row')
        .not($currentRow)
        .find('input, textarea, button, select')
        .removeClass('--disabled')
        .trigger('remove-disabled-attr');

      // hide modal and its form rows
      $modal.hide();
      $modal.find('.mp-form-row').hide();
      $modal.find('#mp-address-group-label').hide()

      // clear errors
      $modal.find('.mepr_pro_error').addClass('hidden')
      $modal.find('.mepr_pro_error ul').empty()
    }

  }

  // Saves our Changes
  function saveChanges(e) {
    e.preventDefault();

    var $form = $(this);
    var formData = new FormData(this);
    formData.append("action", "save_profile_changes");
    formData.append("nonce", MeprAccount.nonce);

    // Let's add checkboxes, whether checked or unchecked
    for (var i = 0; i < this.elements.length; i++) {
      var e = this.elements[i];
      if (!e.disabled && e.type == 'checkbox') {
        var val = e.checked ? 'on' : '';
        formData.append(e.name, val);
      }
    }

    _fetch(formData).done(function (response) {
      $form.find('.mepr_pro_error ul').empty()

      if(false === response.success){
        $form.find('.mepr_pro_error').removeClass('hidden');
        for (let i = 0; i < response.data.length; i++){
          var list = document.createElement('li');
          list.innerText=response.data[i];
          $form.find('.mepr_pro_error ul').append(list);
        }
      } else {
        location.reload();
      }
    });
  }



  /**
   * Adds disabled attr to all elements with the --disabled class
   * @param {Event} e the event object.
   */
  function disableElements(e) {
    $(e.target).prop('disabled', true);
  };

  /**
   * Removes disabled attr to all elements with the --disabled class
   * @param {Event} e the event object.
   */
  function reEnableElements(e) {
    $(e.target).prop('disabled', false);
  };

  function loadMoreSubscriptions() {
    var count = $(this).data('count');
    var $spinner = $(this).parent().find('.mepr-account-meta__spinner');
    $spinner.show();
    $(this).hide();

    var formData = new FormData();
    formData.append("count", count);
    formData.append("action", "load_more_subscriptions");
    formData.append("account_url", MeprAccount.account_url);
    formData.append("nonce", MeprAccount.nonce);

    _fetch(formData)
      .done(function (response) {
        $('#mepr-account-content').html(response.data);

        $('.mepr-open-resume-confirm, .mepr-open-cancel-confirm').magnificPopup({
          type: 'inline',
          closeBtnInside: false
        });

        $('.mepr-open-upgrade-popup').magnificPopup({
          type: 'inline',
          closeBtnInside: false
        });

        $spinner.hide();
      });
  }

  function loadMorePayments() {
    var count = $(this).data('count');
    var $spinner = $(this).parent().find('.mepr-account-meta__spinner');
    $spinner.show();
    $(this).hide();

    var formData = new FormData();
    formData.append("count", count);
    formData.append("action", "load_more_payments");
    formData.append("nonce", MeprAccount.nonce);

    _fetch(formData)
      .done(function (response) {
        $('#mepr-account-content').html(response.data);
        $spinner.hide();
      });
  }

  /**
   * A wrapper to perform AJAX requests.
   * Although named 'fetch', this is XHR not Fetch API
   * @param {Object} data Data to pass to AJAX.
   * @returns Promise
   */
  function _fetch(data = {}) {
    args = {
      type: 'POST',
      url: MeprAccount.ajax_url,
      dataType: 'json',
      data: data,
      cache: false,
      processData: false,
      contentType: false,
    };

    return $.ajax(args);
  }

  // Tooltip
  var popperInstances = [];

  $('.mepr-tooltip-trigger').each(function () {
    var $trigger = $(this);
    var $content = $(this).parent().find('.mepr-tooltip-content');
    var PopperInstance = Popper.createPopper($trigger.get(0), $content.get(0), {
      modifiers: [
        {
          name: 'offset',
          options: {
            offset: [0, 8],
          },
        },
      ],
    });

    popperInstances.push(PopperInstance)
  });


  $(document).on('click', function (e) {
    var $trigger = $('.mepr-tooltip-trigger');
    var $popover = $('.mepr-tooltip-content');
    var $target = $(e.target);

    // Change target to parent if child element was clicked
    if(!$target.is($trigger) && $target.parent().is($trigger)){
      $target = $target.parent();
    }

    if ($target.is($popover)) {
      return;
    }

    if ($target.is($trigger)) {

      var index = $('.mepr-tooltip-trigger').index($target.get(0));
      var instance = popperInstances[index];
      var popover = $target.parent().find('.mepr-tooltip-content').get(0);


      if (!instance) return;

      togglePopper(instance, popover);
    } else {
      hidePopperAll($popover)
    }

  });


  function togglePopper(instance, tooltip) {
    if (tooltip.hasAttribute("data-show")) {
      hidePopper(instance, tooltip);
    } else {
      showPopper(instance, tooltip);
    }
  }

  //show and create popper
  function showPopper(instance, tooltip) {
    tooltip.setAttribute('data-show', '');

    instance.setOptions((options) => ({
      ...options,
      modifiers: [
        ...options.modifiers,
        { name: 'eventListeners', enabled: true },
      ],
    }));

    // Update its position
    instance.update();
  }

  //hide and destroy popper instance
  function hidePopper(instance, tooltip) {
    tooltip.removeAttribute('data-show');

    instance.setOptions((options) => ({
      ...options,
      modifiers: [
        ...options.modifiers,
        { name: 'eventListeners', enabled: false },
      ],
    }));
  }

  //hide and destroy popper instance
  function hidePopperAll(tooltips) {
    tooltips.each(function (index, tooltip) {
      tooltip.removeAttribute('data-show');
    })

    popperInstances.forEach(function (instance) {
      instance.setOptions((options) => ({
        ...options,
        modifiers: [
          ...options.modifiers,
          { name: 'eventListeners', enabled: false },
        ],
      }));
    })
  }
});