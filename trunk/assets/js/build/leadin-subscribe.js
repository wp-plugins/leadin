(function() {
  var vexFactory;

  vexFactory = function($) {
    var animationEndSupport, vex;
    animationEndSupport = false;
    $(function() {
      var s;
      s = (document.body || document.documentElement).style;
      animationEndSupport = s.animation !== void 0 || s.WebkitAnimation !== void 0 || s.MozAnimation !== void 0 || s.MsAnimation !== void 0 || s.OAnimation !== void 0;
      return $(window).bind('keyup.vex', function(event) {
        if (event.keyCode === 27) {
          return vex.closeByEscape();
        }
      });
    });
    return vex = {
      globalID: 1,
      animationEndEvent: 'animationend webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend',
      baseClassNames: {
        vex: 'vex',
        content: 'vex-content',
        overlay: 'vex-overlay',
        close: 'vex-close',
        closing: 'vex-closing',
        open: 'vex-open'
      },
      defaultOptions: {
        content: '',
        showCloseButton: true,
        escapeButtonCloses: true,
        overlayClosesOnClick: true,
        appendLocation: 'body',
        className: '',
        css: {},
        overlayClassName: '',
        overlayCSS: {},
        contentClassName: '',
        contentCSS: {},
        closeClassName: '',
        closeCSS: {}
      },
      open: function(options) {
        options = $.extend({}, vex.defaultOptions, options);
        options.id = vex.globalID;
        vex.globalID += 1;
        options.$vex = $('<div>').addClass(vex.baseClassNames.vex).addClass(options.className).css(options.css).data({
          vex: options
        });
        options.$vexOverlay = $('<div>').addClass(vex.baseClassNames.overlay).addClass(options.overlayClassName).css(options.overlayCSS).data({
          vex: options
        });
        if (options.overlayClosesOnClick) {
          options.$vexOverlay.bind('click.vex', function(e) {
            if (e.target !== this) {
              return;
            }
            return vex.close($(this).data().vex.id);
          });
        }
        options.$vex.append(options.$vexOverlay);
        options.$vexContent = $('<div>').addClass(vex.baseClassNames.content).addClass(options.contentClassName).css(options.contentCSS).append(options.content).data({
          vex: options
        });
        options.$vex.append(options.$vexContent);
        if (options.showCloseButton) {
          options.$closeButton = $('<div>').addClass(vex.baseClassNames.close).addClass(options.closeClassName).css(options.closeCSS).data({
            vex: options
          }).bind('click.vex', function() {
            return vex.close($(this).data().vex.id);
          });
          options.$vexContent.append(options.$closeButton);
        }
        $(options.appendLocation).append(options.$vex);
        vex.setupBodyClassName(options.$vex);
        if (options.afterOpen) {
          options.afterOpen(options.$vexContent, options);
        }
        setTimeout((function() {
          return options.$vexContent.trigger('vexOpen', options);
        }), 0);
        return options.$vexContent;
      },
      getAllVexes: function() {
        return $("." + vex.baseClassNames.vex + ":not(\"." + vex.baseClassNames.closing + "\") ." + vex.baseClassNames.content);
      },
      getVexByID: function(id) {
        return vex.getAllVexes().filter(function() {
          return $(this).data().vex.id === id;
        });
      },
      close: function(id) {
        var $lastVex;
        if (!id) {
          $lastVex = vex.getAllVexes().last();
          if (!$lastVex.length) {
            return false;
          }
          id = $lastVex.data().vex.id;
        }
        return vex.closeByID(id);
      },
      closeAll: function() {
        var ids;
        ids = vex.getAllVexes().map(function() {
          return $(this).data().vex.id;
        }).toArray();
        if (!(ids != null ? ids.length : void 0)) {
          return false;
        }
        $.each(ids.reverse(), function(index, id) {
          return vex.closeByID(id);
        });
        return true;
      },
      closeByID: function(id) {
        var $vex, $vexContent, beforeClose, close, options;
        $vexContent = vex.getVexByID(id);
        if (!$vexContent.length) {
          return;
        }
        $vex = $vexContent.data().vex.$vex;
        options = $.extend({}, $vexContent.data().vex);
        beforeClose = function() {
          if (options.beforeClose) {
            return options.beforeClose($vexContent, options);
          }
        };
        close = function() {
          $vexContent.trigger('vexClose', options);
          $vex.remove();
          if (options.afterClose) {
            return options.afterClose($vexContent, options);
          }
        };
        if (animationEndSupport) {
          beforeClose();
          $vex.unbind(vex.animationEndEvent).bind(vex.animationEndEvent, function() {
            return close();
          }).addClass(vex.baseClassNames.closing);
        } else {
          beforeClose();
          close();
        }
        return true;
      },
      closeByEscape: function() {
        var $lastVex, id, ids;
        ids = vex.getAllVexes().map(function() {
          return $(this).data().vex.id;
        }).toArray();
        if (!(ids != null ? ids.length : void 0)) {
          return false;
        }
        id = Math.max.apply(Math, ids);
        $lastVex = vex.getVexByID(id);
        if ($lastVex.data().vex.escapeButtonCloses !== true) {
          return false;
        }
        return vex.closeByID(id);
      },
      setupBodyClassName: function($vex) {
        return $vex.bind('vexOpen.vex', function() {
          return $('body').addClass(vex.baseClassNames.open);
        }).bind('vexClose.vex', function() {
          if (!vex.getAllVexes().length) {
            return $('body').removeClass(vex.baseClassNames.open);
          }
        });
      },
      hideLoading: function() {
        return $('.vex-loading-spinner').remove();
      },
      showLoading: function() {
        vex.hideLoading();
        return $('body').append("<div class=\"vex-loading-spinner " + vex.defaultOptions.className + "\"></div>");
      }
    };
  };

  if (typeof define === 'function' && define.amd) {
    define(['jquery'], vexFactory);
  } else if (typeof exports === 'object') {
    module.exports = vexFactory(require('jquery'));
  } else {
    window.vex = vexFactory(jQuery);
  }

}).call(this);

