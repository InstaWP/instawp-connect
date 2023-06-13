tailwind.config = {
    theme: {
        extend: {
            colors: {
                grayCust: {
                    50: '#6B7280',
                    100: '#E5E7EB',
                    150: '#333333',
                    200: '#111827',
                    250: '#F9FAFB',
                    300: '#1F2937',
                    350: '#D1D5DB',
                    400: '#F9FAFB',
                    500: '#D93F21',
                    900: '#4B5563'
                },
                primary: {
                    700: '#11BF85',
                    800: '#0B6C63',
                    900: '#005E54',
                },
            }
        }
    }
};

(function ($, window, document, plugin_object) {

    let instawp_migrate_api_call_interval = null,
        instawp_migrate_api_call = () => {

            let instawp_migrate_container = $('.instawp-wrap .nav-item-content.create'),
                el_bar_backup = instawp_migrate_container.find('.instawp-progress-backup'),
                el_bar_upload = instawp_migrate_container.find('.instawp-progress-upload'),
                el_bar_staging = instawp_migrate_container.find('.instawp-progress-staging'),
                el_migration_loader = instawp_migrate_container.find('.instawp-migration-loader'),
                el_migration_progress_wrap = instawp_migrate_container.find('.migration-running'),
                el_site_detail_wrap = instawp_migrate_container.find('.migration-completed');

            if (instawp_migrate_container.hasClass('doing-ajax')) {
                return;
            }

            $.ajax({
                type: 'POST',
                url: plugin_object.ajax_url,
                context: this,
                beforeSend: function () {
                    instawp_migrate_container.addClass('doing-ajax');
                },
                complete: function () {
                    instawp_migrate_container.removeClass('doing-ajax');
                },
                data: {
                    'action': 'instawp_connect_migrate',
                    'settings': instawp_migrate_container.serialize(),
                },
                success: function (response) {

                    if (response.success) {

                        console.log(response.data);

                        el_bar_backup.find('.progress-bar').css('width', response.data.backup.progress + '%');
                        el_bar_backup.find('.progress-text').text(response.data.backup.progress + '%');

                        el_bar_upload.find('.progress-bar').css('width', response.data.upload.progress + '%');
                        el_bar_upload.find('.progress-text').text(response.data.upload.progress + '%');

                        el_bar_staging.find('.progress-bar').css('width', response.data.migrate.progress + '%');
                        el_bar_staging.find('.progress-text').text(response.data.migrate.progress + '%');


                        if (response.data.status === 'completed') {
                            if (
                                typeof response.data.site_detail.url !== 'undefined' &&
                                typeof response.data.site_detail.wp_username !== 'undefined' &&
                                typeof response.data.site_detail.wp_password !== 'undefined' &&
                                typeof response.data.site_detail.auto_login_url !== 'undefined'
                            ) {

                                el_migration_progress_wrap.addClass('hidden');
                                el_site_detail_wrap.removeClass('hidden');
                                el_migration_loader.text(el_migration_loader.data('complete-text'));

                                el_site_detail_wrap.find('#instawp-site-url').attr('href', response.data.site_detail.url).html(response.data.site_detail.url);
                                el_site_detail_wrap.find('#instawp-site-username').html(response.data.site_detail.wp_username);
                                el_site_detail_wrap.find('#instawp-site-password').html(response.data.site_detail.wp_password);
                                el_site_detail_wrap.find('#instawp-site-magic-url').attr('href', response.data.site_detail.auto_login_url);
                            }

                            instawp_migrate_container.removeClass('loading').addClass('completed');

                            clearInterval(instawp_migrate_api_call_interval);
                        }
                    }
                }
            });
        };

    $(document).on('change', '#instawp-screen', function () {

        let create_container = $('.instawp-wrap .nav-item-content.create'),
            el_btn_back = create_container.find('.instawp-button-migrate.back'),
            el_btn_continue = create_container.find('.instawp-button-migrate.continue'),
            el_instawp_screen = create_container.find('#instawp-screen'),
            screen_current = parseInt(el_instawp_screen.val()),
            el_screen_nav_items = create_container.find('.screen-nav-items > li'),
            el_screen = create_container.find('.screen');

        // Adjusting Back/Continue Buttons
        if (screen_current <= 1) {
            el_btn_back.addClass('hidden');
        } else if (screen_current >= 4) {
            el_btn_back.addClass('hidden');
            el_btn_continue.addClass('hidden');
        } else {
            el_btn_back.removeClass('hidden');
            el_btn_continue.removeClass('hidden');
        }

        if (screen_current === 3) {
            el_btn_continue.text('Create Staging');
        } else {
            el_btn_continue.text('Next Step');
        }

        // Changing Screen Nav
        el_screen_nav_items.each(function (index) {
            let el_screen_nav_current = $(this),
                el_screen_nav_current_inner = el_screen_nav_current.find('.screen-nav'),
                el_screen_nav_current_line = el_screen_nav_current.find('.screen-nav .screen-nav-line');

            if (index < screen_current) {
                el_screen_nav_current_inner.addClass('active');
            } else {
                el_screen_nav_current_inner.removeClass('active');
            }

            if (index < (screen_current - 1)) {
                el_screen_nav_current_line.addClass('bg-primary-900').removeClass('bg-gray-200');
            } else {
                el_screen_nav_current_line.addClass('bg-gray-200').removeClass('bg-primary-900');
            }
        });

        // Changing Screen
        el_screen.removeClass('active');
        el_screen.parent().find('.screen-' + screen_current).addClass('active');

        // Initiating Migration
        if (screen_current === 4) {
            create_container.addClass('loading');

            instawp_migrate_api_call();
            instawp_migrate_api_call_interval = setInterval(instawp_migrate_api_call, 500);
        }
    });


    $(document).on('change', '.instawp-wrap .instawp-option-selector', function () {

        let el_option_selector = $(this),
            el_option_selector_wrap = el_option_selector.parent().parent(),
            el_selected_staging_options = $('.selected-staging-options'),
            option_id = el_option_selector.val(),
            option_label = el_option_selector_wrap.find('.option-label').text();

        if (el_option_selector_wrap.hasClass('card-active')) {
            el_option_selector_wrap.removeClass('card-active border-primary-900').addClass('border-grayCust-350');

            // For Preview Screens
            el_selected_staging_options.find('.' + option_id).remove();
        } else {
            el_option_selector_wrap.removeClass('border-grayCust-350').addClass('card-active border-primary-900');

            // For Preview Screens
            el_selected_staging_options.append('<div class="' + option_id + ' border-primary-900 border card-active py-2 px-4 text-grayCust-700 text-xs font-medium rounded-lg mr-3">' + option_label + '</div>');
        }
    });


    $(document).on('click', '.instawp-wrap .instawp-staging-type', function () {

        let el_staging_type = $(this),
            el_staging_type_wrapper = el_staging_type.parent(),
            staging_type = el_staging_type.find('input[type="radio"]').val(),
            el_skip_media_folders = $('input#skip_media_folder');

        el_staging_type_wrapper.find('.instawp-staging-type').removeClass('card-active border-primary-900');
        el_staging_type_wrapper.find('input[type="radio"]').prop('checked', false);
        el_staging_type.addClass('card-active border-primary-900');
        el_staging_type.find('input[type="radio"]').prop('checked', true);

        // For Preview Screens
        $('.selected-staging-type').html(el_staging_type.find('.staging-type-label').text());


        if (staging_type === 'quick') {
            el_skip_media_folders.prop('checked', true).trigger('change');
        } else {
            if (el_skip_media_folders.parent().parent().hasClass('card-active')) {
                el_skip_media_folders.prop('checked', false).trigger('change');
            }
        }

    });


    $(document).on('click', '.instawp-wrap .instawp-button-migrate', function () {

        let el_btn_migrate = $(this),
            screen_increment = el_btn_migrate.data('increment'),
            create_container = $('.instawp-wrap .nav-item-content.create'),
            el_instawp_screen = create_container.find('#instawp-screen'),
            screen_current = parseInt(el_instawp_screen.val()),
            screen_next = screen_current + parseInt(screen_increment),
            instawp_migrate_type = $('input[name="instawp_migrate[type]"]:checked').val();

        // Empty check on first screen
        if (el_btn_migrate.hasClass('continue') && screen_current === 1 && (typeof instawp_migrate_type === 'undefined' || instawp_migrate_type.length <= 0)) {
            return;
        }

        el_instawp_screen.val(screen_next).trigger('change');
    });


    $(document).on('ready', function () {
        // let all_nav_items = $('.instawp-wrap .nav-items .nav-item');
        // all_nav_items.first().addClass('active').find('a').toggleClass('text-primary-900 border-primary-900');

        let this_nav_item_id = localStorage.getItem('instawp_admin_current'),
            all_nav_items = $('.instawp-wrap .nav-items .nav-item');

        if (this_nav_item_id !== null && typeof this_nav_item_id !== 'undefined') {
            $('.instawp-wrap #' + this_nav_item_id).find('a').trigger('click');
        } else {
            all_nav_items.first().find('a').trigger('click');
        }

        let instawp_migrate_container = $('.instawp-wrap .nav-item-content.create');

        if (instawp_migrate_container.hasClass('loading')) {
            instawp_migrate_api_call_interval = setInterval(instawp_migrate_api_call, 3000);
        }
    });


    $(document).on('click', '.instawp-wrap .instawp-button-connect', function () {
        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            data: {
                'action': 'instawp_connect_api_url',
            },
            success: function (response) {

                if (response.success) {
                    setTimeout(function () {
                        window.open(response.data.connect_url, '_blank');
                        button_connect.removeClass('loading');
                    }, 1000);
                }
            }
        });
    });


    $(document).on('click', '.instawp-wrap .instawp-migrate-abort', function () {
        if (confirm('Do you really want to abort the migration?')) {
            $.ajax({
                type: 'POST',
                url: plugin_object.ajax_url,
                context: this,
                data: {
                    'action': 'instawp_abort_migration',
                },
                success: function () {
                    clearInterval(instawp_migrate_api_call_interval);
                    window.location.reload();
                }
            });
        }
    });


    $(document).on('click', '.instawp-wrap .instawp-reset-plugin', function () {

        if (!confirm('Do you really want to reset the plugin?')) {
            return;
        }

        let el_reset_button = $(this),
            el_settings_form = $('.instawp-form'),
            el_settings_reset_type = el_settings_form.find('#instawp_reset_type'),
            el_settings_form_response = el_settings_form.find('.instawp-form-response');

        el_settings_form.addClass('loading');
        el_settings_form_response.html('');
        clearInterval(instawp_migrate_api_call_interval);

        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            data: {
                'action': 'instawp_reset_plugin',
                'reset_type': el_settings_reset_type.val(),
            },
            success: function (response) {
                setTimeout(function () {
                    el_settings_form.removeClass('loading');
                    el_settings_form_response.addClass((response.success ? 'success' : 'error')).html(response.data.message);
                    window.location.reload();
                }, 2000);
            }
        });
    });


    $(document).on('submit', '.instawp-form', function (e) {

        e.preventDefault();

        let this_form = $(this),
            this_form_data = this_form.serialize(),
            this_form_response = this_form.find('.instawp-form-response');

        this_form_response.html('');
        this_form.addClass('loading');

        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            data: {
                'action': 'instawp_update_settings',
                'form_data': this_form_data,
            },
            success: function (response) {

                setTimeout(function () {
                    this_form.removeClass('loading');

                    if (response.success) {
                        this_form_response.addClass('success').html(response.data.message);
                    } else {
                        this_form_response.addClass('error').html(response.data.message);
                    }
                }, 1000);

                setTimeout(function () {
                    this_form_response.removeClass('success error').html('');
                }, 3000);
            }
        });

        return false;
    });


    $(document).on('click', '.instawp-wrap .nav-items .nav-item > a', function () {
        let this_nav_item_link = $(this),
            this_nav_item = this_nav_item_link.parent(),
            this_nav_item_id = this_nav_item.attr('id'),
            all_nav_items = this_nav_item.parent().find('.nav-item'),
            nav_item_content_all = $('.instawp-wrap .nav-content .nav-item-content'),
            nav_item_content_target = nav_item_content_all.parent().find('.' + this_nav_item_id);

        all_nav_items.removeClass('active').find('a').removeClass('text-primary-900 border-primary-900').addClass('border-transparent');
        this_nav_item.addClass('active').find('a').removeClass('border-transparent').addClass('text-primary-900 border-primary-900');

        nav_item_content_all.removeClass('active');
        nav_item_content_target.addClass('active');

        localStorage.setItem('instawp_admin_current', this_nav_item_id);
    });

})(jQuery, window, document, instawp_migrate);

