<?php
/**
 * Snippets Tab View
 *
 * This template displays the WP Code snippets library integration.
 *
 * @var array  $snippets         Array of snippets from WP Code or placeholders
 * @var bool   $wpcode_required  Whether WP Code needs to be installed/activated
 * @var string $wpcode_action    Action needed: 'install', 'update', or 'activate'
 * @var string $wpcode_plugin    Plugin slug or download URL
 */

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

// Set up popup messaging based on action required.
$popup_title = esc_html__('Please Install WP Code to Use the MemberPress Snippet Library', 'memberpress');
$popup_button_text = esc_html__('Install + Activate WP Code', 'memberpress');

if (isset($wpcode_action)) {
    if ($wpcode_action === 'update') {
        $popup_title = esc_html__('Please Update WP Code to Use the MemberPress Snippet Library', 'memberpress');
        $popup_button_text = esc_html__('Update + Activate WP Code', 'memberpress');
    } elseif ($wpcode_action === 'activate') {
        $popup_title = esc_html__('Please Activate WP Code to Use the MemberPress Snippet Library', 'memberpress');
        $popup_button_text = esc_html__('Activate WP Code', 'memberpress');
    }
}

$container_class = isset($wpcode_required) && $wpcode_required ? 'mepr-wpcode-blur' : '';
?>