(function() {
  var vexDialogFactory;

  vexDialogFactory = function($, vex) {
    var $formToObject, dialog;
    if (vex == null) {
      return $.error('Vex is required to use vex.dialog');
    }
    $formToObject = function($form) {
      var object;
      object = {};
      $.each($form.serializeArray(), function() {
        if (object[this.name]) {
          if (!object[this.name].push) {
            object[this.name] = [object[this.name]];
          }
          return object[this.name].push(this.value || '');
        } else {
          return object[this.name] = this.value || '';
        }
      });
      return object;
    };
    dialog = {};
    dialog.buttons = {
      YES: {
        text: 'OK',
        type: 'submit',
        className: 'vex-dialog-button-primary'
      },
      NO: {
        text: 'Cancel',
        type: 'button',
        className: 'vex-dialog-button-secondary',
        click: function($vexContent, event) {
          $vexContent.data().vex.value = false;
          return vex.close($vexContent.data().vex.id);
        }
      }
    };
    dialog.defaultOptions = {
      callback: function(value) {},
      afterOpen: function() {},
      message: 'Message',
      input: "<input name=\"vex\" type=\"hidden\" value=\"_vex-empty-value\" />",
      value: false,
      buttons: [dialog.buttons.YES, dialog.buttons.NO],
      showCloseButton: false,
      onSubmit: function(event) {
        var $form, $vexContent;
        $form = $(this);
        $vexContent = $form.parent();
        event.preventDefault();
        event.stopPropagation();
        $vexContent.data().vex.value = dialog.getFormValueOnSubmit($formToObject($form));
        return vex.close($vexContent.data().vex.id);
      },
      focusFirstInput: true
    };
    dialog.defaultAlertOptions = {
      message: 'Alert',
      buttons: [dialog.buttons.YES]
    };
    dialog.defaultConfirmOptions = {
      message: 'Confirm'
    };
    dialog.open = function(options) {
      var $vexContent;
      options = $.extend({}, vex.defaultOptions, dialog.defaultOptions, options);
      options.content = dialog.buildDialogForm(options);
      options.beforeClose = function($vexContent) {
        return options.callback($vexContent.data().vex.value);
      };
      $vexContent = vex.open(options);
      if (options.focusFirstInput) {
        $vexContent.find('input[type="submit"], textarea, input[type="date"], input[type="datetime"], input[type="datetime-local"], input[type="email"], input[type="month"], input[type="number"], input[type="password"], input[type="search"], input[type="tel"], input[type="text"], input[type="time"], input[type="url"], input[type="week"]').first().focus();
      }
      return $vexContent;
    };
    dialog.alert = function(options) {
      if (typeof options === 'string') {
        options = {
          message: options
        };
      }
      options = $.extend({}, dialog.defaultAlertOptions, options);
      return dialog.open(options);
    };
    dialog.confirm = function(options) {
      if (typeof options === 'string') {
        return $.error('dialog.confirm(options) requires options.callback.');
      }
      options = $.extend({}, dialog.defaultConfirmOptions, options);
      return dialog.open(options);
    };
    dialog.prompt = function(options) {
      var defaultPromptOptions;
      if (typeof options === 'string') {
        return $.error('dialog.prompt(options) requires options.callback.');
      }
      defaultPromptOptions = {
        message: "<label for=\"vex\">" + (options.label || 'Prompt:') + "</label>",
        input: "<input name=\"vex\" type=\"text\" class=\"vex-dialog-prompt-input\" placeholder=\"" + (options.placeholder || '') + "\"  value=\"" + (options.value || '') + "\" />"
      };
      options = $.extend({}, defaultPromptOptions, options);
      return dialog.open(options);
    };
    dialog.buildDialogForm = function(options) {
      var $form, $input, $message;
      $form = $('<form class="vex-dialog-form" />');
      $message = $('<div class="vex-dialog-message" />');
      $input = $('<div class="vex-dialog-input" />');
      $form.append($message.append(options.message)).append($input.append(options.input)).append(dialog.buttonsToDOM(options.buttons)).bind('submit.vex', options.onSubmit);
      return $form;
    };
    dialog.getFormValueOnSubmit = function(formData) {
      if (formData.vex || formData.vex === '') {
        if (formData.vex === '_vex-empty-value') {
          return true;
        }
        return formData.vex;
      } else {
        return formData;
      }
    };
    dialog.buttonsToDOM = function(buttons) {
      var $buttons;
      $buttons = $('<div class="vex-dialog-buttons" />');
      $.each(buttons, function(index, button) {
        return $buttons.append($("<input type=\"" + button.type + "\" />").val(button.text).addClass(button.className + ' vex-dialog-button ' + (index === 0 ? 'vex-first ' : '') + (index === buttons.length - 1 ? 'vex-last ' : '')).bind('click.vex', function(e) {
          if (button.click) {
            return button.click($(this).parents("." + vex.baseClassNames.content), e);
          }
        }));
      });
      return $buttons;
    };
    return dialog;
  };

  if (typeof define === 'function' && define.amd) {
    define(['jquery', 'vex'], vexDialogFactory);
  } else if (typeof exports === 'object') {
    module.exports = vexDialogFactory(require('jquery'), require('vex'));
  } else {
    window.vex.dialog = vexDialogFactory(window.jQuery, window.vex);
  }

}).call(this);

