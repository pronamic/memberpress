<?php if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');} ?>
<div class="mepr-onboarding">
  <div class="mepr-onboarding-logo">
    <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/memberpress-logo.svg'); ?>" alt="">
  </div>
  <h1><?php esc_html_e('Welcome and thank you for choosing us!', 'memberpress'); ?></h1>
  <p class="mepr-onboarding-intro">
    <?php esc_html_e("MemberPress setup is fast and easy. Click below, and we'll walk you through the quick initial process. And don't worry. You can go back and change anything you do – at anytime. Nothing's permanent (unless you want it to be). So feel free to explore!", 'memberpress'); ?>
  </p>
  <div class="mepr-onboarding-get-started">
    <a href="<?php echo esc_url(admin_url('admin.php?page=memberpress-onboarding&step=1')); ?>"><?php esc_html_e('Get Started', 'memberpress'); ?><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/long-arrow-right.svg'); ?>" alt=""></a>
  </div>
  <div class="mepr-onboarding-hero">
    <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/hero.jpg'); ?>" alt="">
  </div>
  <div class="mepr-onboarding-customers-heart">
    Customers <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/heart-outline.svg'); ?>" alt=""> MemberPress
  </div>
  <div class="mepr-onboarding-testimonials">
    <div class="mepr-onboarding-testimonial">
      <p class="mepr-onboarding-testimonial-big">I can't say enough about how great MemberPress has been for our company: from its ability to drip content, to the great support … and more.</p>
      <p>I've built custom WordPress designs and used different membership plugins, and by far MemberPress has provided more services and better value for the price. A true “win-win” solution.”</p>
      <div class="mepr-onboarding-testimonial-citation">
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/david.png'); ?>" alt="">
        <div>
          <h4>David Abling</h4>
          <p>Zion Eye Media</p>
          <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/stars.svg'); ?>" alt="">
        </div>
      </div>
    </div>
    <div class="mepr-onboarding-testimonial">
      <p>Having tried most membership plugins for client sites over the years, MemberPress stands out as a great combination of rich features but easy to use. I'm a big fan of the members' self-serve dashboard, quick setup, reports, and the automated emails. And the fact that they offer excellent customer support is also a major plus. As a result, we use MemberPress for our own membership site and whilst I don't believe there is always a ‘one-size fits all' membership plugin, MemberPress is pretty darn close.”</p>
      <div class="mepr-onboarding-testimonial-citation">
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/callie.png'); ?>" alt="">
        <div>
          <h4>Callie Willows</h4>
          <p>Membership Guys</p>
          <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/stars.svg'); ?>" alt="">
        </div>
      </div>
    </div>
    <div class="mepr-onboarding-testimonial">
      <p>Over many years of building membership sites for clients, we've built many of them with MemberPress. Not only is the code good & clean but the plugin is easy to set up, the support great and the plugin is easy enough to use that our customers can manage their sites for themselves.</p>
      <p class="mepr-onboarding-testimonial-big">This is why MemberPress has become our preferred membership solution.”</p>
      <div class="mepr-onboarding-testimonial-citation">
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/jon.png'); ?>" alt="">
        <div>
          <h4>Jon Brown</h4>
          <p>9Seeds</p>
          <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/stars.svg'); ?>" alt="">
        </div>
      </div>
    </div>
  </div>
  <div class="mepr-onboarding-features-section">
    <h2>Finally, a <strong>WordPress Membership Plugin</strong> that’s <span class="mepr-onb-underline"><strong>Easy</strong> and <strong>Powerful</strong></span></h2>
    <p><strong>MemberPress</strong> lets you build astounding WordPress membership sites, accept credit cards securely, sell online courses, control who sees your content, and sell digital downloads... all with easy setup.</p>
    <div class="mepr-onboarding-features">
      <div class="mepr-onboarding-feature">
        <span><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/icon-lock.svg'); ?>" alt=""></span>
        <h3>Access Rules</h3>
        <p>Control what content your users can access based on what membership or digital products they've purchased.</p>
      </div>
      <div class="mepr-onboarding-feature">
        <span><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/icon-graduation-cap.svg'); ?>" alt=""></span>
        <h3>Online Courses</h3>
        <p>Build and sell online courses with a click-and-go process using the Courses drag-and-drop visual builder.</p>
      </div>
      <div class="mepr-onboarding-feature">
        <span><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/icon-coupon.svg'); ?>" alt=""></span>
        <h3>Coupons</h3>
        <p>Create coupons, control their expiration dates and number of uses, and customize your coupon codes.</p>
      </div>
      <div class="mepr-onboarding-feature">
        <span><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/icon-comments.svg'); ?>" alt=""></span>
        <h3>Community Forums</h3>
        <p>Build and grow password protected communities through our many WordPress forum plugin integrations.</p>
      </div>
      <div class="mepr-onboarding-feature">
        <span><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/icon-square-dollar.svg'); ?>" alt=""></span>
        <h3>Pricing Pages</h3>
        <p>Customize dynamic pricing pages for your products or use our built-in themes – no CSS or HTML coding required.</p>
      </div>
      <div class="mepr-onboarding-feature">
        <span><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/icon-drip.svg'); ?>" alt=""></span>
        <h3>Content Drip</h3>
        <p>Increase value and maintain member interest with timed content releases and content access expiration.</p>
      </div>
      <div class="mepr-onboarding-feature">
        <span><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/icon-share.svg'); ?>" alt=""></span>
        <h3>Affiliate Program</h3>
        <p>Create a salesforce to maximize your profits with our affiliate program add-on, Easy Affiliate.</p>
      </div>
      <div class="mepr-onboarding-feature">
        <span><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/icon-credit-card.svg'); ?>" alt=""></span>
        <h3>Payment Gateways</h3>
        <p>Charge for digital products and bill for memberships with our many simple, seamless payment gateway integrations.</p>
      </div>
      <div class="mepr-onboarding-feature">
        <span><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/icon-repeat.svg'); ?>" alt=""></span>
        <h3>Subscriptions</h3>
        <p>Enjoy the security of automated billing, and let members join, upgrade, or cancel right from your website.</p>
      </div>
    </div>
  </div>
  <?php if(!MeprUtils::is_pro_edition(MEPR_EDITION)) : ?>
    <div class="mepr-onboarding-pricing">
      <h2>The Most Powerful WordPress Membership Plugin ... <span class="mepr-onb-underline">Without the Hidden Costs</span></h2>
      <p>Join thousands of professionals who have together sold <strong>over $1 billion in memberships</strong>.</p>
    </div>
    <div class="mepr-onboarding-pricing-table">
      <div class="mepr-onboarding-pricing-pro">
        <div class="mepr-onboarding-pricing-content">
          <div class="mepr-onboarding-price-title">Pro</div>
          <div class="mepr-onboarding-price-normally">normally $599</div>
          <div class="mepr-onboarding-price-cost">
            <span class="mepr-onb-price-currency">$</span>
            <span class="mepr-onb-price-amount">399</span>
            <span class="mepr-onb-price-term">/ year</span>
          </div>
          <div class="mepr-onboarding-price-savings">$200 savings*</div>
          <p class="mepr-onboarding-price-desc">Perfect for Pros and Advanced Membership Sites to drive big results.</p>
          <a href="https://memberpress.com/ipob/pricing-box/pro" class="mepr-onboarding-price-get-started">Get Started<img src="<?php echo esc_url(MEPR_IMAGES_URL . '/long-arrow-right.svg'); ?>" alt=""></a>
          <div class="mepr-onboarding-price-features">
            <div class="mepr-onboarding-price-feature">Everything in Plus, and:</div>
            <div class="mepr-onboarding-price-feature">Use on up to 5 Sites</div>
            <div class="mepr-onboarding-price-feature">Sell Corporate Accounts</div>
            <div class="mepr-onboarding-price-feature">Exclusive Pro Add-Ons*</div>
            <div class="mepr-onboarding-price-feature"><a href="https://memberpress.com/ipob/pricing-box/features">See all features...</a></div>
          </div>
        </div>
      </div>
      <?php if(!in_array(MEPR_EDITION, array('developer', 'memberpress-plus-2', 'memberpress-plus'))) : ?>
        <div class="mepr-onboarding-pricing-plus">
          <div class="mepr-onboarding-price-popular">Most Popular</div>
          <div class="mepr-onboarding-pricing-content">
            <div class="mepr-onboarding-price-title">Plus</div>
            <div class="mepr-onboarding-price-normally">normally $449</div>
            <div class="mepr-onboarding-price-cost">
              <span class="mepr-onb-price-currency">$</span>
              <span class="mepr-onb-price-amount">299</span>
              <span class="mepr-onb-price-term">/ year</span>
            </div>
            <div class="mepr-onboarding-price-savings">$150 savings*</div>
            <p class="mepr-onboarding-price-desc">Great for Entrepreneurs, Freelancers and other small businesses.</p>
            <a href="https://memberpress.com/ipob/pricing-box/plus" class="mepr-onboarding-price-get-started">Get Started<img src="<?php echo esc_url(MEPR_IMAGES_URL . '/long-arrow-right.svg'); ?>" alt=""></a>
            <div class="mepr-onboarding-price-features">
              <div class="mepr-onboarding-price-feature">Everything in Basic, and:</div>
              <div class="mepr-onboarding-price-feature">Use on up to 2 Sites</div>
              <div class="mepr-onboarding-price-feature">Advanced Marketing Integrations</div>
              <div class="mepr-onboarding-price-feature">Zapier – 2000+ Custom Integrations</div>
              <div class="mepr-onboarding-price-feature">Developer Tools</div>
              <div class="mepr-onboarding-price-feature"><a href="https://memberpress.com/ipob/pricing-box/features">See all features...</a></div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <div class="mepr-onboarding-endorsements-section">
    <p>Join thousands of professionals who have together sold <strong>over $1 billion in memberships.</strong></p>
    <div class="mepr-onboarding-endorsements">
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/chrislema.svg'); ?>" alt="Chris Lema"></div>
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/wpbeginner.svg'); ?>" alt="WP Beginner"></div>
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/membershipacad.svg'); ?>" alt="Membership Academy"></div>
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/isitwp.svg'); ?>" alt="Is It WP"></div>
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/wpkube.svg'); ?>" alt="WP Kube"></div>
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/wpengine.svg'); ?>" alt="WP Engine"></div>
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/bluehost.svg'); ?>" alt="Bluehost"></div>
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/godaddy.svg'); ?>" alt="GoDaddy"></div>
    </div>
    <div class="mepr-onboarding-verifications">
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/capterra.png'); ?>" width="138" height="36" alt="4.8 out of 5 on Capterra"></div>
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/g2.png'); ?>" width="140" height="35" alt="4.7 out of 5 on G2"></div>
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/trustpilot.png'); ?>" width="141" height="41" alt="4.8 out of 5 on Trust Pilot"></div>
      <div><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/wpbeginner.png'); ?>" width="167" height="42" alt="WPBeginner verified"></div>
    </div>
  </div>
  <div class="mepr-onboarding-guarantee">
    <div class="mepr-onboarding-guarantee-cols">
      <div class="mepr-onboarding-guarantee-image">
        <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/14days-badge.svg'); ?>" alt="">
      </div>
      <div class="mepr-onboarding-guarantee-content">
        <div class="mepr-onboarding-guarantee-title">100% No-Risk Money Back Guarantee!</div>
        <div class="mepr-onboarding-guarantee-text">
          <p>You are completely protected by our 100% No-Risk Guarantee. If you don’t like MemberPress over the next 14 days, we’ll happily refund 100% of your money. <strong>No questions asked.</strong></p>
          <p><img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/blair-signature.png'); ?>" alt="" width="206" height="49"></p>
          <p>
            Blair C Williams
            <br>
            Founder, MemberPress
          </p>
        </div>
      </div>
    </div>
    <div class="mepr-onboarding-guarantee-sep"></div>
    <div class="mepr-onboarding-guarantee-bottom">
      <div>
        All pricing is in USD. You can change plans or cancel your account at any time.
        <br>
        * Special introductory pricing, all annual renewals are at full price.
      </div>
      <img src="<?php echo esc_url(MEPR_IMAGES_URL . '/onboarding/payment-options.png'); ?>" width="379" height="28" alt="">
    </div>
  </div>
</div>
