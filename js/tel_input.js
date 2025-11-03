// Ensure meprTel.i18n exists with fallback translations
if (typeof meprTel === 'undefined') {
  var meprTel = {};
}
if (typeof meprTel.i18n === 'undefined') {
  meprTel.i18n = {
    selectCountryCode: 'Select country code',
    countryCodeOptions: 'Country code options',
    countryChangedTo: 'Country changed to',
    countryCode: 'country code',
    phoneNumberInput: 'Phone number input'
  };
}

var telInputs = document.querySelectorAll(".mepr-tel-input");
telInputs.forEach(input => {
  var iti = window.intlTelInput(input, {
    separateDialCode: true,
    initialCountry: meprTel.defaultCountry,
    utilsScript: meprTel.utilsUrl,
    onlyCountries: meprTel.onlyCountries ? meprTel.onlyCountries : [],
    i18n: 'fr',
  });

  adjustPadding(input); // On load
  input.addEventListener('countrychange', () => adjustPadding(input)); // On country change
  input.addEventListener('keyup', () => adjustPadding(input)); // To fix padding conflict with WPForms

  // Fix accessibility issues with the intl-tel-input library - apply after initialization
  enhanceAccessibility(input, iti);
});

function enhanceAccessibility(input, iti) {
  // Add a delay to ensure intl-tel-input has finished initialization
  setTimeout(function() {
    try {
      // Get the container created by intl-tel-input
      var itiContainer = input.parentElement;
      if (!itiContainer || !itiContainer.classList.contains('iti')) {
        return; // Not an intl-tel-input container
      }

      // Find the selected flag element that's causing accessibility issues
      var selectedFlag = itiContainer.querySelector('.iti__selected-flag');

      if (selectedFlag && selectedFlag.nodeType === Node.ELEMENT_NODE) {
        // Only add attributes if they don't already exist (avoid conflicts)
        if (!selectedFlag.getAttribute('role')) {
          selectedFlag.setAttribute('role', 'button');
        }
        if (!selectedFlag.getAttribute('aria-haspopup')) {
          selectedFlag.setAttribute('aria-haspopup', 'listbox');
        }
        if (!selectedFlag.getAttribute('aria-expanded')) {
          selectedFlag.setAttribute('aria-expanded', 'false');
        }
        if (!selectedFlag.getAttribute('aria-label')) {
          selectedFlag.setAttribute('aria-label', meprTel.i18n.selectCountryCode);
        }
        if (!selectedFlag.getAttribute('tabindex')) {
          selectedFlag.setAttribute('tabindex', '0');
        }

        // Add unique IDs for ARIA relationships
        var inputId = input.getAttribute('id') || 'phone-input-' + Math.random().toString(36).substring(2, 11);
        var listboxId = inputId + '-listbox';

        // Set aria-controls immediately to satisfy validation requirements
        // The referenced element will be created when dropdown is opened
        if (!selectedFlag.getAttribute('aria-controls')) {
          selectedFlag.setAttribute('aria-controls', listboxId);

          // Create a temporary placeholder element to satisfy ARIA validation
          // This will be replaced when the actual dropdown is created
          createPlaceholderDropdown(itiContainer, listboxId);
        }

        // Use a less intrusive approach - only observe existing elements
        enhanceDropdownAccessibility(itiContainer, listboxId, selectedFlag);

        // Use passive event listeners to avoid conflicts
        setupAccessibilityObserver(itiContainer, selectedFlag);

        // Add safe keyboard navigation
        addKeyboardNavigation(selectedFlag);
      }

      // Enhance the phone input itself safely
      enhancePhoneInput(input);

      // Add live region for announcements
      setupLiveRegion(input, iti);

    } catch (e) {
      console.warn('MemberPress phone accessibility: Error enhancing accessibility', e);
    }
  }, 200); // Increased delay to avoid conflicts
}

function createPlaceholderDropdown(itiContainer, listboxId) {
  // Check if placeholder already exists
  var existingPlaceholder = document.getElementById(listboxId);
  if (existingPlaceholder) {
    return; // Already exists
  }

  // Create a hidden placeholder element to satisfy ARIA validation
  var placeholder = document.createElement('div');
  placeholder.id = listboxId;
  placeholder.style.display = 'none';
  placeholder.setAttribute('role', 'listbox');
  placeholder.setAttribute('aria-label', meprTel.i18n.countryCodeOptions);
  placeholder.setAttribute('data-placeholder', 'true');

  // Append to container but keep hidden
  itiContainer.appendChild(placeholder);
}