var ignore_date = new Date();
ignore_date.setTime(ignore_date.getTime() + (60 * 60 * 24 * 14 * 1000));

jQuery(document).ready( function ( $ ) {
    var li_subscribe_flag = $.cookie('li_subscribe');

    if ( ! li_subscribe_flag )
    {
        leadin_check_visitor_status($.cookie("li_hash"), function ( data ) {
            if ( data != 'vex_set' )
            {
                $.cookie("li_subscribe", 'show', {path: "/", domain: ""});
                bind_leadin_subscribe_widget(
                    $('#leadin-subscribe-heading').val(),
                    $('#leadin-subscribe-text').val(),
                    $('#leadin-subscribe-name-fields').val(),
                    $('#leadin-subscribe-phone-field').val(),
                    $('#leadin-subscribe-btn-label').val(),
                    $('#leadin-subscribe-btn-color').val(),
                    $('#leadin-subscribe-vex-class').val(),
                    $('#leadin-subscribe-confirmation').val(),
                    $('#leadin-subscribe-mobile-popup').val()
                );
            }
            else
            {
                $.cookie("li_subscribe", 'ignore', {path: "/", domain: "", expires: ignore_date});
            }
        });
    }
    else
    {
        if ( li_subscribe_flag == 'show' )
        {
            bind_leadin_subscribe_widget(
                $('#leadin-subscribe-heading').val(),
                $('#leadin-subscribe-text').val(),
                $('#leadin-subscribe-name-fields').val(),
                $('#leadin-subscribe-phone-field').val(),
                $('#leadin-subscribe-btn-label').val(),
                $('#leadin-subscribe-btn-color').val(),
                $('#leadin-subscribe-vex-class').val(),
                $('#leadin-subscribe-confirmation').val(),
                $('#leadin-subscribe-mobile-popup').val()
            );   
        }
    }
});