<div id="mepr-snippets-wrapper" class="mepr-wpcode">
  <?php if (isset($wpcode_required) && $wpcode_required) : ?>
    <div class="mepr-wpcode-popup">
      <div class="mepr-wpcode-popup-title"><?php echo esc_html($popup_title); ?></div>
      <div class="mepr-wpcode-popup-description">
        <?php esc_html_e('Using WP Code, you can install MemberPress code snippets with 1 click right from this page or the WP Code Library in the WordPress admin.', 'memberpress'); ?>
      </div>
      <button
        type="button"
        data-action="<?php echo esc_attr($wpcode_action ?? 'install'); ?>"
        data-plugin="<?php echo esc_attr($wpcode_plugin ?? ''); ?>"
        class="mepr-wpcode-popup-button button button-primary button-hero">
        <?php echo esc_html($popup_button_text); ?>
      </button>
      <a
        href="https://wordpress.org/plugins/insert-headers-and-footers/"
        target="_blank"
        rel="noopener noreferrer"
        class="mepr-wpcode-popup-link">
        <?php esc_html_e('Learn more about WP Code', 'memberpress'); ?>
      </a>
    </div>
  <?php endif; ?>

  <div class="mepr-snippets-container <?php echo sanitize_html_class($container_class); ?>">
    <div class="mepr-snippets-header">
      <h3><?php esc_html_e('Browse MemberPress Code Snippets', 'memberpress'); ?></h3>
      <p class="mepr-snippets-description">
        <?php
          printf(
            // Translators: %1$s: number of snippets, %2$s: open link tag, %3$s: close link tag.
              esc_html__('Enhance your membership site with %1$s+ tested MemberPress-specific code snippets. Powered by %2$sWP Code%3$s.', 'memberpress'),
              '200',
              '<a href="https://wpcode.com/" target="_blank" rel="noopener noreferrer">',
              '</a>'
          );
            ?>
      </p>
    </div>

    <div class="mepr-snippets-toolbar" role="search">
      <div class="mepr-snippets-search">
        <label for="mepr-snippets-search" class="screen-reader-text"><?php esc_html_e('Search snippets', 'memberpress'); ?></label>
        <input type="search" id="mepr-snippets-search" placeholder="<?php esc_attr_e('Search snippets...', 'memberpress'); ?>" aria-label="<?php esc_attr_e('Search snippets', 'memberpress'); ?>" />
      </div>
      <div class="mepr-snippets-filters">
        <label for="mepr-snippets-category-filter" class="screen-reader-text"><?php esc_html_e('Filter by category', 'memberpress'); ?></label>
        <select id="mepr-snippets-category-filter" class="mepr-snippets-filter" aria-label="<?php esc_attr_e('Filter by category', 'memberpress'); ?>">
          <option value=""><?php esc_html_e('All Categories', 'memberpress'); ?></option>
          <option value="checkout-payment"><?php esc_html_e('Checkout & Payment', 'memberpress'); ?></option>
          <option value="registration-login"><?php esc_html_e('Registration & Login', 'memberpress'); ?></option>
          <option value="content-protection"><?php esc_html_e('Content Protection', 'memberpress'); ?></option>
          <option value="analytics-tracking"><?php esc_html_e('Analytics & Tracking', 'memberpress'); ?></option>
          <option value="design-styling"><?php esc_html_e('Design & Styling', 'memberpress'); ?></option>
          <option value="automation-webhooks"><?php esc_html_e('Automation & Webhooks', 'memberpress'); ?></option>
          <option value="reports-data"><?php esc_html_e('Reports & Data', 'memberpress'); ?></option>
        </select>
        <label for="mepr-snippets-difficulty-filter" class="screen-reader-text"><?php esc_html_e('Filter by difficulty level', 'memberpress'); ?></label>
        <select id="mepr-snippets-difficulty-filter" class="mepr-snippets-filter" aria-label="<?php esc_attr_e('Filter by difficulty level', 'memberpress'); ?>">
          <option value=""><?php esc_html_e('All Levels', 'memberpress'); ?></option>
          <option value="beginner"><?php esc_html_e('Beginner', 'memberpress'); ?></option>
          <option value="intermediate"><?php esc_html_e('Intermediate', 'memberpress'); ?></option>
          <option value="advanced"><?php esc_html_e('Advanced', 'memberpress'); ?></option>
        </select>
      </div>
    </div>

    <div id="mepr-snippets-container" class="mepr-snippets-grid" role="region" aria-live="polite" aria-label="<?php esc_attr_e('Snippets results', 'memberpress'); ?>">
      <?php
        if (!empty($snippets)) :
            foreach ($snippets as $snippet) {
                // Convert to array if object.
                $snippet = (array) $snippet;

                // Extract data - handle both WPCode format and our placeholder format.
                $snippet_id = isset($snippet['id']) ? $snippet['id'] : uniqid('snippet_');
                $snippet_title = isset($snippet['title']) ? $snippet['title'] : '';
                $description = isset($snippet['note']) ? $snippet['note'] : '';
                $description_full = isset($snippet['note_full']) ? $snippet['note_full'] : $description;
                $category = isset($snippet['category']) ? $snippet['category'] : 'general';
                $difficulty = isset($snippet['difficulty']) ? $snippet['difficulty'] : 'intermediate';
                $is_installed = isset($snippet['installed']) && $snippet['installed'];
                $install_url = isset($snippet['install']) ? $snippet['install'] : '#';
                $code = isset($snippet['code']) ? $snippet['code'] : '';

                $difficulty_class = 'mepr-snippet-difficulty-' . esc_attr($difficulty);

                // Map category slug to display label (must match dropdown options).
                $category_labels = [
                    'checkout-payment' => __('Checkout & Payment', 'memberpress'),
                    'registration-login' => __('Registration & Login', 'memberpress'),
                    'content-protection' => __('Content Protection', 'memberpress'),
                    'analytics-tracking' => __('Analytics & Tracking', 'memberpress'),
                    'design-styling' => __('Design & Styling', 'memberpress'),
                    'automation-webhooks' => __('Automation & Webhooks', 'memberpress'),
                    'reports-data' => __('Reports & Data', 'memberpress'),
                    'general' => __('General', 'memberpress'),
                ];
                $category_label = isset($category_labels[$category]) ? $category_labels[$category] : ucwords(str_replace('-', ' ', $category));
                ?>
          <article
            class="mepr-snippet-card <?php echo $is_installed ? 'mepr-snippet-installed' : ''; ?>"
            data-category="<?php echo esc_attr($category); ?>"
            data-difficulty="<?php echo esc_attr($difficulty); ?>"
            data-snippet-id="<?php echo esc_attr($snippet_id); ?>"
            data-install-url="<?php echo esc_url($install_url); ?>"
            data-snippet-code="<?php echo esc_attr($code); ?>"
            aria-label="<?php echo esc_attr($snippet_title); ?>">
            <div class="mepr-snippet-card-inner">
              <div class="mepr-snippet-header">
                <h4 class="mepr-snippet-title"><?php echo esc_html($snippet_title); ?></h4>
                <span
                  class="mepr-snippet-difficulty <?php echo esc_attr($difficulty_class); ?>"
                  <?php // Translators: %s: difficulty level (e.g. Beginner, Intermediate, Advanced). ?>
                  aria-label="<?php echo esc_attr(sprintf(__('Difficulty: %s', 'memberpress'), ucfirst($difficulty))); ?>">
                  <?php echo esc_html(ucfirst($difficulty)); ?>
                </span>
              </div>
              <div class="mepr-snippet-body">
                <p class="mepr-snippet-description"><?php echo esc_html($description); ?></p>
                <div class="mepr-snippet-tags">
                  <?php if ($is_installed) : ?>
                    <span class="mepr-snippet-tag mepr-snippet-installed-badge">
                        <?php esc_html_e('Installed', 'memberpress'); ?>
                    </span>
                  <?php endif; ?>
                  <span
                    class="mepr-snippet-tag"
                    <?php // Translators: %s: category name. ?>
                    aria-label="<?php echo esc_attr(sprintf(__('Category: %s', 'memberpress'), $category_label)); ?>">
                    <?php echo esc_html($category_label); ?>
                  </span>
                </div>
              </div>
              <div class="mepr-snippet-actions">
                <button
                  type="button"
                  class="button mepr-snippet-preview"
                  data-snippet-id="<?php echo esc_attr($snippet_id); ?>"
                  data-snippet-title="<?php echo esc_attr($snippet_title); ?>"
                  data-snippet-description="<?php echo esc_attr($description_full); ?>"
                  data-snippet-category="<?php echo esc_attr($category); ?>"
                  data-snippet-difficulty="<?php echo esc_attr($difficulty); ?>"
                  data-snippet-url="<?php echo esc_url($install_url); ?>"
                  data-snippet-installed="<?php echo $is_installed ? '1' : '0'; ?>"
                  <?php // Translators: %s: snippet title. ?>
                  aria-label="<?php echo esc_attr(sprintf(__('Preview %s snippet', 'memberpress'), $snippet_title)); ?>">
                  <?php esc_html_e('Preview', 'memberpress'); ?>
                </button>
                <?php if ($is_installed) : ?>
                  <a
                    href="<?php echo esc_url($install_url); ?>"
                    class="button button-primary"
                    target="_blank"
                    rel="noopener noreferrer">
                    <?php esc_html_e('Edit Snippet', 'memberpress'); ?>
                  </a>
                <?php else : ?>
                  <button
                    type="button"
                    class="button button-primary mepr-snippet-use"
                    data-snippet-id="<?php echo esc_attr($snippet_id); ?>"
                    data-snippet-url="<?php echo esc_url($install_url); ?>"
                    <?php // Translators: %s: snippet title. ?>
                    aria-label="<?php echo esc_attr(sprintf(__('Use %s snippet', 'memberpress'), $snippet_title)); ?>">
                    <?php esc_html_e('Use Snippet', 'memberpress'); ?>
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </article>
            <?php } ?>
        <?php endif; ?>
    </div>

    <div class="mepr-snippets-empty" style="<?php echo empty($snippets) ? '' : 'display: none;'; ?>" role="status" aria-live="polite">
      <p><?php esc_html_e('No snippets found matching your search criteria.', 'memberpress'); ?></p>
    </div>

    <div class="mepr-snippets-loading" style="display: none;" role="status" aria-live="polite" aria-busy="true">
      <div class="mepr-spinner" aria-hidden="true"></div>
      <p><?php esc_html_e('Loading snippets...', 'memberpress'); ?></p>
    </div>

    <!-- Pagination -->
    <nav class="mepr-snippets-pagination" aria-label="<?php esc_attr_e('Snippets pagination', 'memberpress'); ?>" style="display: none;">
      <div class="mepr-pagination-info" role="status" aria-live="polite" aria-atomic="true">
        <span id="mepr-pagination-showing"><?php esc_html_e('Showing', 'memberpress'); ?> <span id="mepr-pagination-start">1</span>-<span id="mepr-pagination-end">12</span> <?php esc_html_e('of', 'memberpress'); ?> <span id="mepr-pagination-total">0</span></span>
      </div>
      <div class="mepr-pagination-controls">
        <button type="button" class="button mepr-pagination-first" aria-label="<?php esc_attr_e('First page', 'memberpress'); ?>" disabled>
          <span class="dashicons dashicons-controls-skipback" aria-hidden="true"></span>
        </button>
        <button type="button" class="button mepr-pagination-prev" aria-label="<?php esc_attr_e('Previous page', 'memberpress'); ?>" disabled>
          <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        </button>
        <span class="mepr-pagination-pages" aria-live="polite" aria-atomic="true">
                <?php esc_html_e('Page', 'memberpress'); ?> <span id="mepr-pagination-current" aria-current="page">1</span> <?php esc_html_e('of', 'memberpress'); ?> <span id="mepr-pagination-total-pages">1</span>
        </span>
        <button type="button" class="button mepr-pagination-next" aria-label="<?php esc_attr_e('Next page', 'memberpress'); ?>">
          <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
        </button>
        <button type="button" class="button mepr-pagination-last" aria-label="<?php esc_attr_e('Last page', 'memberpress'); ?>">
          <span class="dashicons dashicons-controls-skipforward" aria-hidden="true"></span>
        </button>
      </div>
    </nav>
  </div>
