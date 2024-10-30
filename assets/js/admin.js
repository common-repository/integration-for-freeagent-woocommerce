jQuery(document).ready(function($) {

  //Settings page
  var wc_freeagent_settings = {
    settings_groups: ['coupon', 'vatnumber', 'emails', 'email-notify', 'receipt', 'accounting'],
    init: function() {
      this.init_toggle_groups();

      $('#woocommerce_wc_freeagent_pro_email').keypress(this.submit_pro_on_enter);
      $('#woocommerce_wc_freeagent_pro_key-submit').on('click', this.submit_activate_form);
      $('#woocommerce_wc_freeagent_pro_key-deactivate').on('click', this.submit_deactivate_form);

      $('#wc_freeagent_auth_logout').on('click', this.logout);
      $('#wc_freeagent_authenticate').on('click', this.start_authenticate);

    },
    start_authenticate: function() {
      var nonce = $(this).data('nonce');
      var client_id = $('#wc_freeagent_auth_client_id_field').val();
      var client_secret = $('#wc_freeagent_auth_client_secret_field').val();
      var sandbox = $('#wc_freeagent_auth_sandbox').is(':checked');

      //Create request
      var data = {
        action: 'wc_freeagent_authenticate',
        nonce: nonce,
        client_id: client_id,
        client_secret: client_secret,
        sandbox: sandbox
      };

      if(!client_secret || !client_id) {
        $('.wc-freeagent-section-auth-client-fields').addClass('validate');
        setTimeout(function(){
          $('.wc-freeagent-section-auth-client-fields').removeClass('validate');
        }, 1000);
        return false;
      }

      //Show loading indicator
      wc_freeagent_metabox.loading_indicator($('.wc-freeagent-section-auth-step-3'), '#f1f1f1');

      //Start the auth with an ajax call
      $.post(ajaxurl, data, function(response) {

        //Hide loading indicator
        $('.wc-freeagent-section-auth-step-3').unblock();

        if(!response.data.error) {
          window.location.href = response.data.auth_url;
        }

      });
      return false;
    },
    logout: function() {
      alert('shit');
      var nonce = $(this).data('nonce');

      //Create request
      var data = {
        action: 'wc_freeagent_logout',
        nonce: nonce
      };

      //Show loading indicator
      wc_freeagent_metabox.loading_indicator($('.wc-freeagent-section-authenticated'), '#fff');

      //Start the auth with an ajax call
      $.post(ajaxurl, data, function(response) {

        //Hide loading indicator
        $('.wc-freeagent-section-authenticated').unblock();

        if(!response.data.error) {
          window.location.reload();
        }

      });
      return false;
    },
    init_toggle_groups: function() {
      $.each(wc_freeagent_settings.settings_groups, function( index, value ) {
        var checkbox = $('.wc-freeagent-toggle-group-'+value);
        var group_items = $('.wc-freeagent-toggle-group-'+value+'-item').parents('tr');
        var checked = checkbox.is(":checked");

        if(value == 'emails' && $('.wc-freeagent-toggle-group-'+value+':checked').length) {
          checked = true;
        }

        if(checked) {
          group_items.show();
        } else {
          group_items.hide();
        }
        checkbox.change(function(e){
          e.preventDefault();

          var checked = $(this).is(":checked");
          if(value == 'emails' && $('.wc-freeagent-toggle-group-'+value+':checked').length) {
            checked = true;
          }

          if(checked) {
            group_items.show();
          } else {
            group_items.hide();
          }
        });
      });
    },
    submit_pro_on_enter: function(e) {
      if (e.which == 13) {
        $(this).parent().find('button').click();
        return false;
      }
    },
    submit_activate_form: function() {
      var key = $('#woocommerce_wc_freeagent_pro_key').val();
      var email = $('#woocommerce_wc_freeagent_pro_email').val();
      var button = $(this);
      var form = button.parents('.wc-freeagent-section-pro');

      var data = {
        action: 'wc_freeagent_pro_check',
        key: key,
        email: email
      };

      form.block({
        message: null,
        overlayCSS: {
          background: '#ffffff url(' + wc_freeagent_params.loading + ') no-repeat center',
          backgroundSize: '16px 16px',
          opacity: 0.6
        }
      });

      form.find('.notice').hide();

      $.post(ajaxurl, data, function(response) {
        //Remove old messages
        if(response.success) {
          window.location.reload();
          return;
        } else {
          form.find('.notice p').html(response.data.message);
          form.find('.notice').show();
        }
        form.unblock();
      });

      return false;
    },
    submit_deactivate_form: function() {
      var button = $(this);
      var form = button.parents('.wc-freeagent-section-pro');

      var data = {
        action: 'wc_freeagent_pro_deactivate'
      };

      form.block({
        message: null,
        overlayCSS: {
          background: '#ffffff url(' + wc_freeagent_params.loading + ') no-repeat center',
          backgroundSize: '16px 16px',
          opacity: 0.6
        }
      });

      form.find('.notice').hide();

      $.post(ajaxurl, data, function(response) {
        //Remove old messages
        if(response.success) {
          window.location.reload();
          return;
        } else {
          form.find('.notice p').html(response.data.message);
          form.find('.notice').show();
        }
        form.unblock();
      });
      return false;
    }
  }

  //Metabox functions
  var wc_freeagent_metabox = {
    prefix: 'wc_freeagent_',
    prefix_id: '#wc_freeagent_',
    prefix_class: '.wc-freeagent-',
    $metaboxContent: $('#wc_freeagent_metabox .inside'),
    $disabledState: $('.wc-freeagent-metabox-disabled'),
    $optionsContent: $('.wc-freeagent-metabox-generate-options'),
    $autoMsg: $('.wc-freeagent-metabox-auto-msg'),
    $generateContent: $('.wc-freeagent-metabox-generate'),
    $optionsButton: $('#wc_freeagent_invoice_options'),
    $generateButtonInvoice: $('#wc_freeagent_invoice_generate'),
    $invoiceRow: $('.wc-freeagent-metabox-invoices-invoice'),
    $voidedRow: $('.wc-freeagent-metabox-invoices-void'),
    $voidedReceiptRow: $('.wc-freeagent-metabox-invoices-void_receipt'),
    $completeRow: $('.wc-freeagent-metabox-rows-data-complete'),
    $voidRow: $('.wc-freeagent-metabox-rows-data-void'),
    $messages: $('.wc-freeagent-metabox-messages'),
    nonce: $('.wc-freeagent-metabox-content').data('nonce'),
    order: $('.wc-freeagent-metabox-content').data('order'),
    init: function() {
      this.$optionsButton.on( 'click', this.show_options );
      $(this.prefix_class+'invoice-toggle').on( 'click', this.toggle_invoice );
      this.$generateButtonInvoice.on( 'click', this.generate_invoice );
      this.$completeRow.find('a').on( 'click', this.mark_completed );
      this.$voidRow.find('a').on( 'click', this.void_invoice );
      this.$messages.find('a').on( 'click', this.hide_message );
    },
    loading_indicator: function(button, color) {
      wc_freeagent_metabox.hide_message();
      button.block({
        message: null,
        overlayCSS: {
          background: color+' url(' + wc_freeagent_params.loading + ') no-repeat center',
          backgroundSize: '16px 16px',
          opacity: 0.6
        }
      });
    },
    show_options: function() {
      wc_freeagent_metabox.$optionsContent.slideToggle();
    },
    toggle_invoice: function() {
      var note = '';

      //Ask for message
      if($(this).hasClass('off')) {
        note = prompt("Turning off invoicing for this order. Whats the reason?", "I don't need an invoice for this order.");
        if (!note) {
          return false;
        }
      }

      //Create request
      var data = {
        action: wc_freeagent_metabox.prefix+'toggle_invoice',
        nonce: wc_freeagent_metabox.nonce,
        order: wc_freeagent_metabox.order,
        note: note
      };

      //Show loading indicator
      wc_freeagent_metabox.loading_indicator(wc_freeagent_metabox.$metaboxContent, '#fff');

      //Make request
      $.post(ajaxurl, data, function(response) {

        //Replace text
        wc_freeagent_metabox.$disabledState.find('span').text(note);

        //Hide loading indicator
        wc_freeagent_metabox.$metaboxContent.unblock();

        //Show/hide divs based on response
        if (response.data.state == 'off') {
          wc_freeagent_metabox.$disabledState.slideDown();
          wc_freeagent_metabox.$optionsContent.slideUp();
          wc_freeagent_metabox.$autoMsg.slideUp();
          wc_freeagent_metabox.$generateContent.slideUp();
          wc_freeagent_metabox.$voidedRow.slideUp();
        } else {
          wc_freeagent_metabox.$disabledState.slideUp();
          wc_freeagent_metabox.$autoMsg.slideDown();
          wc_freeagent_metabox.$generateContent.slideDown();
        }
      });

      return false;
    },
    generate_invoice: function() {
      var $this = $(this);
      var r = confirm($this.data('question'));
      var type = 'invoice';
      if (r != true) {
        return false;
      }

      var note = $('#wc_freeagent_invoice_note').val();
      var deadline = $('#wc_freeagent_invoice_deadline').val();
      var completed = $('#wc_freeagent_invoice_completed').val();
      var estimate = $('#wc_freeagent_invoice_estimate').is(':checked');
      if (estimate) {
        type = 'estimate';
      }

      //Create request
      var data = {
        action: wc_freeagent_metabox.prefix+'generate_invoice',
        nonce: wc_freeagent_metabox.nonce,
        order: wc_freeagent_metabox.order,
        note: note,
        deadline: deadline,
        completed: completed,
        type: type
      };

      //Show loading indicator
      wc_freeagent_metabox.loading_indicator(wc_freeagent_metabox.$metaboxContent, '#fff');

      //Make request
      $.post(ajaxurl, data, function(response) {

        //Hide loading indicator
        wc_freeagent_metabox.$metaboxContent.unblock();

        //Show success/error messages
        wc_freeagent_metabox.show_messages(response);

        //On success and error
        if(response.data.error) {

        } else {

          if(response.data.type == 'invoice') {
            wc_freeagent_metabox.$autoMsg.slideUp();
            wc_freeagent_metabox.$generateContent.slideUp();
            wc_freeagent_metabox.$voidedRow.slideUp();

            wc_freeagent_metabox.$invoiceRow.find('strong').text(response.data.name);
            wc_freeagent_metabox.$invoiceRow.find('a').attr('href', response.data.link);
            wc_freeagent_metabox.$invoiceRow.slideDown();
            wc_freeagent_metabox.$completeRow.slideDown();
            wc_freeagent_metabox.$voidRow.slideDown();

            if(response.data.completed) {
              wc_freeagent_metabox.$completeRow.find('a').addClass('completed');
              wc_freeagent_metabox.$completeRow.find('a').text(response.data.completed);
            }
          }

        }

      });

      return false;
    },
    mark_completed_timeout: false,
    mark_completed: function() {
      var $this = $(this);

      //Do nothing if already marked completed
      if($this.hasClass('completed')) return false;

      if($this.hasClass('confirm')) {

        //Reset timeout
        clearTimeout(wc_freeagent_metabox.mark_completed_timeout);

        //Show loading indicator
        wc_freeagent_metabox.loading_indicator(wc_freeagent_metabox.$completeRow, '#fff');

        //Create request
        var data = {
          action: wc_freeagent_metabox.prefix+'mark_completed',
          nonce: wc_freeagent_metabox.nonce,
          order: wc_freeagent_metabox.order,
        };

        $.post(ajaxurl, data, function(response) {

          //Hide loading indicator
          wc_freeagent_metabox.$completeRow.unblock();

          //Show success/error messages
          wc_freeagent_metabox.show_messages(response);

          if(response.data.error) {
            //On success and error
            $this.fadeOut(function(){
              $this.text($this.data('trigger-value'));
              $this.removeClass('confirm');
              $this.fadeIn();
            });
          } else {
            //On success and error
            $this.fadeOut(function(){
              $this.text(response.data.completed);
              $this.addClass('completed');
              $this.fadeIn();
              $this.removeClass('confirm');
            });
          }

        });

      } else {
        wc_freeagent_metabox.mark_completed_timeout = setTimeout(function(){
          $this.fadeOut(function(){
            $this.text($this.data('trigger-value'));
            $this.fadeIn();
            $this.removeClass('confirm');
          });
        }, 5000);

        $this.addClass('confirm');
        $this.fadeOut(function(){
          $this.text('Are you sure?')
          $this.fadeIn();
        });
      }

    },
    void_invoice_timeout: false,
    void_invoice: function() {
      var $this = $(this);

      //Do nothing if already marked completed
      if($this.hasClass('confirm')) {

        //Reset timeout
        clearTimeout(wc_freeagent_metabox.void_invoice_timeout);

        //Show loading indicator
        wc_freeagent_metabox.loading_indicator(wc_freeagent_metabox.$voidRow, '#fff');

        //Set request route
        var request_suffix = wc_freeagent_metabox.is_receipt ? 'void_receipt' : 'void_invoice';

        //Create request
        var data = {
          action: wc_freeagent_metabox.prefix+request_suffix,
          nonce: wc_freeagent_metabox.nonce,
          order: wc_freeagent_metabox.order,
        };

        $.post(ajaxurl, data, function(response) {

          //Hide loading indicator
          wc_freeagent_metabox.$voidRow.unblock();

          //Show success/error messages
          wc_freeagent_metabox.show_messages(response);

          //On success and error
          if(response.data.error) {

          } else {

            wc_freeagent_metabox.$invoiceRow.slideUp();
            wc_freeagent_metabox.$completeRow.slideUp();
            wc_freeagent_metabox.$voidRow.slideUp(function(){
              $this.text(response.data.completed);
              $this.removeClass('confirm');
            });

            wc_freeagent_metabox.$generateContent.slideDown();
            wc_freeagent_metabox.$autoMsg.slideDown();

            //Reload page if we voided a receipt
            wc_freeagent_metabox.$voidedRow.find('strong').text(response.data.name);
            wc_freeagent_metabox.$voidedRow.find('a').attr('href', response.data.link);
            wc_freeagent_metabox.$voidedRow.slideDown();

          }

          //On success and error
          $this.fadeOut(function(){
            $this.text($this.data('trigger-value'));
            $this.fadeIn();
            $this.removeClass('confirm');
          });

        });

      } else {
        wc_freeagent_metabox.void_invoice_timeout = setTimeout(function(){
          $this.fadeOut(function(){
            $this.text($this.data('trigger-value'));
            $this.fadeIn();
            $this.removeClass('confirm');
          });
        }, 5000);

        $this.addClass('confirm');
        $this.fadeOut(function(){
          $this.text($this.data('question'))
          $this.fadeIn();
        });
      }

    },
    show_messages: function(response) {
      if(response.data.messages && response.data.messages.length > 0) {
        this.$messages.removeClass('wc-freeagent-metabox-messages-success');
        this.$messages.removeClass('wc-freeagent-metabox-messages-error');

        if(response.data.error) {
          this.$messages.addClass('wc-freeagent-metabox-messages-error');
        } else {
          this.$messages.addClass('wc-freeagent-metabox-messages-success');
        }

        $ul = this.$messages.find('ul');
        $ul.html('');

        $.each(response.data.messages, function(i, value) {
          var li = $('<li>')
          li.append(value);
          $ul.append(li);
        });
        this.$messages.slideDown();
      }
    },
    hide_message: function() {
      wc_freeagent_metabox.$messages.slideUp();
      return false;
    }
  }

  // Hide notice
	$( '.wc-freeagent-notice .wc-freeagent-hide-notice').on('click', function(e) {
		e.preventDefault();
		var el = $(this).closest('.wc-freeagent-notice');
		$(el).find('.wc-freeagent-wait').remove();
		$(el).append('<div class="wc-freeagent-wait"></div>');
		if ( $('.wc-freeagent-notice.updating').length > 0 ) {
			var button = $(this);
			setTimeout(function(){
				button.triggerHandler( 'click' );
			}, 100);
			return false;
		}
		$(el).addClass('updating');
		$.post( ajaxurl, {
				action: 	'wc_freeagent_hide_notice',
				security: 	$(this).data('nonce'),
				notice: 	$(this).data('notice'),
				remind: 	$(this).hasClass( 'remind-later' ) ? 'yes' : 'no'
		}, function(){
			$(el).removeClass('updating');
			$(el).fadeOut(100);
		});
	});

  //Background generate actions
  var wc_freeagent_background_actions = {
    $menu_bar_item: $('#wp-admin-bar-wc-freeagent-bg-generate-loading'),
    $link_stop: $('#wc-freeagent-bg-generate-stop'),
    $link_refresh: $('#wc-freeagent-bg-generate-refresh'),
    finished: false,
    nonce: '',
    init: function() {
      this.$link_stop.on( 'click', this.stop );
      this.$link_refresh.on( 'click', this.reload_page );

      //Store nonce
      this.nonce = this.$link_stop.data('nonce');

      //Refresh status every 5 second
      var refresh_action = this.refresh;
      setTimeout(refresh_action, 5000);

    },
    reload_page: function() {
      location.reload();
      return false;
    },
    stop: function() {
      var data = {
        action: 'wc_freeagent_bg_generate_stop',
        nonce: wc_freeagent_background_actions.nonce,
      }

      $.post(ajaxurl, data, function(response) {
        wc_freeagent_background_actions.mark_stopped();
      });
      return false;
    },
    refresh: function() {
      var data = {
        action: 'wc_freeagent_bg_generate_status',
        nonce: wc_freeagent_background_actions.nonce,
      }

      if(!wc_freeagent_background_actions.finished) {
        $.post(ajaxurl, data, function(response) {
          if(response.data.finished) {
            wc_freeagent_background_actions.mark_finished();
          } else {
            //Repeat after 5 seconds
            setTimeout(wc_freeagent_background_actions.refresh, 5000);
          }

        });
      }
    },
    mark_finished: function() {
      this.finished = true;
      this.$menu_bar_item.addClass('finished');
    },
    mark_stopped: function() {
      this.mark_finished();
      this.$menu_bar_item.addClass('stopped');
    }
  }

  //Bulk actions
  var wc_freeagent_bulk_actions = {
    init: function() {
      var printAction = $('#wc-freeagent-bulk-print');
      var downloadAction = $('#wc-freeagent-bulk-download');
      printAction.on( 'click', this.printInvoices );
      if(printAction.length) {
        printAction.trigger('click');
      }
    },
    printInvoices: function() {
      var pdf_url = $(this).data('pdf');
      if (typeof printJS === 'function') {
        printJS(pdf_url);
        return false;
      }
    }
  }

  //Metabox
  if($('#wc_freeagent_metabox').length) {
    wc_freeagent_metabox.init();
  }

  //Init settings page
  if($('.wc-freeagent-section-auth').length || $('#woocommerce_wc_freeagent_section_invoice').length) {
    wc_freeagent_settings.init();
  }

  //Init background generate loading indicator
  if($('#wp-admin-bar-wc-freeagent-bg-generate-loading').length) {
    wc_freeagent_background_actions.init();
  }

  //Init bulk actions
  if($('.wc-freeagent-bulk-actions').length) {
    wc_freeagent_bulk_actions.init();
  }

});
