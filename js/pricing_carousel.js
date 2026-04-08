(function () {
  'use strict';

  function initPricingCarousels() {
    var containers = document.querySelectorAll('.mepr-pricing-carousel');

    containers.forEach(function (el) {
      var parent = el.closest('.mepr-price-menu');
      if (!parent) return;

      var carouselEnabled = parent.dataset.carouselEnabled === '1';
      var slides = el.querySelectorAll('.splide__slide');
      var slideCount = slides.length;

      if (!carouselEnabled || slideCount === 0 || slideCount === 1) {
        parent.classList.add('mepr-no-carousel');
        return;
      }

      // Determine if Splide is available
      if (typeof Splide === 'undefined') {
        parent.classList.add('mepr-no-carousel');
        return;
      }

      // Calculate how many cards fit based on container width and minimum card width
      var minCardWidth = 260;
      var gapPx = 24; // 1.5rem approx
      var containerWidth = el.offsetWidth || 800;
      var fitCount = Math.max(1, Math.floor((containerWidth + gapPx) / (minCardWidth + gapPx)));
      var desktopPerPage = Math.min(slideCount, fitCount);

      var splide = new Splide(el, {
        type: 'slide',
        rewind: true,
        perPage: desktopPerPage,
        gap: '1.5rem',
        padding: { left: 0, right: 0 },
        pagination: slideCount > desktopPerPage,
        arrows: slideCount > desktopPerPage,
        drag: slideCount > desktopPerPage,
        breakpoints: {
          1024: {
            perPage: Math.min(slideCount, 2),
            pagination: slideCount > 2,
            arrows: slideCount > 2,
            drag: slideCount > 2,
            gap: '1rem'
          },
          768: {
            perPage: 1,
            pagination: slideCount > 1,
            arrows: slideCount > 1,
            drag: slideCount > 1,
            gap: '0.75rem',
            padding: { left: '1rem', right: '1rem' }
          }
        }
      });

      // Start on the highlighted slide on mobile
      var highlightedIndex = -1;
      slides.forEach(function (slide, i) {
        var box = slide.querySelector('.mepr-price-box');
        if (box && box.classList.contains('highlighted')) {
          highlightedIndex = i;
        }
      });

      splide.on('mounted', function () {
        if (highlightedIndex > -1 && window.innerWidth <= 768) {
          splide.go(highlightedIndex);
        }
      });

      splide.mount();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPricingCarousels);
  } else {
    initPricingCarousels();
  }
})();
