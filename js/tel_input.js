var telInputs = document.querySelectorAll(".mepr-tel-input");
telInputs.forEach(input => {
  var iti = window.intlTelInput(input, {
    separateDialCode: true,
    initialCountry: meprTel.defaultCountry,
    utilsScript: meprTel.utilsUrl,
    onlyCountries: meprTel.onlyCountries ? meprTel.onlyCountries : [],
  });

  adjustPadding(input); // On load
  input.addEventListener('countrychange', () => adjustPadding(input)); // On country change
  input.addEventListener('keyup', () => adjustPadding(input)); // To fix padding conflict with WPForms
});

function adjustPadding(input) {
  var flagContainer = input.parentElement.querySelector('.iti__flag-container'),
      flagWidth = window.getComputedStyle(flagContainer).getPropertyValue('width'),
      adjustedWidth = `${parseInt(flagWidth) + 10}px`;

  // Check if body has RTL class
  if (document.body.classList.contains('rtl')) {
    input.style.setProperty('padding-left', '10px', 'important');
    input.style.setProperty('padding-right', adjustedWidth, 'important');
  } else {
    input.style.setProperty('padding-left', adjustedWidth, 'important');
  }
}

// Handle phone field padding on ReadyLaunch Account
document.querySelectorAll('.mepr-profile-details__button').forEach(button => {
  button.addEventListener('click', () => {
    setTimeout(() => {
      telInputs.forEach(adjustPadding);
    }, 100); // Delay until open modal
  });
});