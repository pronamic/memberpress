/**
 * Utility API wrapper for file uploads using plupload. 'plupload' is included in WP.
 * When enqueueing, please make sure 'plupload' is added as a dependency
 */
(function ($) {

  $.MeprPlUploader = function (args, settings) {
    var $element = $(args.id);

    var defaultArgs = {
      element: $element,
      input: 'input' + args.id,
      container: $element.find('.upload-ui').get(0),
      browse_button: $element.find('.button.browse-button').get(0),
      drop_element: $element.find('.drag-drop-area').get(0),
      loader: $element.find('.mepr_loader'),
      preview: $element.find('.mepr-pluploader-preview')
    }

    // Merge all args into one
    $.extend(this, args, defaultArgs, settings);
  };


  $.MeprPlUploader.prototype = {
    init: function () {

      this.uploader = new plupload.Uploader(this);
      var $this = this; // when you need to reference the original 'this' inside an event handler function
      // checks if browser supports drag and drop upload, makes some css adjustments if necessary
      this.uploader.bind('Init', function (up) {
        var element = $($this.container);
        if (up.features.dragdrop) {
          element.addClass('drag-drop');
          element.find('.drag-drop-area')
            .bind('dragover.wp-uploader', function () { element.addClass('drag-over'); })
            .bind('dragleave.wp-uploader, drop.wp-uploader', function () { element.removeClass('drag-over'); });
        } else {
          element.removeClass('drag-drop');
          element.find('.drag-drop-area').unbind('.wp-uploader');
        }
      });

      this.uploader.bind("postinit", function (up) {
        up.refresh();
      });

      this.uploader.init();

      this.added();
      this.uploaded();
    },
    added: function () {
      var $callback = this.onFilesAdded;
      var $this = this;

      this.uploader.bind('FilesAdded', function (up, files) {

        var hundredmb = 100 * 1024 * 1024, max = parseInt(up.settings.max_file_size, 10);

        plupload.each(files, function (file) {
          if (max > hundredmb && file.size > hundredmb && up.runtime != 'html5') {
            // file size error?
            console.log('Error uploading file')
          }
        });
        up.refresh();
        up.start();

        $this.loader.show();
        $($this.container).find('.drag-drop-buttons').hide();

        // Do additional stuff
        if (typeof $callback === 'function') $callback(files);
      });
    },
    uploaded: function () { // a file was uploaded
      var $hiddenInput = $(this.input);
      var $this = this;

      this.uploader.bind('FileUploaded', function (up, file, response) {
        let r = $.parseJSON(response.response);

        // Store attachment ID inside hidden input
        $hiddenInput.val(r.data.id);
        // $hiddenInput.trigger('input');
        $hiddenInput[0].dispatchEvent(new CustomEvent('input'));

        $this.loader.hide();
        $($this.container).find('.drag-drop-buttons').show();

        // Add URL to html src of the img tag
        $this.element.find('.mepr-pluploader-preview img').attr("src", r.data.url);

        // Do additional stuff
        if (typeof $this.onFileUploaded === 'function') $this.onFileUploaded(r);
      });
    },
    complete: function () { // all files successfully uploaded
      var $callback = this.onUploadComplete;
      this.uploader.bind('UploadComplete', function (up, file) {

        // Do additional stuff
        if (typeof $callback === 'function') $callback(r);
      });
    }
  }
})(jQuery);