jQuery(document).ready(function($) {
    let overlayDiv = $('.mepr-paywall-overlay');
    let containerDiv = $('.mepr-paywall-container');
    containerDiv.on('click',function(e){
        e.stopPropagation();
        if(!$(this).hasClass('scrolled')) {
            $(this).addClass('active');
        }
    });
    overlayDiv.on('click', function(){
       containerDiv.removeClass('active');
    });
    overlayDiv.on('scroll', function() {
        if($(this).scrollTop() > 0) {
           containerDiv.addClass('scrolled');
        } else {
           containerDiv.removeClass('scrolled');
        }
    });
})