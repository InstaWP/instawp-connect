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
            instawp_deployer_api_call_interval = null;

        if (el_btn_go_live.data('is_live')) {
            window.open(el_btn_go_live.data('cloudways'), '_blank');
            return;
        }


        // Disable the button
        el_btn_go_live.addClass('disabled');

        // Enable the loader
        el_go_live_message.html('Connecting to the server.');
        el_go_live_progress.html('0%');
        el_go_live_loader.addClass('visible');

        instawp_deployer_api_call_interval = setInterval(function () {

            let go_live_step = parseInt(el_go_live_step.val());

            console.log(go_live_step);

            if (el_cloudways_wrap.hasClass('doing-ajax')) {
                return;
            }

            if (go_live_step === 1) {

                console.log('Cleaning previous backup.');

                $.ajax({
                    type: 'POST',
                    url: go_live_obj.ajax_url,
                    context: this,
                    data: {
                        'action': 'instawp_go_live_clean',
                    },
                    beforeSend: function () {
                        el_cloudways_wrap.addClass('doing-ajax');
                    },
                    success: function (response) {

                        el_cloudways_wrap.removeClass('doing-ajax');

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

                $.ajax({
                    type: 'POST',
                    url: go_live_obj.ajax_url,
                    context: this,
                    data: {
                        'action': 'instawp_go_live_restore_init',
                    },
                    beforeSend: function () {
                        el_cloudways_wrap.addClass('doing-ajax');
                    },
                    success: function (response) {

                        console.log(response.data);

                        el_go_live_message.html(response.data.backup.message);
                        el_go_live_progress.html(response.data.backup.progress + '%');

                        if (response.success) {
                            el_cloudways_wrap.removeClass('doing-ajax');
                        }

                        if (response.success && response.data.backup.progress >= 100) {
                            el_field_restore_id.val(response.data.migrate_id);
                            el_go_live_step.val(3);
                        } else {
                            el_go_live_step.val(2);
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
                        'migrate_id': el_field_restore_id.val(),
                    },
                    beforeSend: function () {
                        el_cloudways_wrap.addClass('doing-ajax');
                    },
                    success: function (response) {

                        console.log(response);

                        if (response.success) {

                            el_cloudways_wrap.removeClass('doing-ajax');

                            if (response.data.migrate.progress === 100) {

                                console.log('Restore status api completed.');

                                // Clearing the loop
                                clearInterval(instawp_deployer_api_call_interval);

                                el_go_live_progress.fadeOut(100);
                                el_go_live_loader.find('img').fadeOut(100);
                                el_go_live_message.html('✅' + ' ' + response.data.migrate.message);

                                let site_detail = response.data.site_detail;

                                console.log(site_detail);

                                // Update the button
                                el_btn_go_live.html('Login to the website').data('cloudways', site_detail.auto_login_url).removeClass('disabled').data('is_live', true);
                                el_btn_go_live.removeClass('disabled').data('is_live', true);

                                // Display manage account link
                                el_manage_account_link.fadeIn();
                            } else {
                                el_go_live_step.val(3);
                                el_go_live_message.html(response.data.migrate.message);
                                el_go_live_progress.html(response.data.migrate.progress + '%');
                            }
                        }

                        el_go_live_step.val(3);
                    }
                });
            }
        }, 3000);
    });


})(jQuery, document, instawp_ajax_go_live_obj);