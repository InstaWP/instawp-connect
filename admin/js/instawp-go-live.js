/**
 * Cloudways integration script
 */

(function ($, document, go_live_obj) {
    'use strict';

    $(document).on('click', '.instawp-go-live-wrap .instawp-btn-go-live', function () {

        let el_btn_go_live = $(this),
            el_cloudways_wrap = $('.instawp-go-live-wrap'),
            el_go_live_loader = el_cloudways_wrap.parent().find('.go-live-loader'),
            el_go_live_message = el_cloudways_wrap.find('.go-live-status-message'),
            el_go_live_progress = el_cloudways_wrap.find('.go-live-status-progress'),
            el_manage_account_link = el_cloudways_wrap.find('.manage-account-link'),
            el_manage_sites = el_cloudways_wrap.find('.instawp-manage-sites'),
            loop_init_time = 0,
            go_live_messages = [
                'Connecting to Cloudways',
                'Creating account on Cloudways',
                'Creating website on remote',
                'Configuring live website',
                'Migrating the website to live',
                'Finalizing the live website',
            ];

        if (el_btn_go_live.data('is_live')) {
            return;
        }

        // Disable the button
        // el_btn_go_live.addClass('disabled');

        // Enable the loader
        // el_go_live_loader.addClass('visible');

        // Displaying dummy message
        // $.each(go_live_messages, function (index, message) {
        //     setTimeout(function () {
        //
        //         let progress = Math.round(((index / go_live_messages.length) * 100));
        //
        //         el_go_live_message.html(message);
        //         el_go_live_progress.html(progress + '%');
        //     }, loop_init_time);
        //     loop_init_time += 1500;
        // });


        $.ajax({
            type: 'POST',
            url: go_live_obj.ajax_url,
            context: this,
            data: {
                'action': 'instawp_process_go_live',
            },
            success: function (response) {

                console.log(response.success)
                if (response.success) {


                    // Progress is now 100%
                    // el_go_live_progress.html('100%');

                    // Update the button
                    // el_btn_go_live.html('Site is Live').removeClass('disabled').data('is_live', true);

                    // Hide the loader
                    // el_go_live_loader.removeClass('visible');

                    // Display manage account link
                    // el_manage_account_link.fadeIn();

                    // Display manage sites section
                    // el_manage_sites.fadeIn();
                }
            }
        });
    });

})(jQuery, document, instawp_ajax_go_live_obj);