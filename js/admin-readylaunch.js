/**
 * ReadyLaunch Admin JavaScript - ES5 implementation
 * Handles modal functionality, template customization, and uploaders
 */

var MeprReadyLaunch = (function ($) {
  'use strict';

  var readyLaunch = {
    // State management
    state: {
      colorPickerDebounce: null,
      uploaders: {}
    },

    // Configuration
    config: {
      uploaderSelectors: [
        '#mepr-design-account-welcome-img',
        '#mepr-design-login-welcome-img',
        '#mepr-design-thankyou-welcome-img',
        '#mepr-design-coaching-welcome-img',
        '#mepr-design-courses-logo'
      ]
    },

    // Main initialization method
    initialize: function () {
      readyLaunch.initUploaders();
      readyLaunch.initColorPickers();
      readyLaunch.initModalHandlers();
      readyLaunch.initTemplateHandlers();
      readyLaunch.initImageHandlers();
      readyLaunch.initializeStates();
    },

    // Uploader functionality
    initUploaders: function () {
      // Check if plupload is available (required dependency)
      if (!window.plupload) {
        return;
      }

      var selectors = readyLaunch.config.uploaderSelectors;

      for (var i = 0; i < selectors.length; i++) {
        var selector = selectors[i];
        // Only create uploader if the element exists in DOM
        if ($(selector).length > 0) {
          readyLaunch.createUploader(selector);
        }
      }
    },

    createUploader: function (selector) {
      if (!window.jQuery || !window.jQuery.MeprPlUploader) {
        return false;
      }

      try {
        var uploader = new jQuery.MeprPlUploader({
          id: selector,
          // Use onFileUploaded instead of onUploadComplete to get response data
          onFileUploaded: function(response) {
            var $element = $(selector);
            readyLaunch.handleFileUploaded($element, response);
          },
          onUploadComplete: function(up, files) {
            var $element = $(selector);
            readyLaunch.handleUploadComplete($element);
          },
          onFilesAdded: function(files) {
            var $element = $(selector);
            readyLaunch.handleFilesAdded($element, files);
          }
        }, window.MeproTemplates);

        readyLaunch.state.uploaders[selector] = uploader;
        uploader.init();

        return true;
      } catch (e) {
        return false;
      }
    },

    handleFilesAdded: function ($element, files) {
      // Files have been selected for upload
    },

    handleFileUploaded: function ($element, response) {
      // The uploader.js already handles updating the image src and hidden input
      // We just need to handle the UI state changes (show/hide elements)
      var $wrapper = $element.closest('.mepr-pluploader-wrapper');

      if ($wrapper.length) {
        $wrapper.find('.mepr-pluploader-preview').show();
        $wrapper.find('.upload-ui').hide();
      }
    },

    // Method to destroy uploaders if needed
    destroyUploader: function (selector) {
      if (readyLaunch.state.uploaders[selector]) {
        try {
          // plupload has a destroy method
          if (readyLaunch.state.uploaders[selector].uploader) {
            readyLaunch.state.uploaders[selector].uploader.destroy();
          }
          delete readyLaunch.state.uploaders[selector];
          return true;
        } catch (e) {
          return false;
        }
      }
      return false;
    },

    // Method to destroy all uploaders
    destroyAllUploaders: function () {
      var selectors = Object.keys(readyLaunch.state.uploaders);
      var destroyedCount = 0;

      for (var i = 0; i < selectors.length; i++) {
        if (readyLaunch.destroyUploader(selectors[i])) {
          destroyedCount++;
        }
      }

      return destroyedCount;
    },

    handleUploadComplete: function ($element) {
      // Called when all files in the queue have finished uploading
    },

    // Color picker functionality
    initColorPickers: function () {
      if (!$.fn.wpColorPicker) {
        return;
      }

      $('.color-field').wpColorPicker({
        change: function (event, ui) {
          clearTimeout(readyLaunch.state.colorPickerDebounce);
          readyLaunch.state.colorPickerDebounce = setTimeout(function () {
            event.target.dispatchEvent(new CustomEvent('input'));
          }, 100);
        }
      });
    },

    // Modal functionality
    modals: {
      show: function(modalId) {
        var $modal = $('#' + modalId);
        if ($modal.length) {
          $modal.addClass('show').show();
          $('body').addClass('modal-open');
        }
      },

      hide: function(modalId) {
        var $modal = $('#' + modalId);
        if ($modal.length) {
          $modal.removeClass('show').hide();
          $('body').removeClass('modal-open');
        }
      },

      hideAll: function() {
        $('.mepr_modal').removeClass('show').hide();
        $('body').removeClass('modal-open');
      }
    },

    initModalHandlers: function () {
      // Modal close button handlers
      $('body').on('click', '.mepr_modal__close', function(e) {
        e.preventDefault();
        var $modal = $(this).closest('.mepr_modal');
        readyLaunch.modals.hide($modal.attr('id'));
      });

      // Modal overlay click handler
      $('body').on('click', '.mepr_modal__overlay', function(e) {
        e.preventDefault();
        var $modal = $(this).closest('.mepr_modal');
        readyLaunch.modals.hide($modal.attr('id'));
      });

      // ESC key handler to close modals
      $(document).on('keydown', function(e) {
        if (e.keyCode === 27) { // ESC key
          readyLaunch.modals.hideAll();
        }
      });

      // Handle checkbox changes within modals to show/hide related sections
      $('body').on('change', '.mepr_modal input[type="checkbox"]', function() {
        readyLaunch.handleModalCheckboxChange($(this));
      });
    },

    handleModalCheckboxChange: function ($checkbox) {
      var $modal = $checkbox.closest('.mepr_modal');
      var checkboxName = $checkbox.attr('name');
      var isChecked = $checkbox.is(':checked');

      // Find all conditional sections that are controlled by this checkbox
      var $controlledSections = $modal.find('.mepr-conditional-section[data-controlled-by="' + checkboxName + '"]');

      if ($controlledSections.length) {
        if (isChecked) {
          $controlledSections.show();
        } else {
          $controlledSections.hide();
        }
      }
    },

    // Template functionality
    initTemplateHandlers: function () {
      // Template enabler checkbox handlers
      $('.mepr-template-enablers').on('change', function() {
        readyLaunch.handleTemplateEnablerChange($(this));
      });

      // Customize button click handlers
      $('body').on('click', '.mepr-customize-btn', function(e) {
        e.preventDefault();
        readyLaunch.handleCustomizeButtonClick($(this));
      });
    },

    handleTemplateEnablerChange: function ($checkbox) {
      var $row = $checkbox.closest('tr');
      var $customizeCell = $row.find('.mepr-customize-cell');

      if ($checkbox.is(':checked')) {
        $customizeCell.addClass('show').show();
      } else {
        $customizeCell.removeClass('show').hide();
      }
    },

    handleCustomizeButtonClick: function ($button) {
      var modalTarget = readyLaunch.getModalTarget($button);

      if (modalTarget) {
        readyLaunch.modals.show(modalTarget);
      }
    },

    // Image handling functionality
    initImageHandlers: function () {
      // Logo upload button handler
      $('body').on('click', '#mepr-design-logo-btn', function (e) {
        e.preventDefault();
        readyLaunch.handleLogoUpload($(this));
      });

      // Logo remove button handler
      $('body').on('click', '[data-remove-target]', function(e) {
        e.preventDefault();
        readyLaunch.handleImageRemove($(this));
      });

      // Welcome image remove button handlers
      $('body').on('click', '.mepr-pluploader-preview .link', function(e) {
        e.preventDefault();
        readyLaunch.handleWelcomeImageRemove($(this));
      });
    },

    handleLogoUpload: function ($button) {
      if (!wp || !wp.media) {
        return;
      }

      var custom_uploader = wp.media({
        title: 'Insert image',
        library: {
          type: 'image'
        },
        button: {
          text: 'Use this image'
        },
        multiple: false
      }).on('select', function () {
        var attachment = custom_uploader.state().get('selection').first().toJSON();

        $('#mepr-design-logo').attr('src', attachment.url);
        $('#mepr-design-logo-id').attr('value', attachment.id);
        $('#mepr-design-logo-id')[0].dispatchEvent(new CustomEvent('input'));
      }).open();
    },

    handleImageRemove: function ($removeBtn) {
      var targetId = $removeBtn.data('remove-target');
      var $target = $('#' + targetId);
      var $input = $target.siblings('[data-image-input]');

      // Hide image and clear input
      $target.hide();
      $input.val('');
      $removeBtn.hide();
    },

    handleWelcomeImageRemove: function ($link) {
      var $preview = $link.closest('.mepr-pluploader-preview');
      var $wrapper = $link.closest('.mepr-pluploader-wrapper');
      var $hiddenInput = $wrapper.find('input[type="hidden"]');

      // Hide preview and clear input value
      $preview.hide();
      $hiddenInput.val('');

      // Show uploader if welcome image is enabled
      var $uploader = $wrapper.find('.upload-ui');
      if ($uploader.length) {
        $uploader.show();
      }
    },

    // Initialize states for UI elements
    initializeStates: function () {
      readyLaunch.initializeTemplateStates();
      readyLaunch.initializeModalStates();
      readyLaunch.initializeImageStates();
    },

    initializeTemplateStates: function () {
      $('.mepr-template-enablers').each(function() {
        var $checkbox = $(this);
        var $row = $checkbox.closest('tr');
        var $customizeCell = $row.find('.mepr-customize-cell');
        var templateType = $row.data('template');

        if ($checkbox.is(':checked')) {
          $customizeCell.addClass('show').show();
        } else {
          $customizeCell.removeClass('show').hide();
        }

        // Add template type as data attribute for debugging
        $customizeCell.attr('data-template', templateType);
      });
    },

    initializeModalStates: function () {
      $('.mepr_modal input[type="checkbox"]').each(function() {
        var $checkbox = $(this);
        var $modal = $checkbox.closest('.mepr_modal');
        var checkboxName = $checkbox.attr('name');
        var isChecked = $checkbox.is(':checked');

        // Handle conditional sections controlled by this checkbox
        if (checkboxName) {
          var $controlledSections = $modal.find('.mepr-conditional-section[data-controlled-by="' + checkboxName + '"]');

          if ($controlledSections.length && !isChecked) {
            $controlledSections.hide();
          }
        }
      });
    },

    initializeImageStates: function () {
      $('.mepr-pluploader-wrapper').each(function() {
        var $wrapper = $(this);
        var $hiddenInput = $wrapper.find('input[type="hidden"]');
        var $preview = $wrapper.find('.mepr-pluploader-preview');
        var $uploader = $wrapper.find('.upload-ui');

        if ($hiddenInput.val() > 0) {
          // Image exists - show preview, hide uploader
          $preview.show();
          $uploader.hide();
        } else {
          // No image - hide preview, show uploader if parent checkbox is checked
          $preview.hide();
          if ($wrapper.is(':visible')) {
            $uploader.show();
          }
        }
      });
    },

    // Utility functions
    getTemplateType: function($element) {
      var $row = $element.closest('tr[data-template]');
      return $row.length ? $row.data('template') : null;
    },

    getModalTarget: function($element) {
      return $element.data('modal-target') || $element.find('[data-modal-target]').first().data('modal-target');
    }
  };

  // Initialize when document is ready
  $(document).ready(function () {
    readyLaunch.initialize();
  });

  // Return the readyLaunch object for external access if needed
  return readyLaunch;

})(jQuery);