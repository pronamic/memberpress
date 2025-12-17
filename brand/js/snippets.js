/**
 * MemberPress Snippets Tab JavaScript
 *
 * Handles the snippets library functionality including:
 * - WP Code plugin installation
 * - Snippet search and filtering
 * - Pagination
 * - Preview modal
 * - Code copying
 */

jQuery(function ($) {
  // WP Code installation button handler
  const wpcodePopupButton = document.querySelector('.mepr-wpcode-popup-button');
  if (wpcodePopupButton) {
    wpcodePopupButton.addEventListener('click', async function(e) {
      e.preventDefault();

      const button = e.target;
      const action = button.getAttribute('data-action');
      const plugin = button.getAttribute('data-plugin');
      const originalText = button.textContent;

      // Show loading state
      button.classList.add('loading');
      button.disabled = true;

      if (action === 'install') {
        button.textContent = MeprWpCode.installing_text || 'Installing...';
      } else if (action === 'update') {
        button.textContent = MeprWpCode.updating_text || 'Updating...';
      } else if (action === 'activate') {
        button.textContent = MeprWpCode.activating_text || 'Activating...';
      }

      try {
        // Make AJAX request
        const formData = new FormData();
        formData.append('action', 'mepr_wpcode_action');
        formData.append('wpcode_action', action);
        formData.append('plugin', plugin);
        formData.append('_ajax_nonce', MeprWpCode.nonce);

        const response = await fetch(MeprWpCode.ajax_url, {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          // Reload page to show snippets without blur
          window.location.reload();
        } else {
          alert(data.data || MeprWpCode.error_occurred);
          button.classList.remove('loading');
          button.disabled = false;
          button.textContent = originalText;
        }
      } catch (error) {
        console.error('WPCode installation error:', error);
        alert(MeprWpCode.action_failed.replace('%s', action));
        button.classList.remove('loading');
        button.disabled = false;
        button.textContent = originalText;
      }
    });
  }

  // Snippets Tab Functionality
  const snippetsContainer = document.getElementById('mepr-snippets-container');

  if (!snippetsContainer) {
    return;
  }

  const snippetsSearch = document.getElementById('mepr-snippets-search');
  const categoryFilter = document.getElementById('mepr-snippets-category-filter');
  const difficultyFilter = document.getElementById('mepr-snippets-difficulty-filter');
  const snippetCards = document.querySelectorAll('.mepr-snippet-card');
  const emptyMessage = document.querySelector('.mepr-snippets-empty');

  // Pagination state and elements
  const paginationContainer = document.querySelector('.mepr-snippets-pagination');
  let currentPage = 1;
  const perPage = 24;
  let filteredCards = [];

  const paginationStart = document.getElementById('mepr-pagination-start');
  const paginationEnd = document.getElementById('mepr-pagination-end');
  const paginationTotal = document.getElementById('mepr-pagination-total');
  const paginationCurrent = document.getElementById('mepr-pagination-current');
  const paginationTotalPages = document.getElementById('mepr-pagination-total-pages');
  const paginationFirst = document.querySelector('.mepr-pagination-first');
  const paginationPrev = document.querySelector('.mepr-pagination-prev');
  const paginationNext = document.querySelector('.mepr-pagination-next');
  const paginationLast = document.querySelector('.mepr-pagination-last');

  // Bind filter events (now includes pagination)
  snippetsSearch.addEventListener('keyup', filterWithPagination);
  snippetsSearch.addEventListener('input', filterWithPagination);
  categoryFilter.addEventListener('change', filterWithPagination);
  difficultyFilter.addEventListener('change', filterWithPagination);

  // Filter with pagination wrapper
  function filterWithPagination() {
    currentPage = 1; // Reset to first page
    applyFiltersAndPaginate();
  }

  // Apply filters and paginate
  function applyFiltersAndPaginate() {
    const searchTerm = snippetsSearch.value.toLowerCase();
    const categoryValue = categoryFilter.value;
    const difficultyValue = difficultyFilter.value;

    // Filter cards
    filteredCards = [];
    snippetCards.forEach(function(card) {
      const title = card.querySelector('.mepr-snippet-title').textContent.toLowerCase();
      const description = card.querySelector('.mepr-snippet-description').textContent.toLowerCase();
      const category = card.getAttribute('data-category');
      const difficulty = card.getAttribute('data-difficulty');

      const matchesSearch = !searchTerm || title.includes(searchTerm) || description.includes(searchTerm);
      const matchesCategory = !categoryValue || category === categoryValue;
      const matchesDifficulty = !difficultyValue || difficulty === difficultyValue;

      if (matchesSearch && matchesCategory && matchesDifficulty) {
        filteredCards.push(card);
      }
    });

    updatePaginationDisplay();
  }

  // Update pagination and display current page
  function updatePaginationDisplay(shouldScrollAndFocus = false) {
    const totalFiltered = filteredCards.length;

    // Hide all cards first
    snippetCards.forEach(function(card) {
      card.style.display = 'none';
    });

    // Show/hide empty message
    if (totalFiltered === 0) {
      snippetsContainer.style.display = 'none';
      emptyMessage.style.display = 'block';
      if (paginationContainer) paginationContainer.style.display = 'none';
      return;
    } else {
      snippetsContainer.style.display = 'grid';
      emptyMessage.style.display = 'none';
    }

    // Calculate pagination
    const totalPages = Math.ceil(totalFiltered / perPage);
    const startIndex = (currentPage - 1) * perPage;
    const endIndex = Math.min(startIndex + perPage, totalFiltered);

    // Show cards for current page
    for (let i = startIndex; i < endIndex; i++) {
      filteredCards[i].style.display = '';
    }

    // Update pagination UI
    if (totalFiltered > perPage && paginationContainer) {
      paginationContainer.style.display = 'flex';

      if (paginationStart) paginationStart.textContent = startIndex + 1;
      if (paginationEnd) paginationEnd.textContent = endIndex;
      if (paginationTotal) paginationTotal.textContent = totalFiltered;
      if (paginationCurrent) paginationCurrent.textContent = currentPage;
      if (paginationTotalPages) paginationTotalPages.textContent = totalPages;

      // Update button states
      if (paginationFirst) paginationFirst.disabled = currentPage === 1;
      if (paginationPrev) paginationPrev.disabled = currentPage === 1;
      if (paginationNext) paginationNext.disabled = currentPage === totalPages;
      if (paginationLast) paginationLast.disabled = currentPage === totalPages;
    } else if (paginationContainer) {
      paginationContainer.style.display = 'none';
    }

    // Scroll to top and focus for accessibility after page navigation
    if (shouldScrollAndFocus) {
      const snippetsWrapper = document.getElementById('mepr-snippets-wrapper');
      if (snippetsWrapper) {
        snippetsWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Set focus to the container for screen readers
        snippetsContainer.setAttribute('tabindex', '-1');
        snippetsContainer.focus();

        // Remove tabindex after focus (so it doesn't stay in tab order)
        setTimeout(function() {
          snippetsContainer.removeAttribute('tabindex');
        }, 100);
      }
    }
  }

  // Pagination button handlers
  if (paginationFirst) {
    paginationFirst.addEventListener('click', function() {
      currentPage = 1;
      updatePaginationDisplay(true);
    });
  }

  if (paginationPrev) {
    paginationPrev.addEventListener('click', function() {
      if (currentPage > 1) {
        currentPage--;
        updatePaginationDisplay(true);
      }
    });
  }

  if (paginationNext) {
    paginationNext.addEventListener('click', function() {
      const totalPages = Math.ceil(filteredCards.length / perPage);
      if (currentPage < totalPages) {
        currentPage++;
        updatePaginationDisplay(true);
      }
    });
  }

  if (paginationLast) {
    paginationLast.addEventListener('click', function() {
      const totalPages = Math.ceil(filteredCards.length / perPage);
      currentPage = totalPages;
      updatePaginationDisplay(true);
    });
  }

  // Initialize on page load
  applyFiltersAndPaginate();

  // Preview modal functionality
  const modal = document.getElementById('mepr-snippet-preview-modal');
  const modalTitle = document.getElementById('mepr-modal-snippet-title');
  const modalDescription = document.getElementById('mepr-modal-snippet-description');
  const modalDifficulty = document.getElementById('mepr-modal-snippet-difficulty');
  const modalCategory = document.getElementById('mepr-modal-snippet-category');
  const modalCode = document.querySelector('#mepr-modal-snippet-code code');
  const modalUseButton = document.querySelector('.mepr-modal-use-snippet');
  const modalCloseButton = document.querySelector('.mepr-modal-close');
  let lastFocusedElement = null;

  // Open preview modal
  document.addEventListener('click', function(e) {
    if (e.target.matches('.mepr-snippet-preview') || e.target.closest('.mepr-snippet-preview')) {
      e.preventDefault();
      const button = e.target.matches('.mepr-snippet-preview') ? e.target : e.target.closest('.mepr-snippet-preview');

      // Get the parent card to access all data
      const card = button.closest('.mepr-snippet-card');
      if (!card) return;

      // Get data from button attributes and card
      const snippetId = button.getAttribute('data-snippet-id');
      const title = button.getAttribute('data-snippet-title');
      const description = button.getAttribute('data-snippet-description');
      const category = button.getAttribute('data-snippet-category');
      const difficulty = button.getAttribute('data-snippet-difficulty');
      const url = button.getAttribute('data-snippet-url');
      const isInstalled = button.getAttribute('data-snippet-installed') === '1';
      const code = card.getAttribute('data-snippet-code') || '// Code preview not available';

      // Store the currently focused element
      lastFocusedElement = document.activeElement;

      // Populate modal
      modalTitle.textContent = title;
      modalDescription.innerHTML = description;
      modalDifficulty.textContent = difficulty.charAt(0).toUpperCase() + difficulty.slice(1);
      modalDifficulty.className = 'mepr-snippet-difficulty mepr-snippet-difficulty-' + difficulty;

      // Format category for display
      const categoryLabel = category.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
      modalCategory.textContent = categoryLabel;

      // Populate code preview
      if (modalCode) {
        // Clear any existing content and classes
        modalCode.textContent = '';
        modalCode.className = '';
        modalCode.removeAttribute('data-highlighted');

        // Ensure PHP code has opening tag for proper highlighting
        let codeToHighlight = code;
        if (code && !code.trim().startsWith('<?php') && !code.trim().startsWith('<?=')) {
          // Add opening tag for highlighting purposes
          codeToHighlight = '<?php\n' + code;
        }

        // Set the code content
        modalCode.textContent = codeToHighlight;

        // Apply syntax highlighting with Prism
        if (typeof Prism !== 'undefined') {
          // Set language class for PHP
          modalCode.className = 'language-php';
          // Apply Prism highlighting
          Prism.highlightElement(modalCode);
        }
      }

      // Show/hide installed badge
      const installedBadge = document.getElementById('mepr-modal-snippet-installed');
      if (installedBadge) {
        installedBadge.style.display = isInstalled ? 'inline-block' : 'none';
      }

      // Set snippet URL for Use Snippet button
      modalUseButton.setAttribute('data-snippet-url', url);
      modalUseButton.setAttribute('data-snippet-id', snippetId);

      // Show modal with animation
      modal.style.display = 'block';
      // Trigger reflow to enable transition
      modal.offsetHeight;
      modal.classList.add('mepr-modal-open');
      document.body.classList.add('modal-open');

      // Focus the close button for accessibility
      setTimeout(function() {
        if (modalCloseButton) {
          modalCloseButton.focus();
        }
      }, 100);

      // Trap focus within modal
      trapFocus(modal);
    }
  });

  // Close modal
  function closeModal() {
    modal.classList.remove('mepr-modal-open');
    document.body.classList.remove('modal-open');

    // Wait for animation to complete before hiding
    setTimeout(function() {
      modal.style.display = 'none';

      // Return focus to the element that opened the modal
      if (lastFocusedElement && lastFocusedElement.focus) {
        lastFocusedElement.focus();
      }
    }, 300);
  }

  // Trap focus within modal for accessibility
  function trapFocus(element) {
    const focusableElements = element.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];

    element.addEventListener('keydown', function(e) {
      if (e.key !== 'Tab') {
        return;
      }

      if (e.shiftKey) {
        // Shift + Tab
        if (document.activeElement === firstFocusable) {
          e.preventDefault();
          lastFocusable.focus();
        }
      } else {
        // Tab
        if (document.activeElement === lastFocusable) {
          e.preventDefault();
          firstFocusable.focus();
        }
      }
    });
  }

  const modalCloseElements = document.querySelectorAll('.mepr-modal-close, .mepr-modal-cancel, .mepr-modal-overlay');
  modalCloseElements.forEach(function(element) {
    element.addEventListener('click', closeModal);
  });

  // Close modal on ESC key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.classList.contains('mepr-modal-open')) {
      closeModal();
    }
  });

  // Use Snippet button functionality
  document.addEventListener('click', function(e) {
    if (e.target.matches('.mepr-snippet-use, .mepr-modal-use-snippet') || e.target.closest('.mepr-snippet-use, .mepr-modal-use-snippet')) {
      e.preventDefault();
      const button = e.target.matches('.mepr-snippet-use, .mepr-modal-use-snippet') ? e.target : e.target.closest('.mepr-snippet-use, .mepr-modal-use-snippet');
      const snippetUrl = button.getAttribute('data-snippet-url');

      // Close modal if open
      if (modal.classList.contains('mepr-modal-open')) {
        closeModal();
      }

      // Open WP Code library URL in new tab
      if (snippetUrl) {
        window.open(snippetUrl, '_blank', 'noopener,noreferrer');
      }
    }
  });

  // Copy code functionality
  const copyButton = document.querySelector('.mepr-copy-code');
  if (copyButton) {
    copyButton.addEventListener('click', async function() {
      const code = modalCode.textContent;

      // Use modern clipboard API if available
      if (navigator.clipboard && navigator.clipboard.writeText) {
        try {
          await navigator.clipboard.writeText(code);
          // Visual feedback
          const originalHTML = copyButton.innerHTML;
          copyButton.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + MeprWpCode.copied;
          setTimeout(function() {
            copyButton.innerHTML = originalHTML;
          }, 2000);
        } catch (error) {
          // Fallback to textarea method
          copyCodeFallback(code);
        }
      } else {
        copyCodeFallback(code);
      }

      function copyCodeFallback(text) {
        const temp = document.createElement('textarea');
        temp.value = text;
        temp.style.position = 'fixed';
        temp.style.left = '-9999px';
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);

        // Visual feedback
        const originalHTML = copyButton.innerHTML;
        copyButton.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + MeprWpCode.copied;
        setTimeout(function() {
          copyButton.innerHTML = originalHTML;
        }, 2000);
      }
    });
  }
});
