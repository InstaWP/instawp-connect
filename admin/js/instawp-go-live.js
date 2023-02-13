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
            el_field_restore_id = el_cloudways_wrap.find('#instawp_go_live_restore_id'),
            el_go_live_step = el_cloudways_wrap.find('#instawp_go_live_step'),
            finished_doing_api_call_interval = null,
            go_live_step_completed = 0;

        if (el_btn_go_live.data('is_live')) {
            window.open(el_btn_go_live.data('cloudways'), '_blank');
            return;
        }

        // Disable the button
        el_btn_go_live.addClass('disabled');

        // Enable the loader
        el_go_live_message.html('Connecting to Cloudways');
        el_go_live_progress.html('0%');
        el_go_live_loader.addClass('visible');

        finished_doing_api_call_interval = setInterval(function () {

            let go_live_step = parseInt(el_go_live_step.val());

            console.log(go_live_step);

            if (go_live_step === 1) {

                console.log('Cleaning previous backup.');

                $.ajax({
                    type: 'POST',
                    url: go_live_obj.ajax_url,
                    context: this,
                    data: {
                        'action': 'instawp_go_live_clean',
                    },
                    success: function (response) {

                        el_go_live_step.val(2);
                        el_go_live_message.html(response.data.message);
                        el_go_live_progress.html(response.data.progress + '%');

                        console.log('Cleaning previous backup completed.');
                    },
                    error: function () {
                        el_go_live_step.val(1);
                    }
                });

            } else if (go_live_step === 2) {

                console.log('Going to hit restore-init');

                $.ajax({
                    type: 'POST',
                    url: go_live_obj.ajax_url,
                    context: this,
                    data: {
                        'action': 'instawp_go_live_restore_init',
                    },
                    success: function (response) {

                        console.log(response);

                        el_go_live_message.html(response.data.message);
                        el_go_live_progress.html(response.data.progress + '%');

                        if (response.success) {

                            el_field_restore_id.val(response.data.restore_id);
                            el_go_live_step.val(3);

                            console.log('restore-init completed.');
                        } else {
                            el_go_live_step.val(2);
                            console.log('restore-init failed, will try again.');
                        }
                    },
                    error: function (request, status, error) {
                        el_go_live_step.val(2);
                        console.log({
                            'request': request,
                            'status': status,
                            'error': error
                        });
                    }
                });

            } else if (go_live_step === 3) {

                console.log('Going to hit `get_restore_status`');

                $.ajax({
                    type: 'POST',
                    url: go_live_obj.ajax_url,
                    context: this,
                    data: {
                        'action': 'instawp_go_live_restore_status',
                        'restore_id': el_field_restore_id.val(),
                    },
                    success: function (response) {

                        console.log(response);

                        if (response.success) {

                            if (response.data.progress === 100) {

                                console.log('Restore status api completed.');

                                // Clearing the loop
                                clearInterval(finished_doing_api_call_interval);

                                el_go_live_progress.fadeOut(100);
                                el_go_live_loader.find('img').fadeOut(100);
                                el_go_live_message.html('✅' + ' ' + response.data.message);

                                let wp_details = response.data.wp[0];

                                console.log(wp_details);

                                // Update the button
                                el_btn_go_live.html('Login to Cloudways Site').data('cloudways', wp_details.wp_admin_url).removeClass('disabled').data('is_live', true);
                                el_btn_go_live.removeClass('disabled').data('is_live', true);

                                // Display manage account link
                                el_manage_account_link.fadeIn();
                            } else {
                                el_go_live_step.val(3);
                                el_go_live_message.html(response.data.message);
                                el_go_live_progress.html(response.data.progress + '%');
                            }
                        }

                        el_go_live_step.val(3);
                    }
                });
            }
        }, 3000);
    });


})(jQuery, document, instawp_ajax_go_live_obj);