(function ($) {

  var maxHeight = function (elems) {
    return Math.max.apply(null, elems.map(function () {
      return $(this).outerHeight();
    }).get());
  }

  $(".mepr-price-box-title").css({ "height": maxHeight($(".mepr-price-box-title")) + "px" });
  $(".mepr-price-box-price").css({ "height": maxHeight($(".mepr-price-box-price")) + "px" });


})(jQuery);
