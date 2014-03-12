jQuery(document).ready( function ( $ ) {
    var li_subscribe_flag = $.cookie('li_subscribe');

    if ( !leadin_subscribe_check_mobile($) )
    {
        if ( !li_subscribe_flag )
        {
            leadin_check_visitor_status($.cookie("li_hash"), function ( data ) {
                if ( data != 'subscribe' )
                {
                    $.cookie("li_subscribe", 'show', {path: "/", domain: ""});
                    bind_leadin_subscribe_widget();
                }
                else
                {
                    $.cookie("li_subscribe", 'ignore', {path: "/", domain: ""});
                }
            });
        }
        else
        {
            if ( li_subscribe_flag == 'show' )
                bind_leadin_subscribe_widget();
        }
    }
});

function bind_leadin_subscribe_widget () 
{
    (function(){
        var $ = jQuery;
        var subscribe = {};

        subscribe.vex = undefined;

        subscribe.init = function() {
            $(window).scroll(function() {
                if ($(window).scrollTop() + $(window).height() > $(document).height() / 2) {
                    subscribe.open();
                } else {
                    subscribe.close();
                }
            });
        };

        subscribe.open = function() {
            if (subscribe.vex) {
                return subscribe._open();
            }

            subscribe.vex = vex.dialog.open({
                showCloseButton: true,
                className: 'leadin-subscribe vex-theme-bottom-right-corner',
                message: $('#leadin-subscribe-heading').val(),
                input: '' +
                    '<input id="leadin-subscribe-email" name="email" type="email" placeholder="Email address" required />' +
                    '<input id="leadin-subscribe-first-name" name="firstName" type="text" placeholder="First name" required/>' +
                    '<input id="leadin-subscribe-last-name" name="lastName" type="text" placeholder="Last name" required/>',
                buttons: [$.extend({}, vex.dialog.buttons.YES, { text: 'Subscribe' })],
                callback: function(data) {
                    if (data === false) {
                        $.cookie("li_subscribe", 'ignore', {path: "/", domain: ""});
                        return;
                    }
                    
                    leadin_submit_form($('.leadin-subscribe form'), $, 'subscribe');
                    $.cookie("li_subscribe", 'ignore', {path: "/", domain: ""});
                }
            });

            $('.leadin-subscribe form.vex-dialog-form').append('<a href="http://leadin.com/pop-subscribe-form-plugin-wordpress/?source=widget&referrer=' + document.URL + '" id="leadin-subscribe-powered-by" class="leadin-subscribe-powered-by">Powered by LeadIn</a>');
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

function leadin_subscribe_check_mobile( $ )
{
    var is_mobile = false;

    if ( $('#leadin-subscribe-mobile-check').css('display')=='none' )
        is_mobile = true;

    return is_mobile;
}