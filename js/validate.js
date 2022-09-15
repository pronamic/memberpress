/** Some basic methods to validate form elements */
var mpValidateEmail = function (email) {
  //In case the email is not entered yet and is not required
  if(!email || 0 === email.length) {
    return true;
  }

  // https://stackoverflow.com/a/46181/6114835
  // var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
  var re = /^(([^<>()\[\]\.,;:\s@\"]+(\.[^<>()\[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i; //Accepts unicode???
  return re.test(email);

  //OLD, but doesn't work super well
  // var filter = /^[A-Z0-9._'%+-]+@[A-Z0-9.-]+\.[A-Z]{2,25}$/i;
  // return filter.test(email);
};

var mpValidateUrl = function(url) {
  var re = /(https?:\/\/)?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#()?&//=]*)/;
  return re.test(url);
};

var mpValidateNotBlank = function(val) {
  return (val && val.length > 0);
};

var mpValidateFieldNotBlank = function($field) {
  var notBlank = true;

  if(!$field.is(':visible')) {
    // Pass validation on fields that are not visible
  }
  else if ($field.is('input') || $field.is('select') || $field.is('textarea')) {
    notBlank = mpValidateNotBlank($field.val());
  }
  else if ($field.hasClass('mepr-checkbox-field')) {
    notBlank = $field.find('input').is(':checked');
  }
  else if ($field.hasClass('mepr-radios-field') || $field.hasClass('mepr-checkboxes-field')) {
    var input_vals = [];

    $field.find('input:checked').each(function (i, obj) {
      input_vals.push(true);
    });

    notBlank = mpValidateNotBlank(input_vals);
  }

  return notBlank;
};

var mpToggleFieldValidation = function(field, valid) {
  field.toggleClass('invalid', !valid);
  field.toggleClass('valid', valid);

  if(field.hasClass('mepr-password-confirm') || field.hasClass('mepr-password')) {
    field.parent().prev('.mp-form-label').find('.cc-error').toggle(!valid);
  } else {
    field.prev('.mp-form-label').find('.cc-error').toggle(!valid);
  }

  if(field.hasClass('mepr-countries-dropdown')) {
    field.closest('.mepr-form').find('.mepr_mepr-address-state .cc-error').toggle(!valid);
  }

  field.triggerHandler('mepr-validate-field', valid);
  var form = field.closest('.mepr-form');

  if (0 < form.find('.invalid').length) {
    // Toggle CSS directly so we can ensure 'block' display
    form.find('.mepr-form-has-errors').css('display','block');
  } else {
    form.find('.mepr-form-has-errors').css('display','none');
  }
};
