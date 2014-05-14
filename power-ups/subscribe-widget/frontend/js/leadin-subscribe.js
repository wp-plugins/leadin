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
                className: 'leadin-subscribe ' + $('#leadin-subscribe-vex-class').val(),
                message: $('#leadin-subscribe-heading').val(),
                input: '<input id="leadin-subscribe-email" name="email" type="email" placeholder="Email address" required />' +
                    (($('#leadin-subscribe-name-fields').val()==0) ? '' : '<input id="leadin-subscribe-fname" name="fname" type="text" placeholder="First Name" required /><input id="leadin-subscribe-lname" name="lname" type="text" placeholder="Last Name" required />') +
                    (($('#leadin-subscribe-phone-field').val()==0) ? '' : '<input id="leadin-subscribe-phone" name="phone" type="tel" placeholder="Phone" required />'),
                buttons: [$.extend({}, vex.dialog.buttons.YES, { text: ( $('#leadin-subscribe-btn-label').val() ? $('#leadin-subscribe-btn-label').val() : 'SUBSCRIBE' ) })],
                onSubmit: function ( data )
                {
                    $('.vex-dialog-form').fadeOut(300, function ( e ) {
                        $('.vex-dialog-form').html(
                            '<div class="vex-close"></div>' + 
                            '<h3>Thanks!<br>You should receive a confirmation email in your inbox shortly.</h3>' + 
                            '<div>' +
                                '<span class="powered-by">Powered by LeadIn</span>' + 
                                '<a href="http://leadin.com/wordpress-subscribe-widget/?utm_campaign=subscribe_widget&utm_medium=email&utm_source=' + window.location.host + '"><img alt="LeadIn" height="20px" width="99px" src="http://leadin.com/wp-content/themes/LeadIn-WP-Theme/library/images/logos/Leadin_logo@2x.png" alt="leadin.com"/></a>' +
                            '</div>'
                        ).css('text-align', 'center').fadeIn(250);
                    });

                    leadin_submit_form($('.leadin-subscribe form'), $, 'subscribe');
                    $.cookie("li_subscribe", 'ignore', {path: "/", domain: ""});
                    return false;
                },
                callback: function(data) {
                    if (data === false) {
                        $.cookie("li_subscribe", 'ignore', {path: "/", domain: ""});
                    }
                    
                    $.cookie("li_subscribe", 'ignore', {path: "/", domain: ""});
                }
            });

            //leadin_subscribe_show();

            $('.leadin-subscribe form.vex-dialog-form').append('<a href="http://leadin.com/pop-subscribe-form-plugin-wordpress/?utm_campaign=subscribe_widget&utm_medium=widget&utm_source=' + document.URL + '" id="leadin-subscribe-powered-by" class="leadin-subscribe-powered-by">Powered by LeadIn</a>');
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