function bind_leadin_subscribe_widget ( lis_heading, lis_desc, lis_show_names, lis_show_phone, lis_btn_label, lis_btn_color, lis_vex_class, lis_confirmation, lis_mobile_popup ) 
{

    lis_heading         = ( lis_heading ? lis_heading : 'Sign up for email updates' );
    lis_desc            = ( lis_desc ? lis_desc : '' );
    lis_btn_label       = ( lis_btn_label ? lis_btn_label : 'SUBSCRIBE' );
    lis_btn_color       = ( lis_btn_color ? lis_btn_color : 'leadin-popup-color-blue' );
    lis_vex_class       = ( lis_vex_class ? lis_vex_class : 'vex-theme-bottom-right-corner' );
    lis_confirmation    = ( lis_confirmation ? lis_confirmation : 1 );
    lis_mobile_popup    = ( lis_mobile_popup ? lis_mobile_popup : 1 );

    (function(){
        var $ = jQuery;
        var subscribe = {};

        subscribe.vex = undefined;

        subscribe.init = function() {
            if ($(window).scrollTop() + $(window).height() > $(document).height() / 2) {
                subscribe.open();
            } else {
                //subscribe.close();
            }
            
            $(window).scroll(function() {
                if ($(window).scrollTop() + $(window).height() > $(document).height() / 2) {
                    subscribe.open();
                } else {
                    //subscribe.close();
                }
            });
        };

        subscribe.open = function() {
            if (subscribe.vex) {
                return subscribe._open();
            }

            subscribe.vex = vex.dialog.open({
                showCloseButton: true,
                className: 'leadin-subscribe ' + lis_vex_class + ' ' + lis_btn_color + ( lis_mobile_popup == 1 ? '' : ' leadin-subscribe-mobile-hide' ),
                message: '<h4>' + lis_heading + '</h4>' + '<p>' + lis_desc + '</p>',
                input: '<input id="leadin-subscribe-email" name="email" type="email" placeholder="Email address" />' +
                    ( parseInt(lis_show_names) ? '<input id="leadin-subscribe-fname" name="fname" type="text" placeholder="First Name" /><input id="leadin-subscribe-lname" name="lname" type="text" placeholder="Last Name"  />' : '' ) +
                    ( parseInt(lis_show_phone) ? '<input id="leadin-subscribe-phone" name="phone" type="tel" placeholder="Phone" />' : '' ),
                buttons: [$.extend({}, vex.dialog.buttons.YES, { text: ( lis_btn_label ? lis_btn_label : 'SUBSCRIBE' ) })],
                onSubmit: function ( data )
                {
                    $subscribe_form = $(this);
                    $subscribe_form.find('input.error').removeClass('error');
                    var form_validated = true;

                    $subscribe_form.find('input').each( function ( e ) {
                        var $input = $(this);
                        if ( ! $input.val() )
                        {
                            $input.addClass('error');
                            form_validated = false;
                        }
                    });

                    if ( ! form_validated )
                        return false;

                    $('.vex-dialog-form').fadeOut(300, function ( e ) {
                        $('.vex-dialog-form').html(
                            '<div class="vex-close"></div>' + 
                            ( parseInt(lis_confirmation) ? '<h3>Thanks!<br>You should receive a confirmation email in your inbox shortly.</h3>' : '<h3>Thanks!<br>We received your submission.</h3>' ) + 
                            '<div id="powered-by-leadin-thank-you">' +
                                '<span class="powered-by">Powered by</span><br>' + 
                                '<a href="http://leadin.com/wordpress-subscribe-widget?utm_source=virality&utm_medium=referral&utm_term=' + window.location.host + '&utm_content=e11&utm_campaign=subscribe%20widget"><img alt="Leadin" height="20px" width="99px" src="' + document.location.protocol + '//leadin.com/wp-content/themes/LeadIn-WP-Theme/library/images/logos/Leadin_logo@2x.png" alt="leadin.com"/></a>' +
                            '</div>'
                        ).css('text-align', 'center').fadeIn(250);
                    });

                    leadin_submit_form($('.leadin-subscribe form'), $);
                    $.cookie("li_subscribe", 'ignore', {path: "/", domain: "", expires: ignore_date});
                    return false;
                },
                callback: function(data) {
                    if (data === false) {
                        $.cookie("li_subscribe", 'ignore', {path: "/", domain: "", expires: ignore_date});
                    }
                    
                    $.cookie("li_subscribe", 'ignore', {path: "/", domain: "", expires: ignore_date});
                }
            });

            $('.leadin-subscribe form.vex-dialog-form').append('<a href="http://leadin.com/wordpress-subscribe-widget?utm_source=virality&utm_medium=referral&utm_term=' + window.location.host + '&utm_content=e11&utm_campaign=subscribe%20widget" id="leadin-subscribe-powered-by" class="leadin-subscribe-powered-by">Powered by Leadin</a>');
        };

        subscribe._open = function() {
            subscribe.vex.parent().removeClass('vex-closing');
        }

        subscribe.close = function() {

            if (!subscribe.vex) {
                return;
            }
            subscribe.vex.parent().addClass('vex-closing');
        }

        subscribe.init();
        window.subscribe = subscribe;
    })();
}

function leadin_subscribe_show ()
{
    jQuery.ajax({
        type: 'POST',
        url: li_ajax.ajax_url,
        data: {
            "action": "leadin_subscribe_show"
        },
        success: function(data){
        },
        error: function ( error_data ) {
            //alert(error_data);
        }
    });
}

function leadin_get_parameter_by_name ( name ) 
{
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(location.search);
    return results == null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}