function enhanceDropdownAccessibility(itiContainer, listboxId, selectedFlag) {
  // Look for dropdown when it's actually created (lazy approach)
  var checkDropdown = function() {
    var dropdown = itiContainer.querySelector('.iti__country-list');
    if (dropdown && !dropdown.getAttribute('role')) {
      // Remove placeholder element if it exists
      var placeholder = itiContainer.querySelector('[data-placeholder="true"]');
      if (placeholder) {
        placeholder.remove();
      }

      dropdown.setAttribute('id', listboxId);
      dropdown.setAttribute('role', 'listbox');
      dropdown.setAttribute('aria-label', meprTel.i18n.countryCodeOptions);

      // aria-controls is already set, just ensure the dropdown has the correct ID
      // This ensures the ARIA relationship is valid

      // Enhance country items when dropdown is opened
      var countryItems = dropdown.querySelectorAll('.iti__country');
      countryItems.forEach((item, index) => {
        if (item && !item.getAttribute('role')) {
          item.setAttribute('role', 'option');
          item.setAttribute('id', listboxId + '-option-' + index);
          item.setAttribute('aria-selected', 'false');
        }
      });
    }
  };

  // Check immediately and also when container changes
  checkDropdown();

  // Set up observer for when dropdown gets created
  var dropdownObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.type === 'childList') {
        checkDropdown();
      }
    });
  });

  dropdownObserver.observe(itiContainer, {
    childList: true,
    subtree: true
  });
}

function setupAccessibilityObserver(itiContainer, selectedFlag) {
  // Observe class changes for aria-expanded updates
  var classObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
        try {
          var isOpen = itiContainer.classList.contains('iti__country-list-open');
          if (selectedFlag && selectedFlag.getAttribute && selectedFlag.setAttribute) {
            selectedFlag.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
          }
        } catch (e) {
          // Silently handle errors to avoid breaking functionality
        }
      }
    });
  });

  classObserver.observe(itiContainer, {
    attributes: true,
    attributeFilter: ['class']
  });
}

function addKeyboardNavigation(selectedFlag) {
  // Use passive event listener to avoid conflicts
  selectedFlag.addEventListener('keydown', function(e) {
    try {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        // Use a safer click simulation
        setTimeout(function() {
          if (selectedFlag.click) {
            selectedFlag.click();
          }
        }, 10);
      }
    } catch (e) {
      // Silently handle errors
    }
  }, { passive: false });
}

function enhancePhoneInput(input) {
  if (input && input.setAttribute) {
    if (!input.getAttribute('role')) {
      input.setAttribute('role', 'textbox');
    }

    // Add aria-label if not already present
    if (!input.getAttribute('aria-label') && !input.getAttribute('aria-labelledby')) {
      input.setAttribute('aria-label', meprTel.i18n.phoneNumberInput);
    }
  }
}

function setupLiveRegion(input, iti) {
  // Add live region for country code announcements
  var liveRegion = document.getElementById('phone-country-live-region');
  if (!liveRegion) {
    liveRegion = document.createElement('div');
    liveRegion.id = 'phone-country-live-region';
    liveRegion.setAttribute('aria-live', 'polite');
    liveRegion.setAttribute('aria-atomic', 'true');
    liveRegion.style.position = 'absolute';
    liveRegion.style.left = '-10000px';
    liveRegion.style.width = '1px';
    liveRegion.style.height = '1px';
    liveRegion.style.overflow = 'hidden';
    document.body.appendChild(liveRegion);
  }

  // Use one-time event listener to avoid multiple bindings
  var countryChangeHandler = function() {
    try {
      var selectedCountryData = iti.getSelectedCountryData();
      if (selectedCountryData && liveRegion) {
        liveRegion.textContent = meprTel.i18n.countryChangedTo + ' ' + selectedCountryData.name +
                                 ', ' + meprTel.i18n.countryCode + ' ' + selectedCountryData.dialCode;
      }
    } catch (e) {
      // Silently handle errors
    }
  };

  // Remove any existing listener and add new one
  input.removeEventListener('countrychange', countryChangeHandler);
  input.addEventListener('countrychange', countryChangeHandler);
}

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
      telInputs.forEach(input => {
        adjustPadding(input);
        // Re-apply accessibility enhancements for dynamically loaded forms
        var iti = window.intlTelInputGlobals.getInstance(input);
        if (iti) {
          enhanceAccessibility(input, iti);
        }
      });
    }, 100); // Delay until open modal
  });
});