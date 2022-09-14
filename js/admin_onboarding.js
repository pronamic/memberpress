var MeprOnboarding = (function($) {
  var onboarding;
  var working = false;
  var selected_content = null;
  var ea_install_started;
  var upgrade_wait_started;

  onboarding = {
    init: function () {
      if(!MeprOnboardingL10n.step) {
        return; // Skip JS on Welcome page
      }

      if(MeprOnboardingL10n.step > 1) {
        onboarding.go_to_step(MeprOnboardingL10n.step);
      }


      $('body').on('click','.mepr-wizard-onboarding-video-collapse', function (e) {
        e.preventDefault();
        $('#inner_' + $(this).data('id')).hide();
        $('#wrapper_' + $(this).data('id')).removeClass('active');
        $('#expand_' + $(this).data('id')).show();
      });

      $('body').on('click','.mepr-wizard-onboarding-video-expand', function (e) {
        e.preventDefault();
        $(this).hide();
        $('#wrapper_' + $(this).data('id')).show();
        $('#wrapper_' + $(this).data('id')).addClass('active');
        $('#inner_' + $(this).data('id')).show();
        $('#mepr_play_' + $(this).data('id')).trigger('click');
      });

      $('body').on('click','.mepr-video-play-button', function (e) {
        e.preventDefault();
        var meprPlayBtn = $(this);
        onboarding.load_video(meprPlayBtn, 1);
      });

      $('.mepr-wizard-go-to-step').on('click', function () {
        var current_step = MeprOnboardingL10n.step;
        var context = $(this).data('context');
        onboarding.go_to_step($(this).data('step'));

        if(current_step == 3 || current_step == 4 || current_step == 5){
          if(context == 'skip'){
            $('.mepr_onboarding_step_3').addClass('mepr-wizard-current-step-skipped');
            $('.mepr_onboarding_step_4').addClass('mepr-wizard-current-step-skipped');
            $('.mepr_onboarding_step_5').addClass('mepr-wizard-current-step-skipped');
            $.ajax({
              method: 'POST',
              url: MeprOnboardingL10n.ajax_url,
              dataType: 'json',
              data: {
                action: 'mepr_onboarding_mark_content_steps_skipped',
                _ajax_nonce: MeprOnboardingL10n.mark_content_steps_skipped_nonce,
                data: JSON.stringify({})
              }
            });
            return;
          }else{
            $('.mepr_onboarding_step_3').removeClass('mepr-wizard-current-step-skipped');
            $('.mepr_onboarding_step_4').removeClass('mepr-wizard-current-step-skipped');
            $('.mepr_onboarding_step_5').removeClass('mepr-wizard-current-step-skipped');
          }
        }

        onboarding.mark_steps_complete(current_step);

      });


      $(window).on('resize', function(){

         if( $( window ).width() > 1440 ){
            $('.mepr-wizard-onboarding-video-expand').each(function(){
              var _this = $(this);
              var obj_id = $(this).data('id');
              $('#expand_' + obj_id).trigger('click');
            });
         }
      });

      $(window).trigger('resize');

      $(window).on('popstate', function (e) {
        var state = e.originalEvent.state;

        if(state && state.step) {
          onboarding.display_step(state.step);
        }
      });

      $('.mepr-wizard-feature').on('click', function () {
        onboarding.toggle_feature($(this));
      });

      onboarding.show_features_to_install();

      $('#mepr-wizard-save-features').on('click', onboarding.save_features);

      onboarding.setup_popups();

      $('body').on('change','input[name="mepr_wizard_create_content_type"]', function () {
        var value = $(this).val();

        $('#mepr-wizard-create-content-page-fields')[value === 'course' ? 'hide' : 'show']();
        $('#mepr-wizard-create-content-course-fields')[value === 'course' ? 'show' : 'hide']();
        $('#mepr-wizard-create-content-course-help')[value === 'course' ? 'show' : 'hide']();
      });

      $('body').on('change','.mepr-wizard-feature-input[value="memberpress-courses"]', function () {
        if($('.mepr-wizard-feature-input[value="memberpress-courses"]').is(':checked')) {
          $('input[name="mepr_wizard_create_content_type"][value="course"]').prop('checked', true).triggerHandler('change');
          $('#mepr-wizard-create-new-content-popup').find('.mepr-wizard-create-content-type').show();
        }
        else {
          $('input[name="mepr_wizard_create_content_type"][value="page"]').prop('checked', true).triggerHandler('change');
          $('#mepr-wizard-create-new-content-popup').find('.mepr-wizard-create-content-type').hide();
        }
      }).triggerHandler('change');

      $('body').on('click', '#mepr-wizard-create-new-content-save', onboarding.create_new_content);

      $('.mepr-wizard-selected-content-expand-menu').on('click', function (e) {
        e.stopPropagation();
        var element_id = $(this).data('id');
        $('#'+element_id).show();

        $(document.body).one('click', function () {
          $('#' + element_id).hide();
        });
      });

      $('#mepr-wizard-selected-content-delete').on('click', function () {
        selected_content = null;

        var $selected_content = $('#mepr-wizard-selected-content');
        $selected_content.find('.mepr-wizard-selected-content-heading').text('');
        $selected_content.find('.mepr-wizard-selected-content-name').text('');
        $selected_content.hide();

        $('#mepr-wizard-content-nav-continue').hide();
        $('#mepr-wizard-create-select-content, #mepr-wizard-content-nav-skip').show();

        $('#mepr-wizard-create-rule-membershipname').val('');
      });

      $('#mepr-wizard-selected-membership-delete').on('click', function () {

        $('#mepr-wizard-selected-membership').hide();
        $('#mepr-wizard-membership-nav-continue').hide();
        $('#mepr-wizard-create-membership-content, #mepr-wizard-membership-nav-skip, #mepr-wizard-create-select-membership').show();

        var data = {
          membership_rule_id: 0
        };

        $.ajax({
          method: 'POST',
          url: MeprOnboardingL10n.ajax_url,
          dataType: 'json',
          data: {
            action: 'mepr_onboarding_unset_membership',
            _ajax_nonce: MeprOnboardingL10n.unset_membership_nonce,
            data: JSON.stringify(data)
          }
        });
      });

      $('#mepr-wizard-choose-content-search').on('keyup', onboarding.debounce(onboarding.search_content, 250));

      $('#mepr-wizard-choose-content-save').on('click', onboarding.select_existing_content);
      $('#mepr-wizard-selected-content-delete').on('click', onboarding.select_content_remove);

      $('#mepr-wizard-create-new-membership-save').on('click', onboarding.create_new_membership);

      $('#mepr-wizard-create-new-rule-save').on('click', onboarding.create_new_rule);

      $('#mepr-wizard-selected-rule-delete').on('click', function () {
        var $selected_rule = $('#mepr-wizard-selected-rule');
        $selected_rule.hide();

        $('#mepr-wizard-rule-nav-continue').hide();
        $('#mepr-wizard-create-rule, #mepr-wizard-rule-nav-skip').show();

        var data = {
          membership_rule_id: 0
        };

        $.ajax({
          method: 'POST',
          url: MeprOnboardingL10n.ajax_url,
          dataType: 'json',
          data: {
            action: 'mepr_onboarding_unset_rule',
            _ajax_nonce: MeprOnboardingL10n.unset_rule_nonce,
            data: JSON.stringify(data)
          }
        });
      });

      if( MeprOnboardingL10n.step == 3 ){
        if( MeprOnboardingL10n.content_id > 0 ){
          $('#mepr_wizard_choose_content_post-'+MeprOnboardingL10n.content_id).prop('checked', true);
          $('#mepr-wizard-choose-content-save').trigger('click');
        }
      }

      $('#mepr-wizard-add-stripe').on('click', onboarding.add_stripe_payment_method);
      $('#mepr-wizard-add-authorize').on('click', function () {
        onboarding.add_authorize_payment_method.call(this, false);
      });
      $('#mepr-wizard-configure-authorize-save').on('click', onboarding.save_authorize_config);
      $('#mepr-wizard-skip-payment-methods').on('click', onboarding.skip_payment_methods);
      $('#mepr-wizard-add-offline-payment-method').on('click', onboarding.add_offline_payment_method);

      $(document.body).on('click', '#mepr-wizard-payment-gateway-expand-menu', function (e) {
        e.stopPropagation();
        $('#mepr-wizard-payment-gateway-menu').show();

        $(document.body).one('click', function () {
          $('#mepr-wizard-payment-gateway-menu').hide();
        });
      });

      $(document.body).on('click', '#mepr-wizard-payment-gateway-delete', onboarding.remove_payment_method);

      $(document.body).on('click', '#mepr-deactivate-license-key', onboarding.deactivate_license);

      if( MeprOnboardingL10n.step == 1 ){
          $('#mepr-wizard-license-wrapper').removeClass('mepr-hidden');
      }

      if( MeprOnboardingL10n.step == 4 ){
        if( MeprOnboardingL10n.membership_id > 0 ){
          onboarding.fillin_membership_data();
        }else{
          $('#mepr-wizard-create-select-membership').show();
        }
      }

      if( MeprOnboardingL10n.step == 5 ){
        if( MeprOnboardingL10n.membership_rule_id > 0 ){
          onboarding.fillin_membership_rule_data();
        }else{
          $('#mepr-wizard-create-rule').show();
        }
      }

      $(document.body).on('click', '#mepr-wizard-finish-configure-authorize', function () {
        if($.magnificPopup) {
          $.magnificPopup.open({
            mainClass: 'mepr-wizard-mfp',
            closeOnBgClick: false,
            items: {
              src: '#mepr-wizard-configure-authorize-popup',
              type: 'inline'
            }
          });
        }
      });

      $('#mepr-wizard-finish-onboarding').on('click', onboarding.finish);
    },

    load_video: function (o_this) {
      var video_id = o_this.data('id');

      if(o_this.hasClass('iframe_loaded')){
        return;
      }
      var video_holder_id = o_this.data('holder-id');
      var video_hash = o_this.data('hash');
      var iframe_id = 'mepr_iframe' + video_hash;

      $('#'+ video_holder_id).html('<iframe id="'+iframe_id+'" width="100%" height="100%" src="https://www.youtube.com/embed/'+video_id+'?rel=0&autoplay=0&mute=1&enablejsapi=1" frameborder="0" allowfullscreen></iframe>')
      o_this.addClass('iframe_loaded');
    },

    mark_steps_complete: function (current_step) {
      $.ajax({
          method: 'POST',
          url: MeprOnboardingL10n.ajax_url,
          dataType: 'json',
          data: {
            action: 'mepr_onboarding_mark_steps_complete',
            _ajax_nonce: MeprOnboardingL10n.mark_steps_complete_nonce,
             data: JSON.stringify({step:current_step})
           }
      });
    },

    toggle_feature: function ($feature) {
      var $checkbox = $feature.find('input[type="checkbox"]');

      $checkbox.prop('checked', !$checkbox.prop('checked')).triggerHandler('change');
      onboarding.show_features_to_install();
    },

    show_features_to_install: function () {
      var plugins_to_install = [];
      var $plugins_to_install = $('.mepr-wizard-plugins-to-install');

      $('.mepr-wizard-feature-input:checked').each(function () {
        var value = $(this).val();

        if(value && MeprOnboardingL10n.features[value]) {
          plugins_to_install.push(MeprOnboardingL10n.features[value]);
        }
      });

      $plugins_to_install.find('span').text(plugins_to_install.join(', '));
      $plugins_to_install[plugins_to_install.length ? 'show' : 'hide']();
    },

    save_features: function () {
      if(working) {
        return;
      }

      working = true;

      var $button = $(this),
        button_html = $button.html(),
        button_width = $button.width();

      $button.width(button_width).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');

      var features = [];

      $('.mepr-wizard-feature-input:checked').each(function () {
        var value = $(this).val();

        if(value && MeprOnboardingL10n.features[value]) {
          features.push(value);
        }
      });

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_save_features',
          _ajax_nonce: MeprOnboardingL10n.save_features_nonce,
          data: JSON.stringify(features)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            onboarding.go_to_step(3);
          }
          else {
            onboarding.save_features_error(response.data);
          }
        }
        else {
          onboarding.save_features_error('Invalid response');
        }
      })
      .fail(function () {
        onboarding.save_features_error('Request failed');
      })
      .always(function () {
        $button.html(button_html).width('auto');
        working = false;
      });
    },

    save_features_error: function (message) {
      alert(message || MeprOnboardingL10n.an_error_occurred);
    },

    go_to_step: function (step) {
      MeprOnboardingL10n.step = step;
      onboarding.display_step(step);

      var url = new URL(window.location);
      url.searchParams.set('step', step);
      window.history.pushState({ step: step }, '', url);

      if( step == 3 ){
        if( MeprOnboardingL10n.content_id > 0 ){
          $('#mepr_wizard_choose_content_post-'+MeprOnboardingL10n.content_id).prop('checked', true);
          $('#mepr-wizard-choose-content-save').trigger('click');
        }
      }

      if( step == 4 ){
        if( MeprOnboardingL10n.membership_id > 0 ){
          onboarding.fillin_membership_data();
        }else{
          $('#mepr-wizard-create-select-membership').show();
        }
      }

      if( step == 5 ){
        if( MeprOnboardingL10n.membership_rule_id > 0 ){
          onboarding.fillin_membership_rule_data();
        }else{
          $('#mepr-wizard-create-rule').show();
        }
      }

      if( step == 7 ){
        onboarding.load_finish_step();
      }

      if( step == 8 ){
        onboarding.load_complete_step();
      }

      if($('.mepr-wizard-onboarding-video-'+step).length){
        var meprPlayBtn =  $('.mepr-video-play-button', $('.mepr-wizard-onboarding-video-'+step) );
        onboarding.load_video(meprPlayBtn);
      }
    },

    load_finish_step: function () {
      var edition = MeprOnboardingL10n.edition_url_param;

      if(upgrade_wait_started && (Date.now() - upgrade_wait_started > 45000)) {
        edition = null;
      }

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_load_finish_step',
          _ajax_nonce: MeprOnboardingL10n.load_finish_step,
          data: JSON.stringify({
            step: 8,
            edition: edition
          })
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            $('#mepr-wizard-finish-step-container').html(response.data.html);

            if($('#mepr-upgrade-wait-edition').length) {
              if(!upgrade_wait_started) {
                upgrade_wait_started = Date.now();
              }

              setTimeout(function () {
                onboarding.load_finish_step();
              }, 10000);

              return;
            }

            if($('#mepr-finishing-setup-redirect').length) {
              setTimeout(function(){
                onboarding.mark_steps_complete(7);
                onboarding.go_to_step(8);
              }, 1500);
            }

            if($('#mepr_wizard_finalize_setup').length) {
              if($('#mepr_wizard_install_correct_edition').length) {
                onboarding.install_correct_edition();
              } else {
                if($('#start_addon_slug_installable').length) {
                  onboarding.install_addons($('#start_addon_slug_installable').val());
                }
                else {
                  $('#mepr-wizard-finish-step-container').find('.mepr-wizard-step-description').hide();
                }

                if($('#mepr-wizard-finish-add-authorize-gateway').length) {
                  onboarding.add_authorize_payment_method.call($('#mepr-wizard-finish-configure-authorize').get(0), true);
                }
              }
            }
          }
        }
      })
      .fail(function () {

      })
      .always(function () {

      });
    },

    load_complete_step: function () {
      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_load_complete_step',
          _ajax_nonce: MeprOnboardingL10n.load_complete_step,
           data: JSON.stringify({step:8})
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            var completed_step_urls = response.data.html;
            $('#mepr-wizard-completed-step-urls').html(completed_step_urls);
          }
        }
      })
      .fail(function () {

      })
      .always(function () {

      });
    },

    display_step: function (step) {
      $('.mepr-wizard-step').hide();
      $('.mepr-wizard-step-' + step).show();
      $('.mepr-wizard-nav-step').hide();
      $('.mepr-wizard-nav-step-' + step).css('display', 'flex');
    },

    setup_popups: function () {
      if(!$.magnificPopup) {
        return;
      }

      $('#mepr-wizard-create-new-content').on('click', function () {
        var o_this = $(this);
        o_this.attr('disabled','disabled');
        $.ajax({
          method: 'POST',
          url: MeprOnboardingL10n.ajax_url,
          dataType: 'json',
          data: {
            action: 'mepr_onboarding_load_create_new_content',
            _ajax_nonce: MeprOnboardingL10n.load_create_new_content,
             data: JSON.stringify({step:3})
          }
        })
        .done(function (response) {
          o_this.removeAttr('disabled');
          if(response && typeof response.success === 'boolean') {
            if(response.success) {
              $('#mepr-wizard-create-new-content-popup').html(response.data.html);
              $.magnificPopup.open({
                mainClass: 'mepr-wizard-mfp',
                items: {
                  src: '#mepr-wizard-create-new-content-popup',
                  type: 'inline'
                }
              });
            }
          }
        })
        .fail(function () {
          o_this.removeAttr('disabled');
        })
        .always(function () {
          o_this.removeAttr('disabled');
        });
      });

      $('#mepr-wizard-create-new-membership').on('click', function () {
        $.magnificPopup.open({
          mainClass: 'mepr-wizard-mfp',
          closeOnBgClick: false,
          items: {
            src: '#mepr-wizard-create-new-membership-popup',
            type: 'inline'
          }
        });
      });

      $('#mepr-wizard-create-new-rule').on('click', function () {
        $.magnificPopup.open({
          mainClass: 'mepr-wizard-mfp',
          items: {
            src: '#mepr-wizard-create-new-rule-popup',
            type: 'inline'
          }
        });
      });

      $('#mepr-wizard-choose-content').on('click', function () {
        $.magnificPopup.open({
          mainClass: 'mepr-wizard-mfp',
          items: {
            src: '#mepr-wizard-choose-content-popup',
            type: 'inline'
          }
        });
      });
    },

    create_new_content: function () {
      $('#mepr-wizard-create-new-content-popup').find('.mepr-wizard-popup-field-error').removeClass('mepr-wizard-popup-field-error');

      var type = $('input[name="mepr_wizard_create_content_type"]:checked').val();
      var $title = type === 'course' ? $('#mepr-wizard-create-content-course-name') : $('#mepr-wizard-create-content-page-name');

      var data = {
        type: type,
        title: $title.val()
      };

      if(!data.title) {
        $title.closest('.mepr-wizard-popup-field').addClass('mepr-wizard-popup-field-error');
        return;
      }

      if(working) {
        return;
      }

      working = true;

      var $button = $(this),
        button_html = $button.html(),
        button_width = $button.width();

      $button.width(button_width).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_save_new_content',
          _ajax_nonce: MeprOnboardingL10n.save_new_content_nonce,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            selected_content = response.data.post;

            $('#mepr-wizard-create-select-content, #mepr-wizard-content-nav-skip').hide();
            $('#mepr-wizard-content-nav-continue').show();

            var $selected_content = $('#mepr-wizard-selected-content');
            $selected_content.find('.mepr-wizard-selected-content-heading').text(response.data.heading);
            $selected_content.find('.mepr-wizard-selected-content-name').text(response.data.post.post_title);
            $selected_content.show();

            $title.val('');

            if($.magnificPopup) {
              $.magnificPopup.close();
            }

            $('label[for="mepr-wizard-create-rule-content"]').text(response.data.post.post_type === 'mpcs-course' ? MeprOnboardingL10n.course : MeprOnboardingL10n.page);
            $('#mepr-wizard-create-rule-content').val(response.data.post.post_title);

            MeprOnboardingL10n.content_id = response.data.post.ID;
            MeprOnboardingL10n.membership_rule_id = 0;

            $('#mepr-wizard-create-rule-content').val(response.data.rule_data.content_title);
            $('#mepr-wizard-create-rule-membershipname').val(response.data.rule_data.membership_title);
          }
          else {
            onboarding.wizard_mepr_ajax_error(response.data);
          }
        }
        else {
          onboarding.wizard_mepr_ajax_error('Invalid response');
        }
      })
      .fail(function () {
        onboarding.wizard_mepr_ajax_error('Request failed');
      })
      .always(function () {
        $button.html(button_html).width('auto');
        working = false;
      });
    },

    create_new_rule: function () {
      $('#mepr-wizard-create-new-rule-popup').find('.mepr-wizard-popup-field-error').removeClass('mepr-wizard-popup-field-error');

      var content = $('#mepr-wizard-create-rule-content');
      var membershipname = $('#mepr-wizard-create-rule-membershipname');

      var data = {
        content: content.val(),
        membershipname: membershipname.val()
      };

      if(!data.content) {
        content.closest('.mepr-wizard-popup-field').addClass('mepr-wizard-popup-field-error');
        return;
      }

      if(!data.membershipname) {
        membershipname.closest('.mepr-wizard-popup-field').addClass('mepr-wizard-popup-field-error');
        return;
      }

      if(working) {
        return;
      }

      working = true;

      var $button = $(this),
        button_html = $button.html(),
        button_width = $button.width();

      $button.width(button_width).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_save_new_rule',
          _ajax_nonce: MeprOnboardingL10n.save_new_rule_nonce,
           data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            selected_content = response.data.post;

            $('#mepr-wizard-create-rule, #mepr-wizard-rule-nav-skip').hide();
            $('#mepr-wizard-rule-nav-continue').show();

            var $selected_rule = $('#mepr-wizard-selected-rule');
            $selected_rule.find('#mepr-wizard-selected-content-heading').text(response.data.rule_data.content_type);
            $selected_rule.find('#mepr-selected-rule-content-name').text(response.data.rule_data.content_title);
            $selected_rule.find('#mepr-selected-rule-membership-name').text(response.data.rule_data.membership_title);
            $selected_rule.show();

            if($.magnificPopup) {
              $.magnificPopup.close();
            }
          }
          else {
            onboarding.wizard_mepr_ajax_error(response.data);
          }
        }
        else {
          onboarding.wizard_mepr_ajax_error('Invalid response');
        }
      })
      .fail(function () {
        onboarding.wizard_mepr_ajax_error('Request failed');
      })
      .always(function () {
        $button.html(button_html).width('auto');
        working = false;
      });
    },

    create_new_membership: function () {
      $('#mepr-wizard-create-new-membership-popup').find('.mepr-wizard-popup-field-error').removeClass('mepr-wizard-popup-field-error');

      var type = $('input[name="mepr_wizard_create_membership_type"]:checked').val();
      var $title = $('#mepr-wizard-create-membership-name');
      var $price = $('#mepr-wizard-create-membership-price');

      var data = {
        type: type,
        title: $title.val(),
        price: $price.val()
      };

      if(!data.title) {
        $title.closest('.mepr-wizard-popup-field').addClass('mepr-wizard-popup-field-error');
        return;
      }

      if(!data.price) {
        $price.closest('.mepr-wizard-popup-field').addClass('mepr-wizard-popup-field-error');
        return;
      }

      if( type != 'onetime' && parseFloat(data.price) <= 0.0 ){
        $price.closest('.mepr-wizard-popup-field').addClass('mepr-wizard-popup-field-error');
        return;
      }

      if(working) {
        return;
      }

      working = true;

      var $button = $(this),
        button_html = $button.html(),
        button_width = $button.width();

      $button.width(button_width).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_save_new_membership',
          _ajax_nonce: MeprOnboardingL10n.save_new_membership_nonce,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            selected_content = response.data.post;

            $('#mepr-wizard-create-select-membership, #mepr-wizard-membership-nav-skip').hide();
            $('#mepr-wizard-membership-nav-continue').show();

            var $selected_content = $('#mepr-wizard-selected-membership');
            $selected_content.find('#mepr-selected-membership-name').text(response.data.title);
            $selected_content.find('#mepr-selected-membership-billing').text(response.data.billing);
            $selected_content.find('#mepr-selected-membership-price').text(response.data.price_string);
            $selected_content.show();

            $('#mepr-wizard-create-new-membership-form')[0].reset();

            if($.magnificPopup) {
              $.magnificPopup.close();
            }

            $('#mepr-wizard-create-rule-membershipname').val(response.data.title);
          }
          else {
            onboarding.wizard_mepr_ajax_error(response.data);
          }
        }
        else {
          onboarding.wizard_mepr_ajax_error('Invalid response');
        }
      })
      .fail(function () {
        onboarding.wizard_mepr_ajax_error('Request failed');
      })
      .always(function () {
        $button.html(button_html).width('auto');
        working = false;
      });
    },


    fillin_membership_data: function () {

      var data = {
        membership_id: MeprOnboardingL10n.membership_id,
      };

      if(!data.membership_id) {
        return;
      }

      if(working) {
        return;
      }

      working = true;

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_get_membership',
          _ajax_nonce: MeprOnboardingL10n.get_membership_nonce,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            selected_content = response.data.post;

            $('#mepr-wizard-create-select-membership, #mepr-wizard-membership-nav-skip').hide();
            $('#mepr-wizard-membership-nav-continue').show();

            var $selected_content = $('#mepr-wizard-selected-membership');
            $selected_content.find('#mepr-selected-membership-name').text(response.data.title);
            $selected_content.find('#mepr-selected-membership-billing').text(response.data.billing);
            $selected_content.find('#mepr-selected-membership-price').text(response.data.price_string);
            $selected_content.show();
          }
        }
      })
      .fail(function () {
        onboarding.wizard_mepr_ajax_error('Request failed');
      })
      .always(function () {
        working = false;
      });
    },

    fillin_membership_rule_data: function () {

      var data = {
        membership_rule_id: MeprOnboardingL10n.membership_rule_id,
      };

      if(!data.membership_rule_id) {
        return;
      }

      if(working) {
        return;
      }

      working = true;

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_get_rule',
          _ajax_nonce: MeprOnboardingL10n.get_rule_nonce,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {

            $('#mepr-wizard-create-rule, #mepr-wizard-rule-nav-skip').hide();
            $('#mepr-wizard-rule-nav-continue').show();

            var $selected_rule = $('#mepr-wizard-selected-rule');
            $selected_rule.find('#mepr-wizard-selected-content-heading').text(response.data.content_type);
            $selected_rule.find('#mepr-selected-rule-content-name').text(response.data.content_title);
            $selected_rule.find('#mepr-selected-rule-membership-name').text(response.data.membership_title);
            $selected_rule.show();
          }
        }
      })
      .fail(function () {
        onboarding.wizard_mepr_ajax_error('Request failed');
      })
      .always(function () {
        working = false;
      });
    },

    install_correct_edition: function () {
      if(working) {
        return;
      }

      working = true;

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_install_correct_edition',
          _ajax_nonce: MeprOnboardingL10n.install_correct_edition,
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            window.location.reload();
          } else {
            alert(response.data);
          }
        } else {
          onboarding.wizard_mepr_ajax_error('Invalid response');
        }
      })
      .fail(function () {
        onboarding.wizard_mepr_ajax_error('Request failed');
      })
      .always(function () {
        working = false;
      });
    },

    install_addons: function (addon_slug) {
      var data = {
        addon_slug: addon_slug,
      };

      if(!data.addon_slug) {
        return;
      }

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_install_addons',
          _ajax_nonce: MeprOnboardingL10n.install_addons,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            var _addon_slug = response.data.addon_slug;
            var status = response.data.status;
            var message = response.data.message;
            var o_div = jQuery('#mepr-finish-step-addon-' + _addon_slug);
            var o_spinner = jQuery('#mepr-wizard-finish-step-' + _addon_slug);

            if(o_div.length && 1 === status) {
              o_div.find('.mepr-wizard-feature-activatedx').addClass('mepr-wizard-feature-activated');
              o_spinner.hide();
            }

            if(0 === status) {
              if(_addon_slug === 'easy-affiliate') {
                var timeout;

                if(!ea_install_started) {
                  ea_install_started = Date.now();
                  timeout = 60000;
                  o_div.find('.mepr-wizard-addon-text').text(MeprOnboardingL10n.may_take_couple_minutes);
                }
                else {
                  timeout = 15000;
                }

                if(Date.now() - ea_install_started > 300000) {
                  o_spinner.hide();
                  o_div.find('.mepr-wizard-addon-text').addClass('error').html(message);
                }
                else {
                  setTimeout(function () {
                    onboarding.install_addons(_addon_slug);
                  }, timeout);

                  return;
                }
              }
              else {
                o_spinner.hide();
                o_div.find('.mepr-wizard-addon-text').addClass('error').html(message);
              }
            }

            if(_addon_slug === 'easy-affiliate' && !o_div.find('.mepr-wizard-addon-text').hasClass('error')) {
              o_div.find('.mepr-wizard-addon-text').html('');
            }

            var next_addon = response.data.next_addon;

            if(next_addon !== '') {
              onboarding.install_addons(next_addon);
            }
            else if($('#mepr-wizard-finish-step-addon-MeprAuthorizeGateway').length) {
              $('#mepr-wizard-finish-step-container').find('.mepr-wizard-step-description').hide();
              $('#mepr-wizard-finish-skip').hide();
              $('#mepr-wizard-finish-continue').show();
            }
            else {
              setTimeout(function(){
                onboarding.mark_steps_complete(7);
                onboarding.go_to_step(8);
              }, 1500);
            }
          }
          else {
            onboarding.install_addons_error(typeof response.data === 'string' ? response.data : null);
          }
        } else {
          onboarding.install_addons_error('Invalid response');
        }
      })
      .fail(function () {
        onboarding.install_addons_error('Request failed');
      });
    },

    install_addons_error: function (message) {
      $('#mepr-wizard-finish-step-container .mepr-wizard-step-description').text(MeprOnboardingL10n.error_installing_addon);
      $('#mepr-wizard-finish-step-container .animate-spin').hide();
      onboarding.wizard_mepr_ajax_error(message);
    },

    wizard_mepr_ajax_error: function (message) {
      alert(message || MeprOnboardingL10n.an_error_occurred);
    },

    debounce: function (func, wait, immediate) {
      var timeout;

      return function() {
        var context = this,
          args = arguments;

        var later = function() {
          timeout = null;

          if (!immediate) {
            func.apply(context, args);
          }
        };

        var callNow = immediate && !timeout;

        clearTimeout(timeout);
        timeout = setTimeout(later, wait);

        if (callNow) {
          func.apply(context, args);
        }
      };
    },

    search_content: function () {
      var data = {
        search: $(this).val()
      };

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_search_content',
          _ajax_nonce: MeprOnboardingL10n.search_content_nonce,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            $('#mepr-wizard-choose-content-posts').html(response.data);
          }
          else {
            onboarding.search_content_error(response.data);
          }
        }
        else {
          onboarding.search_content_error('Invalid response');
        }
      })
      .fail(function () {
        onboarding.search_content_error('Request failed');
      });
    },

    search_content_error: function (message) {
      $('#mepr-wizard-choose-content-posts').html(
        $('<div class="notice notice-error inline">').append(
          $('<p>').text(message || MeprOnboardingL10n.an_error_occurred)
        )
      );
    },

    select_content_remove: function () {
      var data = {
        content_id: 0
      };

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_unset_content',
          _ajax_nonce: MeprOnboardingL10n.unset_content_nonce,
          data: JSON.stringify(data)
        }
      });

      MeprOnboardingL10n.membership_rule_id = 0;
      MeprOnboardingL10n.content_id = 0;

      $('label[for="mepr-wizard-create-rule-content"]').text('');
      $('#mepr-wizard-create-rule-content').val('');
    },

    select_existing_content: function () {
      var $selected_radio = $('#mepr-wizard-choose-content-posts input[type="radio"]:checked');

      if(!$selected_radio.length) {
        return;
      }

      var $selected_post = $selected_radio.closest('.mepr-wizard-choose-content-post');

      selected_content = $selected_post.data('post');

      $('#mepr-wizard-create-select-content, #mepr-wizard-content-nav-skip').hide();
      $('#mepr-wizard-content-nav-continue').show();

      var $selected_content = $('#mepr-wizard-selected-content');
      $selected_content.find('.mepr-wizard-selected-content-heading').text(selected_content.post_type === 'mpcs-course' ? MeprOnboardingL10n.course_name : MeprOnboardingL10n.page_title);
      $selected_content.find('.mepr-wizard-selected-content-name').text(selected_content.post_title);
      $selected_content.show();

      $('label[for="mepr-wizard-create-rule-content"]').text(selected_content.post_type === 'mpcs-course' ? MeprOnboardingL10n.course : MeprOnboardingL10n.page);
      $('#mepr-wizard-create-rule-content').val(selected_content.post_title);

      if(working){
        return;
      }

      working = true;

      var data = {
        content_id: selected_content.ID
      };

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_set_content',
          _ajax_nonce: MeprOnboardingL10n.set_content_nonce,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if($.magnificPopup) {
          $.magnificPopup.close();
        }

        if(response && typeof response.success === 'boolean') {
          $('#mepr-wizard-create-rule-content').val(response.data.rule_data.content_title);
          $('#mepr-wizard-create-rule-membershipname').val(response.data.rule_data.membership_title);
        }
      })
      .fail(function () {
        alert('Request failed');
      })
      .always(function () {
        working = false;
      });
    },

    add_stripe_payment_method: function() {
      if(working){
        return;
      }

      working = true;

      var $button = $(this),
        button_html = $button.html(),
        button_width = $button.width();

      $button.width(button_width).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_add_stripe_payment_method',
          _ajax_nonce: MeprOnboardingL10n.add_payment_method_nonce,
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            window.location = response.data;
          }
          else {
            console.log(response.data);
            $button.html(button_html).width('auto');
            alert('Request failed');
          }
        }
        else {
          $button.html(button_html).width('auto');
          alert('Request failed');
        }
      })
      .fail(function () {
        $button.html(button_html).width('auto');
        alert('Request failed');
      })
      .always(function () {
        working = false;
      });
    },

    add_paypal_payment_method: function(auth_code, shared_id) {
      if(working){
        return;
      }

      working = true;

      var $button = $('#mepr-wizard-add-paypal'),
        button_html = $button.html(),
        button_width = $button.width(),
        data = {
          sandbox: $button.data('sandbox') === true,
          auth_code: auth_code,
          shared_id: shared_id,
          gateway_id: $button.data('gateway-id')
        };

      $button.width(button_width).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_add_paypal_payment_method',
          _ajax_nonce: MeprOnboardingL10n.add_payment_method_nonce,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            $('#mepr-wizard-payments').hide();
            $('#mepr-wizard-payment-selected').html(response.data);
            $('#mepr-wizard-payments-skip').add($('#mepr-wizard-payments-skip-empty')).hide();
            $('#mepr-wizard-payments-continue').show();
          }
          else {
            console.log(response.data);
            alert('Request failed');
          }
        }
        else {
          alert('Request failed');
        }
      })
      .fail(function () {
        alert('Request failed');
      })
      .always(function () {
        $button.html(button_html).width('auto');
        working = false;
      });
    },

    add_authorize_payment_method: function (automatic) {
      if(!automatic) {
        if(working) {
          return;
        }

        working = true;
      }

      var $button = $(this),
        button_html = $button.html(),
        button_width = $button.width();

      var upgrade_required = 0;
      if($button.hasClass('mepr-optin')){
        upgrade_required = 1;
      }

      var data = {
        upgrade_required: upgrade_required
      };

      $button.width(button_width).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_add_authorize_payment_method',
          _ajax_nonce: MeprOnboardingL10n.add_payment_method_nonce,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            $('#mepr-wizard-payments').hide();
            $('#mepr-wizard-payment-selected').html(response.data.payment_gateway_html);
            $('#mepr-wizard-authorize-webhook_url').val(response.data.webhook_url);
            $('#mepr-wizard-payments-skip').add($('#mepr-wizard-payments-skip-empty')).hide();
            $('#mepr-wizard-payments-continue').show();

            if(!automatic && $.magnificPopup) {
              if(!upgrade_required){
                $.magnificPopup.open({
                  mainClass: 'mepr-wizard-mfp',
                  closeOnBgClick: false,
                  items: {
                    src: '#mepr-wizard-configure-authorize-popup',
                    type: 'inline'
                  }
                });
              }else{
                $.magnificPopup.open({
                  mainClass: 'mepr-wizard-mfp',
                  closeOnBgClick: false,
                  items: {
                    src: '#mepr-wizard-authnet-pro-optin-popup',
                    type: 'inline'
                  }
                });
              }
            }
          }
          else {
            console.log(response.data);
            alert('Request failed');
          }
        }
        else {
          alert('Request failed');
        }
      })
      .fail(function () {
        alert('Request failed');
      })
      .always(function () {
        $button.html(button_html).width('auto');

        if(!automatic) {
          working = false;
        }
      });
    },

    remove_payment_method: function () {
      var $selected = $('#mepr-wizard-payment-selected'),
        data = {
          gateway_id: $selected.find('> .mepr-wizard-payment-gateway').data('gateway-id')
        };

      $('#mepr-wizard-payments').show();
      $selected.empty();

      $('#mepr-wizard-payments-skip-empty').add($('#mepr-wizard-payments-continue')).hide();
      $('#mepr-wizard-payments-skip').show();

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_remove_payment_method',
          _ajax_nonce: MeprOnboardingL10n.remove_payment_method_nonce,
          data: JSON.stringify(data)
        }
      });
    },

    save_authorize_config: function () {
      if(working){
        return;
      }

      working = true;

      var $button = $(this),
        button_html = $button.html(),
        button_width = $button.width(),
        data = {
          gateway_id: $('#mepr-wizard-payment-selected').find('> .mepr-wizard-payment-gateway').data('gateway-id'),
          login_name: $('#mepr-wizard-authorize-login-name').val(),
          transaction_key: $('#mepr-wizard-authorize-transaction-key').val(),
          signature_key: $('#mepr-wizard-authorize-signature-key').val()
        };

      $button.width(button_width).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');
      $('#mepr-wizard-configure-authorize-popup').find('.mepr-wizard-popup-field-error').removeClass('mepr-wizard-popup-field-error');

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_save_authorize_config',
          _ajax_nonce: MeprOnboardingL10n.save_authorize_config_nonce,
          data: JSON.stringify(data)
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            $('#mepr-wizard-payment-selected').html(response.data);
            $('#mepr-wizard-finish-skip').hide();
            $('#mepr-wizard-finish-continue').show();

            if($.magnificPopup) {
              $.magnificPopup.close();
            }
          }
          else {
            if(response.data.errors) {
              $.each(response.data.errors, function (i, field) {
                $('#mepr-wizard-authorize-' + field).closest('.mepr-wizard-popup-field').addClass('mepr-wizard-popup-field-error');
              });
            }
            else {
              console.log(response.data);
              alert('Request failed');
            }
          }
        }
        else {
          alert('Request failed');
        }
      })
      .fail(function () {
        alert('Request failed');
      })
      .always(function () {
        $button.html(button_html).width('auto');
        working = false;
      });
    },

    skip_payment_methods: function () {
      if($.magnificPopup) {
        $.magnificPopup.open({
          mainClass: 'mepr-wizard-mfp',
          closeOnBgClick: false,
          items: {
            src: '#mepr-wizard-skip-payment-methods-popup',
            type: 'inline'
          }
        });
      }
    },

    add_offline_payment_method: function () {
      if(working){
        return;
      }

      working = true;

      var $button = $(this),
        button_html = $button.html(),
        button_width = $button.width();

      $button.width(button_width).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');

      $.ajax({
        method: 'POST',
        url: MeprOnboardingL10n.ajax_url,
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_add_offline_payment_method',
          _ajax_nonce: MeprOnboardingL10n.add_payment_method_nonce,
        }
      })
      .done(function (response) {
        if(response && typeof response.success === 'boolean') {
          if(response.success) {
            onboarding.go_to_step(7);

            if($.magnificPopup) {
              $.magnificPopup.close();
            }
          }
          else {
            console.log(response.data);
            alert('Request failed');
          }
        }
        else {
          alert('Request failed');
        }
      })
      .fail(function () {
        alert('Request failed');
      })
      .always(function () {
        $button.html(button_html).width('auto');
        working = false;
      });
    },

    deactivate_license: function () {
      var $button = $(this),
        button_width = $button.width(),
        button_html = $button.html();

      if (working || !confirm(MeprOnboardingL10n.deactivate_confirm)) {
        return;
      }

      working = true;
      $button.width(button_width).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');
      $('#mepr-license-container').find('> .notice').remove();

      $.ajax({
        url: MeprOnboardingL10n.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'mepr_deactivate_license',
          _ajax_nonce: MeprOnboardingL10n.deactivate_license_nonce
        }
      })
      .done(function (response) {
        if (!response || typeof response != 'object' || typeof response.success != 'boolean') {
          onboarding.deactivate_license_error('Request failed');
        } else if (!response.success) {
          onboarding.deactivate_license_error(response.data);
        } else {
          window.location.reload();
        }
      })
      .fail(function () {
        onboarding.deactivate_license_error('Request failed');
      })
      .always(function () {
        working = false;
        $button.html(button_html).width('auto');
      });
    },

    deactivate_license_error: function (message) {
      $('#mepr-license-container').prepend(
        $('<div class="notice notice-error">').append(
          $('<p>').html(message)
        )
      );
    },

    finish: function () {
      var $button = $(this);

      if (working) {
        return;
      }

      working = true;
      $button.width($button.width()).html('<i class="mp-icon mp-icon-spinner animate-spin"></i>');

      $.ajax({
        url: MeprOnboardingL10n.ajax_url,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'mepr_onboarding_finish',
          _ajax_nonce: MeprOnboardingL10n.finish_nonce
        }
      })
      .always(function () {
        window.location = MeprOnboardingL10n.memberships_url;
      });
    }
  };

  $(onboarding.init);

  return onboarding;
})(jQuery);

function MeprOnboardingPayPalComplete(auth_code, shared_id) {
  MeprOnboarding.add_paypal_payment_method(auth_code, shared_id);
}