</div>

<!-- Snippet Preview Modal -->
<div id="mepr-snippet-preview-modal" class="mepr-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="mepr-modal-snippet-title" aria-describedby="mepr-modal-snippet-description">
  <div class="mepr-modal-overlay" aria-hidden="true"></div>
  <div class="mepr-modal-content">
    <div class="mepr-modal-header">
      <h3 id="mepr-modal-snippet-title"></h3>
      <button type="button" class="mepr-modal-close" aria-label="<?php esc_attr_e('Close preview modal', 'memberpress'); ?>">
        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
      </button>
    </div>
    <div class="mepr-modal-body">
      <div class="mepr-snippet-preview-meta">
        <span id="mepr-modal-snippet-difficulty" class="mepr-snippet-difficulty" role="text"></span>
        <span id="mepr-modal-snippet-category" class="mepr-snippet-tag" role="text"></span>
        <span id="mepr-modal-snippet-installed" class="mepr-snippet-tag mepr-snippet-installed-badge" style="display: none;" role="text"><?php esc_html_e('Installed', 'memberpress'); ?></span>
      </div>
      <div class="mepr-snippet-preview-description">
        <h4><?php esc_html_e('Description', 'memberpress'); ?></h4>
        <p id="mepr-modal-snippet-description"></p>
      </div>
      <div class="mepr-snippet-preview-code">
        <h4><?php esc_html_e('Code Preview', 'memberpress'); ?></h4>
        <pre id="mepr-modal-snippet-code" tabindex="0" role="region" aria-label="<?php esc_attr_e('Snippet code', 'memberpress'); ?>"><code class="language-php"></code></pre>
        <button type="button" class="button mepr-copy-code" aria-label="<?php esc_attr_e('Copy code to clipboard', 'memberpress'); ?>">
          <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
                <?php esc_html_e('Copy Code', 'memberpress'); ?>
        </button>
      </div>
      <div class="mepr-snippet-preview-instructions">
        <h4><?php esc_html_e('Installation Instructions', 'memberpress'); ?></h4>
        <p><?php esc_html_e('This snippet requires WP Code plugin to be installed and activated. Click "Use Snippet" below to automatically install WP Code (if needed) and add this snippet to your site.', 'memberpress'); ?></p>
      </div>
    </div>
    <div class="mepr-modal-footer">
      <button type="button" class="button button-secondary mepr-modal-cancel"><?php esc_html_e('Cancel', 'memberpress'); ?></button>
      <button type="button" class="button button-primary mepr-modal-use-snippet" data-snippet-url="" aria-label="<?php esc_attr_e('Use this snippet', 'memberpress'); ?>">
                <?php esc_html_e('Use Snippet', 'memberpress'); ?>
      </button>
    </div>
  </div>
</div>
