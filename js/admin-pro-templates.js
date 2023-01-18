jQuery(document).ready(function ($) {

  new jQuery.MeprPlUploader({
    id: '#mepr-design-account-welcome-img',
  }, window.MeproTemplates).init();

  new jQuery.MeprPlUploader({
    id: '#mepr-design-login-welcome-img',
  }, window.MeproTemplates).init();

  new jQuery.MeprPlUploader({
    id: '#mepr-design-thankyou-welcome-img',
  }, window.MeproTemplates).init();

  new jQuery.MeprPlUploader({
    id: '#mepr-design-courses-logo',
  }, window.MeproTemplates).init();


  $('body').on('click', '#mepr-design-logo-btn', function (e) {
    e.preventDefault();

    var button = $(this),
      custom_uploader = wp.media({
        title: 'Insert image',
        library: {
          // uploadedTo : wp.media.view.settings.post.id, // attach to the current post?
          type: 'image'
        },
        button: {
          text: 'Use this image' // button label text
        },
        multiple: false
      }).on('select', function () {
        var attachment = custom_uploader.state().get('selection').first().toJSON();
        console.log(attachment);
        $('#mepr-design-logo').attr('src', attachment.url)
        $('#mepr-design-logo-id').attr('value', attachment.id)
        $('#mepr-design-logo-id')[0].dispatchEvent(new CustomEvent('input'));
        // button.html('<img src="' + attachment.url + '">').next().show().next().val(attachment.id);
      }).open();
  });

  var debounce = null;
  $('.color-field').wpColorPicker({
    change: function (event, ui) {
      clearTimeout(debounce);
      debounce = setTimeout(function () {
        event.target.dispatchEvent(new CustomEvent('input'));
      }, 100);
    },
  });
});

window.meprProTemplates = {
  global: {
    logoId: null,
    primaryColor: 'dsd'
  },
  login: { // default login values
    openModal: false,
    enableTemplate: false,
    showWelcomeImage: false,
    welcomeImageId: null
  },
  thankyou: { // default unauthorized values
    openModal: false,
    enableTemplate: false,
    showWelcomeImage: false,
    welcomeImageId: null
  },
  account: { // default account values
    openModal: false,
    enableTemplate: false,
    showWelcomeImage: false,
    welcomeImageId: null
  },
  courses: { // default courses values
    openModal: false,
    enableTemplate: false,
    showWelcomeImage: false,
    logoId: null
  },
  pricing: { // default courses values
    openModal: false,
    enableTemplate: false,
    showWelcomeImage: false,
    // logoId: null
  },
  checkout: { // default courses values
    enableTemplate: false,
  },
  init(data) {
    // console.log('yup');
  },
  mounted(data) {
    this.global = data.global;
    this.login = data.login;
    this.account = data.account;
    this.courses = data.courses;
    this.pricing = data.pricing;
    this.thankyou = data.thankyou;
    this.checkout = data.checkout;

    // console.log('value')
    this.$watch('login.enableTemplate', (value) => {
      if (true === value) this.login.openModal = true;
    })
    this.$watch('thankyou.enableTemplate', (value) => {
      if (true === value) this.thankyou.openModal = true;
    })
    this.$watch('account.enableTemplate', (value) => {
      if (true === value) this.account.openModal = true;
    })
    this.$watch('pricing.enableTemplate', (value) => {
      if (true === value) this.pricing.openModal = true;
    })
    this.$watch('courses.enableTemplate', (value) => {
      if (true === value) this.courses.openModal = true;
    })
  },
  closeModal($event, modal){
    if($event.target.className == 'mepr_modal__content'){
      modal.openModal=false
    }
  }
}


