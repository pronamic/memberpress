document.addEventListener('DOMContentLoaded', function () {
  // Profile menu functionality
  var userMenuButton = document.getElementById('user-menu-button');
  var profileMenuDropdown = document.querySelector('.profile-menu__dropdown');
  var mobileMenuButton = document.querySelector('.profile-menu__button.--is-mobile');
  var meprAccountNav = document.getElementById('mepr-account-nav');

  if (userMenuButton && profileMenuDropdown) {
    userMenuButton.addEventListener('click', function () {
      var isOpen = profileMenuDropdown.style.display === 'block';
      profileMenuDropdown.style.display = isOpen ? 'none' : 'block';
    });

    document.addEventListener('click', function (event) {
      if (!userMenuButton.contains(event.target) && !profileMenuDropdown.contains(event.target)) {
        profileMenuDropdown.style.display = 'none';
      }
    });
  }

  if (mobileMenuButton && meprAccountNav) {
    mobileMenuButton.addEventListener('click', function () {
      meprAccountNav.classList.toggle('open');
    });
  }
});
