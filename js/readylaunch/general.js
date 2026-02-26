document.addEventListener('DOMContentLoaded', function () {
  // Profile menu functionality
  var userMenuButton = document.getElementById('user-menu-button');
  var profileMenuDropdown = document.querySelector('.profile-menu__dropdown');
  var mobileMenuButton = document.querySelector('.profile-menu__button.--is-mobile');
  var meprAccountNav = document.getElementById('mepr-account-nav');

  function toggleProfileDropdown() {
    if (profileMenuDropdown) {
      profileMenuDropdown.classList.toggle('is-open');
    }
  }

  function closeProfileDropdown() {
    if (profileMenuDropdown) {
      profileMenuDropdown.classList.remove('is-open');
    }
  }

  if (userMenuButton && profileMenuDropdown) {
    userMenuButton.addEventListener('click', toggleProfileDropdown);

    document.addEventListener('click', function (event) {
      var isFromMenu =
        userMenuButton.contains(event.target) ||
        profileMenuDropdown.contains(event.target) ||
        (mobileMenuButton && mobileMenuButton.contains(event.target));
      if (!isFromMenu) {
        closeProfileDropdown();
      }
    });
  }

  if (mobileMenuButton) {
    mobileMenuButton.addEventListener('click', function () {
      if (meprAccountNav) {
        meprAccountNav.classList.toggle('open');
      } else {
        toggleProfileDropdown();
      }
    });
  }
});
