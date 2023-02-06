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
            el_ajax_nonce_field = el_cloudways_wrap.find('#instawp_ajax_nonce_field'),
            finished_doing_api_call_interval = null;

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

        let task_id = null, go_live_step_completed = 0;

        finished_doing_api_call_interval = setInterval(function () {

            console.log(go_live_step_completed);

            if (go_live_step_completed === 0) {
                $.ajax({
                    type: 'POST',
                    url: go_live_obj.ajax_url,
                    context: this,
                    data: {
                        'action': 'instawp_prepare_backup',
                        'backup': '{"ismerge":"1","backup_files":"files+db","local":"1"}',
                        'options': [],
                        'nonce': el_ajax_nonce_field.val(),
                    },
                    success: function (response) {

                        let response_arr = $.parseJSON(response);

                        if (response_arr.result === 'success') {
                            task_id = response_arr.task_id;
                            go_live_step_completed = 1;
                        }
                    }
                });
            } else if (go_live_step_completed === 1) {
                $.ajax({
                    type: 'POST',
                    url: go_live_obj.ajax_url,
                    context: this,
                    data: {
                        'action': 'instawp_backup_now',
                        'task_id': task_id,
                        'nonce': el_ajax_nonce_field.val(),
                    },
                    success: function (response) {

                        let response_arr = $.parseJSON(response);

                        if (response_arr.result === 'success') {
                            task_id = response_arr.task_id;
                            go_live_step_completed = 2;
                        }
                    }
                });
            } else if (go_live_step_completed === 2) {
                $.ajax({
                    type: 'POST',
                    url: go_live_obj.ajax_url,
                    context: this,
                    data: {
                        'action': 'instawp_go_live_restore',
                    },
                    success: function (response) {
                        if (response.success) {
                            console.log(response.data);
                        }
                    }
                });
                go_live_step_completed = 3;
            } else if (go_live_step_completed === 3) {

                $.ajax({
                    type: 'POST',
                    url: go_live_obj.ajax_url,
                    context: this,
                    data: {
                        'action': 'instawp_go_live_restore_status',
                    },
                    success: function (response) {

                        if (response.success) {

                            if (response.data.progress === 100) {

                                // Clearing the loop
                                clearInterval(finished_doing_api_call_interval);

                                el_go_live_progress.fadeOut(100);
                                el_go_live_loader.find('img').fadeOut(100);
                                el_go_live_message.html('✅' + ' ' + response.data.message);

                                // Update the button
                                el_btn_go_live.html('Magic Login').removeClass('disabled').data('is_live', true);
                                el_btn_go_live.removeClass('disabled').data('is_live', true);

                                // Display manage account link
                                el_manage_account_link.fadeIn();
                            } else {
                                el_go_live_message.html(response.data.message);
                                el_go_live_progress.html(response.data.progress + '%');
                            }
                        }
                    }
                });
            }
        }, 3000);
    });


})(jQuery, document, instawp_ajax_go_live_obj);