tailwind.config = {
    theme: {
        extend: {
            colors: {
                grayCust: {
                    50: '#6B7280', 100: '#E5E7EB', 150: '#333333', 200: '#111827', 250: '#F9FAFB', 300: '#1F2937', 350: '#D1D5DB', 400: '#F9FAFB', 500: '#D93F21', 900: '#4B5563'
                }, primary: {
                    50: '#A0E5CE', 700: '#11BF85', 800: '#0B6C63', 900: '#005E54',
                }, redCust: {
                    700: '#991B1B'
                }, purpleCust: {
                    50: '#194185', 100: '#1D4ED8', 200: '#0070F0', 700: '#6B2FAD',
                }
            }
        }
    }
};

(function ($, window, document, plugin_object) {

    $.fn.extend({
        hasClasses: function (selectors) {
            let self = this;
            for (let i in selectors) {
                if ($(self).hasClass(selectors[i]))
                    return true;
            }
            return false;
        }
    });

    let instawp_migrate_api_call = () => {

        let create_container = $('.instawp-wrap .nav-item-content.create'),
            el_bar_backup = create_container.find('.instawp-progress-backup'),
            el_bar_upload = create_container.find('.instawp-progress-upload'),
            el_bar_staging = create_container.find('.instawp-progress-staging'),
            el_migration_loader = create_container.find('.instawp-migration-loader'),
            el_migration_progress_wrap = create_container.find('.migration-running'),
            el_site_detail_wrap = create_container.find('.migration-completed'),
            el_screen_buttons = create_container.find('.screen-buttons'),
            el_screen_buttons_last = create_container.find('.screen-buttons-last'),
            el_instawp_nonce = create_container.find('#instawp-nonce');

        if (create_container.hasClasses('doing-ajax completed')) {
            return;
        }

        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            beforeSend: function () {
                create_container.addClass('doing-ajax');
            },
            complete: function () {
                create_container.removeClass('doing-ajax');
            },
            data: {
                'action': 'instawp_connect_migrate',
                'settings': create_container.serialize(),
            }, success: function (response) {

                if (response.success) {

                    console.log(response.data);

                    el_bar_backup.find('.progress-bar').css('width', response.data.backup.progress + '%');
                    el_bar_backup.find('.progress-text').text(response.data.backup.progress + '%');

                    el_bar_upload.find('.progress-bar').css('width', response.data.upload.progress + '%');
                    el_bar_upload.find('.progress-text').text(response.data.upload.progress + '%');

                    el_bar_staging.find('.progress-bar').css('width', response.data.migrate.progress + '%');
                    el_bar_staging.find('.progress-text').text(response.data.migrate.progress + '%');

                    if (typeof response.data.track_migrate_progress !== 'undefined' && response.data.track_migrate_progress.length > 0) {
                        create_container.find('.instawp-track-migration').attr('href', response.data.track_migrate_progress).removeClass('hidden');
                        create_container.find('.instawp-track-migration-area').removeClass('justify-end').addClass('justify-between');
                    }

                    if (response.data.status === 'aborted' || response.data.status === 'nonce_expired') {
                        el_instawp_nonce.val('');
                        create_container.find('.instawp-migration-start-over').trigger('click');
                        create_container.removeClass('loading').addClass('completed');
                        clearInterval(create_container.attr('interval-id'));
                    }

                    if (response.data.status === 'completed') {
                        if (typeof response.data.site_detail.url !== 'undefined' && typeof response.data.site_detail.wp_username !== 'undefined' && typeof response.data.site_detail.wp_password !== 'undefined' && typeof response.data.site_detail.auto_login_url !== 'undefined') {

                            el_migration_progress_wrap.addClass('hidden');
                            el_site_detail_wrap.removeClass('hidden');
                            el_migration_loader.text(el_migration_loader.data('complete-text'));

                            el_site_detail_wrap.find('#instawp-site-url').attr('href', response.data.site_detail.url).find('span').html(response.data.site_detail.url);
                            el_site_detail_wrap.find('#instawp-site-username').html(response.data.site_detail.wp_username);
                            el_site_detail_wrap.find('#instawp-site-password').html(response.data.site_detail.wp_password);
                            el_site_detail_wrap.find('#instawp-site-magic-url').attr('href', response.data.site_detail.auto_login_url);

                            // screen-buttons-last
                            el_screen_buttons.addClass('hidden');
                            el_screen_buttons_last.removeClass('hidden');
                        }

                        el_instawp_nonce.val('');
                        create_container.removeClass('loading').addClass('completed');
                        clearInterval(create_container.attr('interval-id'));
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
        } else if (screen_current >= 5) {
            el_btn_back.addClass('hidden');
            el_btn_continue.addClass('hidden');
        } else {
            el_btn_back.removeClass('hidden');
            el_btn_continue.removeClass('hidden');
        }

        if (screen_current === 4) {
            el_btn_continue.text('Create Staging');
        } else {
            el_btn_continue.text('Next Step');
        }

        // Changing Screen Nav
        el_screen_nav_items.each(function (index) {
            let el_screen_nav_current = $(this), el_screen_nav_current_inner = el_screen_nav_current.find('.screen-nav'), el_screen_nav_current_line = el_screen_nav_current.find('.screen-nav .screen-nav-line');

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
        if (screen_current === 5) {
            create_container.addClass('loading');

            instawp_migrate_api_call();
            create_container.attr('interval-id', setInterval(instawp_migrate_api_call, 2000));
        }
    });


    $(document).on('change', '.instawp-wrap .instawp-option-selector', function () {

        let el_option_selector = $(this), el_option_selector_wrap = el_option_selector.parent().parent(), el_selected_staging_options = $('.selected-staging-options'), option_id = el_option_selector.val(), option_label = el_option_selector_wrap.find('.option-label').text();

        if (el_option_selector_wrap.hasClass('card-active')) {
            el_option_selector_wrap.removeClass('card-active border-primary-900').addClass('border-grayCust-350');

            // For Preview Screens
            el_selected_staging_options.find('.' + option_id).remove();
        } else {
            el_option_selector_wrap.removeClass('border-grayCust-350').addClass('card-active border-primary-900');

            // For Preview Screens
            el_selected_staging_options.append('<div class="' + option_id + ' border-primary-900 border card-active py-2 px-4 text-grayCust-700 text-xs font-medium rounded-lg">' + option_label + '</div>');
        }
    });


    $(document).on('click', '.instawp-wrap .instawp-show-staging-sites, .instawp-wrap .instawp-create-another-site', function (e) {

        let create_container = $('.instawp-wrap .nav-item-content.create'),
            sites_container_nav = $('.instawp-wrap #sites > a'),
            el_screen_buttons = create_container.find('.screen-buttons'),
            el_screen_buttons_last = create_container.find('.screen-buttons-last'),
            el_migration_start_over = create_container.find('.instawp-migration-start-over');

        el_screen_buttons_last.addClass('hidden');
        el_screen_buttons.removeClass('hidden');

        el_migration_start_over.trigger('click');

        if ($(this).hasClass('instawp-create-another-site')) {
            return;
        }

        sites_container_nav.trigger('click');
    });


    $(document).on('click', '.instawp-wrap .instawp-migration-start-over', function (e) {

        let create_container = $('.instawp-wrap .nav-item-content.create'),
            el_instawp_screen = create_container.find('#instawp-screen'),
            el_confirmation_preview = create_container.find('.confirmation-preview'),
            el_confirmation_warning = create_container.find('.confirmation-warning'),
            el_screen_buttons = create_container.find('.screen-buttons'),
            el_screen_doing_request = el_screen_buttons.find('p.doing-request');

        create_container.trigger("reset");
        create_container.removeClass('warning');
        el_confirmation_preview.removeClass('hidden');
        el_confirmation_warning.addClass('hidden');
        el_screen_doing_request.removeClass('loading');

        create_container.find('.card-active').removeClass('card-active border-primary-900');
        create_container.find('.confirmation-preview .selected-staging-options').html('');

        create_container.find('.screen-buttons-last').addClass('hidden');
        create_container.find('.screen-buttons').removeClass('hidden').find('.instawp-button-migrate.continue').removeClass('hidden');

        el_instawp_screen.val(1).trigger('change');
    });


    $(document).on('click', '.instawp-wrap .instawp-staging-type', function () {

        let el_staging_type = $(this), el_staging_type_wrapper = el_staging_type.parent(), staging_type = el_staging_type.find('input[type="radio"]').val(), el_skip_media_folders = $('input#skip_media_folder');

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
            el_screen_buttons = create_container.find('.screen-buttons'),
            el_screen_doing_request = el_screen_buttons.find('p.doing-request'),
            el_confirmation_preview = create_container.find('.confirmation-preview'),
            el_confirmation_warning = create_container.find('.confirmation-warning'),
            el_instawp_screen = create_container.find('#instawp-screen'),
            el_instawp_nonce = create_container.find('#instawp-nonce'),
            screen_current = parseInt(el_instawp_screen.val()),
            screen_next = screen_current + parseInt(screen_increment),
            instawp_migrate_type = $('input[name="instawp_migrate[type]"]:checked').val();

        // Empty check on first screen
        if (el_btn_migrate.hasClass('continue') && screen_current === 1 && (typeof instawp_migrate_type === 'undefined' || instawp_migrate_type.length <= 0)) {
            return;
        }

        if (el_btn_migrate.hasClass('back') || screen_current !== 4) {
            el_instawp_screen.val(screen_next).trigger('change');
        } else {

            // Check limit
            el_screen_doing_request.addClass('loading');

            $.ajax({
                type: 'POST',
                url: plugin_object.ajax_url,
                context: this,
                data: {
                    'action': 'instawp_check_limit'
                }, success: function (response) {
                    if (response.success) {
                        el_screen_doing_request.removeClass('loading');
                        el_instawp_nonce.val(response.data.nonce);
                        el_instawp_screen.val(screen_next).trigger('change');
                    } else {
                        create_container.addClass('warning');
                        el_confirmation_preview.addClass('hidden');
                        el_confirmation_warning.removeClass('hidden');
                        el_confirmation_warning.find('a').attr('href', response.data.button_url).html(response.data.button_text);

                        el_confirmation_warning.find('.remaining-site').html(response.data.remaining_site);
                        el_confirmation_warning.find('.user-allow-site').html(response.data.userAllowSite);
                        el_confirmation_warning.find('.remaining-disk-space').html(response.data.remaining_disk_space);
                        el_confirmation_warning.find('.user-allow-disk-space').html(response.data.userAllowDiskSpace);
                        el_confirmation_warning.find('.require-disk-space').html(response.data.require_disk_space);

                        if (response.data.issue_for === 'remaining_site') {
                            el_confirmation_warning.find('.remaining-site').parent().removeClass('text-primary-900').addClass('text-red-500');
                        }

                        if (response.data.issue_for === 'remaining_disk_space') {
                            el_confirmation_warning.find('.remaining-disk-space').parent().removeClass('text-primary-900').addClass('text-red-500');
                        }
                    }
                }
            });
        }
    });

    $(document).on('ready', function () {
        let create_container = $('.instawp-wrap .nav-item-content.create'),
            this_nav_item_id = localStorage.getItem('instawp_admin_current'),
            el_instawp_screen = create_container.find('#instawp-screen'),
            all_nav_items = $('.instawp-wrap .nav-items .nav-item');

        if (this_nav_item_id !== null && typeof this_nav_item_id !== 'undefined') {
            $('.instawp-wrap #' + this_nav_item_id).find('a').trigger('click');
        } else {
            all_nav_items.first().find('a').trigger('click');
        }

        if (create_container.hasClass('loading')) {

            el_instawp_screen.val(5).trigger('change');
            create_container.attr('interval-id', setInterval(instawp_migrate_api_call, 2000));
        }
    });


    $(document).on('click', '.instawp-wrap .instawp-button-connect', function () {
        $.ajax({
            type: 'POST', url: plugin_object.ajax_url, context: this, data: {
                'action': 'instawp_connect_api_url',
            }, success: function (response) {

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
        let migrate_task_id = $(this).data('migrate-task-id');
        let create_container = $('.instawp-wrap .nav-item-content.create');
        
        if (confirm('Do you really want to abort the migration?')) {
            clearInterval(create_container.attr('interval-id'));
            window.location = window.location.href.split("?")[0] + '?page=instawp&clear=all';
        }
    });


    $(document).on('click', '.instawp-wrap .instawp-reset-plugin', function () {

        if (!confirm('Do you really want to reset the plugin?')) {
            return;
        }

        let el_reset_button = $(this),
            create_container = $('.instawp-wrap .nav-item-content.create'),
            el_settings_form = $('.instawp-form'),
            el_settings_reset_type = el_settings_form.find('#instawp_reset_type'),
            el_settings_form_response = el_settings_form.find('.instawp-form-response');

        el_settings_form.addClass('loading');
        el_settings_form_response.html('');
        clearInterval(create_container.attr('interval-id'));

        $.ajax({
            type: 'POST', url: plugin_object.ajax_url, context: this, data: {
                'action': 'instawp_reset_plugin', 'reset_type': el_settings_reset_type.val(),
            }, success: function (response) {
                setTimeout(function () {
                    el_settings_form.removeClass('loading');
                    el_settings_form_response.addClass((response.success ? 'success' : 'error')).html(response.data.message);
                    window.location = window.location.href.split("?")[0] + '?page=instawp';
                }, 2000);
            }
        });
    });


    $(document).on('submit', '.settings .instawp-form', function (e) {

        e.preventDefault();

        let this_form = $(this), this_form_data = this_form.serialize(), this_form_response = this_form.find('.instawp-form-response');

        this_form_response.html('');
        this_form.addClass('loading');

        $.ajax({
            type: 'POST', url: plugin_object.ajax_url, context: this, data: {
                'action': 'instawp_update_settings', 'form_data': this_form_data,
            }, success: function (response) {

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
        let this_nav_item_link = $(this), this_nav_item = this_nav_item_link.parent(), this_nav_item_id = this_nav_item.attr('id'), all_nav_items = this_nav_item.parent().find('.nav-item'), nav_item_content_all = $('.instawp-wrap .nav-content .nav-item-content'), nav_item_content_target = nav_item_content_all.parent().find('.' + this_nav_item_id);

        all_nav_items.removeClass('active').find('a').removeClass('text-primary-900 border-primary-900').addClass('border-transparent');
        this_nav_item.addClass('active').find('a').removeClass('border-transparent').addClass('text-primary-900 border-primary-900');

        nav_item_content_all.removeClass('active');
        nav_item_content_target.addClass('active');

        localStorage.setItem('instawp_admin_current', this_nav_item_id);
    });


    $(document).on('keyup', '#website_domain', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            $('.instawp-migrate-wrap .migrate-step-proceed').trigger('click');
        }
    });


    $(document).on('click', '.instawp-migrate-wrap .migrate-step-proceed', function () {

        let el_migrate_hosting_wrapper = $('.instawp-migrate-wrap'),
            el_current_step = el_migrate_hosting_wrapper.find('#migrate-step-controller'),
            current_step = parseInt(el_current_step.val()),
            el_website_domain = el_migrate_hosting_wrapper.find('#website_domain'),
            el_email_address = el_migrate_hosting_wrapper.find('#email_address'),
            el_website_domain_confirm = el_migrate_hosting_wrapper.find('#website_domain_confirm'),
            website_domain = el_website_domain.val(),
            email_address = el_email_address.val(),
            website_domain_confirm = el_website_domain_confirm.val();


        if (current_step === 1) {

            if (website_domain.length === 0) {
                return;
            }

            let step_1_wrap = el_migrate_hosting_wrapper.find('.migrate-step.step-' + current_step),
                step_1_button = step_1_wrap.find('.migrate-step-proceed'),
                should_proceed = step_1_button.data('proceed'),
                el_domain_search_response = step_1_wrap.find('.migrate-domain-search-response'),
                el_loading_controller = step_1_button.parent().find('.loading-controller');

            if (should_proceed === 'yes') {
                el_current_step.val(current_step + 1).trigger('change');
                return;
            }

            el_loading_controller.removeClass('opacity-0').addClass('loading');

            $.ajax({
                type: 'POST',
                url: plugin_object.ajax_url,
                context: this,
                data: {
                    'action': 'instawp_check_domain_availability',
                    'domain_name': website_domain
                },
                success: function (response) {

                    el_loading_controller.removeClass('loading').addClass('opacity-0');
                    el_domain_search_response.find('img').attr('src', response.data.icon_url);
                    el_domain_search_response.find('span').html(response.data.message);

                    if (response.success) {
                        step_1_button.data('proceed', 'yes').html('Continue');
                        el_domain_search_response.addClass('text-primary-700');
                    } else {
                        el_domain_search_response.addClass('text-redCust-700');
                    }

                    el_domain_search_response.removeClass('hidden');
                }
            });
        }

        if (current_step === 2) {

            if (email_address.length === 0) {
                return;
            }

            let callback_window, window_open_checker, register_url,
                step_2_wrap = el_migrate_hosting_wrapper.find('.migrate-step.step-' + current_step),
                step_2_button = step_2_wrap.find('.migrate-step-proceed'),
                should_proceed = step_2_button.data('proceed'),
                el_migrate_step_response = step_2_wrap.find('.migrate-step-response'),
                el_migrate_step_loading = el_migrate_step_response.find('.loading-controller');

            if (should_proceed === 'yes') {
                el_website_domain_confirm.val(website_domain);
                el_current_step.val(current_step + 1).trigger('change');
                return;
            }

            el_migrate_step_response.find('span').html('Connecting to hosting...');

            register_url = 'https://www.mijndomein.nl/shop/check-domeinnaam?domeinnaam=' + website_domain + '&email=' + email_address;
            callback_window = window.open(register_url, '_blank', "width=1440,height=720");

            window_open_checker = setInterval(function () {
                if (callback_window.closed) {
                    clearInterval(window_open_checker);

                    step_2_button.data('proceed', 'yes').html('Continue');
                    el_migrate_step_response.find('span').html('Connected to hosting.');
                }
            }, 1000);
        }

        if (current_step === 3) {

            if (website_domain_confirm.length === 0) {
                return;
            }

            let callback_window, window_open_checker, overall_migration_progress = 0,
                step_3_wrap = el_migrate_hosting_wrapper.find('.migrate-step.step-' + current_step),
                step_3_button = step_3_wrap.find('.migrate-step-proceed'),
                should_proceed = step_3_button.data('proceed'),
                el_migration_progress_wrap = step_3_wrap.find('.migration-progress-wrap'),
                el_migrate_step_response = step_3_wrap.find('.migrate-step-response'),
                el_migrate_step_loading = el_migrate_step_response.find('.loading-controller');


            if (should_proceed === 'yes') {
                // Start doing migration now

                el_migration_progress_wrap.removeClass('opacity-0').find('.progress').html(overall_migration_progress);

                let instawp_migrate_hosting_interval = setInterval(function () {

                    if (step_3_wrap.hasClass('doing-ajax')) {
                        return;
                    }

                    $.ajax({
                        type: 'POST',
                        url: plugin_object.ajax_url,
                        context: this,
                        beforeSend: function () {
                            step_3_wrap.addClass('doing-ajax');
                        },
                        complete: function () {
                            step_3_wrap.removeClass('doing-ajax');
                        },
                        data: {
                            'action': 'instawp_connect_migrate',
                            'destination_domain': website_domain_confirm,
                            'clean_previous_backup': true,
                        },
                        success: function (response) {

                            overall_migration_progress = (response.data.backup.progress + response.data.upload.progress + response.data.migrate.progress) / 3;
                            overall_migration_progress = Math.ceil(overall_migration_progress);

                            el_migration_progress_wrap.find('.progress').html(overall_migration_progress);

                            console.log(response.data);

                            if (response.success) {
                                if (response.data.status === 'completed') {

                                    clearInterval(instawp_migrate_hosting_interval);

                                    el_migrate_hosting_wrapper.find('.website-domain-name').html(website_domain_confirm).attr('href', website_domain_confirm);
                                    el_migrate_hosting_wrapper.find('.migrate-visit-site').attr('href', response.data.site_detail.auto_login_url);

                                    el_current_step.val(current_step + 1).trigger('change');
                                }
                            }
                        }
                    });
                }, 1000);

                // el_current_step.val(current_step + 1).trigger('change');
                return;
            }

            el_migrate_step_response.find('.loading-controller > span').html('Authorising...');

            callback_window = window.open(website_domain_confirm + '/wp-admin/authorize-application.php?app_name=InstaWP&app_id=33ff0627-fdd3-5266-a7b3-9eba4e2d07e3&success_url=https%3A%2F%2Fstage.instawp.io%2Fdesign20%2Fwp-connect-callback%3Fsid%3DMjEyOTY%3D', '_blank', "width=1440,height=720");
            window_open_checker = setInterval(function () {
                if (callback_window.closed) {

                    $.ajax({
                        type: 'POST',
                        url: plugin_object.ajax_url,
                        context: this,
                        data: {
                            'action': 'instawp_check_domain_connect_status',
                            'destination_domain': website_domain_confirm,
                        },
                        success: function (response) {

                            console.log(response.data);

                            if (response.success) {
                                step_3_button.data('proceed', 'yes').html('Migrate Website');
                                el_migrate_step_response.find('.loading-controller > span').html('Connected.');
                            } else {
                                el_migrate_step_response.find('.loading-controller > span').html('Not Connected.');
                            }
                        },
                        complete: function () {
                            clearInterval(window_open_checker);
                        }
                    });
                }
            }, 1000);
        }
    });


    $(document).on('change', '#migrate-step-controller', function () {

        let current_step = $(this).val(),
            el_migrate_hosting_wrapper = $('.instawp-migrate-wrap'),
            el_migrate_step_all = el_migrate_hosting_wrapper.find('.migrate-step'),
            el_migrate_step_current = el_migrate_hosting_wrapper.find('.migrate-step.step-' + current_step),
            el_migrate_step_prev = el_migrate_hosting_wrapper.find('.migrate-step.step-' + (current_step - 1));

        el_migrate_step_all.find('.accordion-item-body-content').addClass('accordion-height');
        el_migrate_step_all.find('.accordion-item-header-title').removeClass('text-lg text-grayCust-150 font-bold').addClass('text-sm text-grayCust-900 font-medium');

        el_migrate_step_current.find('.accordion-item-body-content').removeClass('accordion-height');
        el_migrate_step_current.find('.accordion-item-header-title').removeClass('text-sm text-grayCust-900 font-medium').addClass('text-lg text-grayCust-150 font-bold');


        // For the step lines and icon box
        el_migrate_step_prev.find('.step-progress-line').removeClass('bg-grayCust-350').addClass('bg-purpleCust-700').css('top', '44px');

        el_migrate_step_current.find('.step-progress-box').addClass('-top-1').attr('style', '');
        el_migrate_step_prev.find('.step-progress-box').addClass('-top-1').attr('style', '');

        el_migrate_step_current.find('.step-progress-icon').removeClass('border-gray-300').addClass('border-purpleCust-700');
        el_migrate_step_current.find('.step-progress-icon span').removeClass('hidden');

        el_migrate_step_prev.find('.step-progress-icon').removeClass('bg-white').addClass('bg-purpleCust-700');
        el_migrate_step_prev.find('.step-progress-icon').find('img').removeClass('hidden');
        el_migrate_step_prev.find('.step-progress-icon').find('span').addClass('hidden');
    });


    // Get Dir List start //
    $(document).on('change', '#skip_large_files', function() {
        let el = $(this),
            subEl = $(document).find('.instawp-checkbox.exclude-item.large-file');

        subEl.not(":disabled").prop( "checked", el.is(":checked") );
    });

    $(document).on('change', '#active_plugins_only, input#active_themes_only, input#skip_media_folder, select#instawp-sort-by', function () {
        $(document).trigger("instawpTriggerRefresh");
    });

    $(document).on('click', '.instawp-refresh-file-explorer', function () {
        $(document).trigger("instawpTriggerRefresh");
    });

    $(document).on('instawpTriggerRefresh', function () {
        $(document).find('.exclude-container').removeClass('p-4 h-80').html('<div class="loading"></div>');
        $(document).find('#instawp-files-select-all').prop( "checked", false ).prop( "disabled", true );
        $(document).find('#instawp-sort-by').prop( "disabled", true );
        $(document).find('.instawp-refresh-file-explorer').prop( "disabled", true ).addClass('animate-spin');
        $(document).trigger("instawpLoadDirectory");
    });

    $(document).on('change', '#instawp-files-select-all', function() {
        let el = $(this),
            subEl = $(document).find('.exclude-container .instawp-checkbox.exclude-item');

        subEl.not(":disabled").prop( "checked", el.is(":checked") );
    });

    $(document).on('change', '.instawp-checkbox.exclude-item', function() {
        let el = $(this),
            parentEl = el.closest('.item'),
            subEl = parentEl.find('.sub-item .instawp-checkbox.exclude-item');

        if ( $(document).find('.exclude-container .instawp-checkbox.exclude-item').not(':checked').length) {
            $(document).find('#instawp-files-select-all').prop( "checked", false );
        } else {
            $(document).find('#instawp-files-select-all').prop( "checked", true );
        }

        subEl.not(":disabled").prop( "checked", el.is(":checked") );
    });

    $(document).on('ready', function () {
        $(document).trigger("instawpLoadDirectory");
    });

    $(document).on('instawpLoadDirectory', function () {
        let = el_active_plugins_only = $('input#active_plugins_only'),
            el_active_themes_only = $('input#active_themes_only'),
            el_skip_media_folder = $('input#skip_media_folder'),
            el_sort_by = $('select#instawp-sort-by').val(),
            el_loading = $(document).find('.exclude-container > .loading');
    
        if (el_loading.length > 0) {
            $.ajax({
                type: 'POST', 
                url: plugin_object.ajax_url, 
                context: this, 
                data: {
                    'action': 'instawp_get_dir_contents',
                    //'path': '/wp-content',
                    'active_plugins': el_active_plugins_only.prop("checked"),
                    'active_themes': el_active_themes_only.prop("checked"),
                    'skip_media_folder': el_skip_media_folder.prop("checked"),
                    'sort_by': el_sort_by,
                    'security': instawp_migrate.security
                },
                success: function (response) {
                    $(document).find('.exclude-container').html(response.data).addClass('p-4 h-80');
                    $(document).find('#instawp-files-select-all').prop( "disabled", false );
                    $(document).find('#instawp-sort-by').prop( "disabled", false );
                    $(document).find('.instawp-refresh-file-explorer').prop( "disabled", false ).removeClass('animate-spin');
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    alert(errorThrown + ': Can\'t proceed. Please try again!');
                    window.location = window.location.href.split("?")[0] + '?page=instawp';
                }
            });
        }
    });

    $(document).on('click', '.instawp-refresh-large-files', function () {
        $(document).trigger("instawpLoadLargeFiles");
    });

    $(document).on('instawpLoadLargeFiles', function () {
        let el_skip_large_files = $('input#skip_large_files').is(":checked");

        $(document).find('.instawp-refresh-large-files').prop( "disabled", true ).addClass('animate-spin');

        $.ajax({
            type: 'POST', 
            url: plugin_object.ajax_url, 
            context: this, 
            data: {
                'action': 'instawp_get_large_files',
                'skip': el_skip_large_files,
                'security': instawp_migrate.security
            },
            success: function (response) {
                if ( response.data ) {
                    $(document).find('.instawp-large-file-container').html(response.data);
                    $(document).find('.instawp-refresh-large-files').prop( "disabled", false ).removeClass('animate-spin');
                } else {
                    $(document).trigger("instawpLoadLargeFiles");
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert(errorThrown + ': Can\'t proceed. Please try again!');
                window.location = window.location.href.split("?")[0] + '?page=instawp';
            }
        });
    });

    $(document).on('click', '.instawp-wrap .expand-folder', function () {
        let el = $(this),
            imgEl = $(this).find('svg'),
            parentEl = el.closest('.item'),
            //inputLabel = parentEl.find('label'),
            folderPath = el.data('expand-folder'),
            el_is_checked = parentEl.find('.instawp-checkbox.exclude-item').is(":checked"),
            el_active_plugins_only = $('input#active_plugins_only'),
            el_active_themes_only = $('input#active_themes_only'),
            el_sort_by = $('select#instawp-sort-by').val(),
            el_skip_media_folder = $('input#skip_media_folder');

        if ( imgEl.hasClass('rotate-icon') ) {
            if ( ! parentEl.find('.sub-item').length ) {
                $.ajax({
                    type: 'POST', 
                    url: plugin_object.ajax_url, 
                    context: this, 
                    data: {
                        'action': 'instawp_get_dir_contents',
                        'path': '/'+folderPath,
                        'active_plugins': el_active_plugins_only.prop("checked"),
                        'active_themes': el_active_themes_only.prop("checked"),
                        'skip_media_folder': el_skip_media_folder.prop("checked"),
                        'sort_by': el_sort_by,
                        'is_checked': el_is_checked,
                        'security': instawp_migrate.security
                    },
                    beforeSend: function () {
                        parentEl.find('.cursor-pointer').append('<svg role="status" class="instawp-loader inline ml-3 w-4 h-4 text-primary-900 animate-spin" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg"><path data-v-fe125208="" d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="#E5E7EB"></path><path data-v-fe125208="" d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentColor"></path></svg>');
                    },
                    success: function (response) {
                        // parentEl.find('.sub-item').removeClass('hidden').html(response.data);
                        parentEl.append('<div class="pl-5 sub-item">' + response.data + '</div>');
                        //inputLabel.after('<svg role="status" class="inline ml-3 w-4 h-4 text-primary-900 opacity-70" fill="none" xmlns="http://www.w3.org/2000/svg"> <path style="fill: #005e54;" fill-rule="evenodd" clip-rule="evenodd" d="M1.59995 0.800049C2.09701 0.800049 2.49995 1.20299 2.49995 1.70005V3.59118C3.64303 2.42445 5.23642 1.70005 6.99995 1.70005C9.74442 1.70005 12.0768 3.45444 12.9412 5.90013C13.1069 6.36877 12.8612 6.88296 12.3926 7.0486C11.924 7.21425 11.4098 6.96862 11.2441 6.49997C10.6259 4.75097 8.95787 3.50005 6.99995 3.50005C5.52851 3.50005 4.22078 4.20657 3.39937 5.30005H6.09995C6.59701 5.30005 6.99995 5.70299 6.99995 6.20005C6.99995 6.6971 6.59701 7.10005 6.09995 7.10005H1.59995C1.10289 7.10005 0.699951 6.6971 0.699951 6.20005V1.70005C0.699951 1.20299 1.10289 0.800049 1.59995 0.800049ZM1.6073 8.95149C2.07594 8.78585 2.59014 9.03148 2.75578 9.50013C3.37396 11.2491 5.04203 12.5 6.99995 12.5C8.47139 12.5 9.77912 11.7935 10.6005 10.7L7.89995 10.7C7.40289 10.7 6.99995 10.2971 6.99995 9.80005C6.99995 9.30299 7.40289 8.90005 7.89995 8.90005H12.3999C12.6386 8.90005 12.8676 8.99487 13.0363 9.16365C13.2051 9.33243 13.3 9.56135 13.3 9.80005V14.3C13.3 14.7971 12.897 15.2 12.4 15.2C11.9029 15.2 11.5 14.7971 11.5 14.3V12.4089C10.3569 13.5757 8.76348 14.3 6.99995 14.3C4.25549 14.3 1.92309 12.5457 1.05867 10.1C0.893024 9.63132 1.13866 9.11714 1.6073 8.95149Z"></path> </svg>')
                        imgEl.removeClass('rotate-icon');
                        parentEl.find('.instawp-loader').remove();
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        alert(errorThrown + ': Can\'t proceed. Please try again!');
                        window.location = window.location.href.split("?")[0] + '?page=instawp';
                    }
                });
            } else {
                parentEl.find('.sub-item').removeClass('hidden');
                imgEl.removeClass('rotate-icon');
            }
        } else {
            //parentEl.find('.sub-item').addClass('hidden').html('');
            parentEl.find('.sub-item').addClass('hidden');
            //parentEl.find('.sub-item').remove();
            imgEl.addClass('rotate-icon');
        }
        
    });
    // Get Dir List end //


    // Staging Sites List start //
    $(document).on('click', '.instawp-wrap .instawp-clear-staging-sites', function () {
        $.ajax({
            type: 'POST', 
            url: plugin_object.ajax_url, 
            context: this, 
            data: {
                'action': 'instawp_clear_staging_sites',
                'security': instawp_migrate.security
            },
            beforeSend: function () {
                $(document).find('.settings .instawp-form').addClass('loading');
            },
            success: function (response) {
                window.location = window.location.href.split("?")[0] + '?page=instawp';
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert(errorThrown + ': Can\'t proceed. Please try again!');
                window.location = window.location.href.split("?")[0] + '?page=instawp';
            }
        });
    });
    // Staging Sites List end //

    // Disconnect start //
    $(document).on('click', '.instawp-wrap .instawp-disconnect-plugin', function () {
        if (!confirm('Do you really want to disconnect the plugin? It will completely remove the existing staging sites form the plugin.')) {
            return;
        }
        $.ajax({
            type: 'POST', 
            url: plugin_object.ajax_url, 
            context: this, 
            data: {
                'action': 'instawp_disconnect_plugin',
                'api': true,
                'security': instawp_migrate.security
            },
            beforeSend: function () {
                $(document).find('.settings .instawp-form').addClass('loading');
            },
            success: function (response) {
                if ( response.success == true ) {
                    window.location = window.location.href.split("?")[0] + '?page=instawp';
                } else {
                    $(document).find('.settings .instawp-form').removeClass('loading');
                    if (confirm(response.data.message + ' Do you still want to disconnect the plugin?')) {
                        $.ajax({
                            type: 'POST', 
                            url: plugin_object.ajax_url, 
                            context: this, 
                            data: {
                                'action': 'instawp_disconnect_plugin',
                                'api': false,
                                'security': instawp_migrate.security
                            },
                            beforeSend: function () {
                                $(document).find('.settings .instawp-form').addClass('loading');
                            },
                            success: function (response) {
                                if ( response.success == true ) {
                                    window.location = window.location.href.split("?")[0] + '?page=instawp';
                                } else {
                                    $(document).find('.settings .instawp-form').removeClass('loading');
                                }
                            }
                        });
                    }
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert(errorThrown + ': Can\'t proceed. Please try again!');
                window.location = window.location.href.split("?")[0] + '?page=instawp';
            }
        });
    });
    // Disconnect end //

    // Remote Management settings save start //
    let ajaxSaveManagementSettings = (name, value) => {
        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            data: {
                'action': 'instawp_save_management_settings',
                'name': name,
                'value': value,
                'security': instawp_migrate.security
            },
            beforeSend: function () {
                $(document).find('.manage .instawp-form').addClass('loading');
            },
            success: function (response) {
                if (response.success === true) {
                    let label_field = $(document).find('.' + name.replace(/_/g, '-') + '-field .toggle-label');
                    setTimeout(function () {
                        label_field.text(label_field.data(value));
                        $(document).find('.manage .instawp-form').removeClass('loading');
                        
                    }, 300);
                } else {
                    alert('Can\'t update settings. Please try again!');
                    window.location = window.location.href.split("?")[0] + '?page=instawp';
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert(errorThrown + ': Can\'t update settings. Please try again!');
                window.location = window.location.href.split("?")[0] + '?page=instawp';
            }
        });
    }

    $(document).on('change', '.save-ajax', function () {
        let name = $(this).attr('id');
        let value = $(this).is(':checked') ? 'on' : 'off';

        ajaxSaveManagementSettings(name, value);
    });

    $(document).on('instawpToggleSave', function (e, name, value) {
        if (name === 'instawp_rm_heartbeat') {
            if (value === 'on') {
                $(document).find('.instawp-api-heartbeat-field').show();
            } else if (value === 'off') {
                $(document).find('.instawp-api-heartbeat-field').hide();
            }
        }
    });

    let debounce = null;
    $(document).on('input', '#instawp_api_heartbeat', function (e) {
        let el = $(this);
        let name = el.attr('id');
        let value = parseInt(Math.abs($(this).val()));

        clearTimeout(debounce);
        debounce = setTimeout(function () {
            if (value >= 15 && value <= 60) {
                ajaxSaveManagementSettings(name, value);
            } else {
                el.val(15);
                ajaxSaveManagementSettings(name, 15);
            }
        }, 500);
    });
    // Remote Management settings save end //

    // Site list pagination start //
    $(document).on('ready', function () {
        $(document).find('.staging-site-list').slice(0, parseInt($(document).find('.sites').data('pagination'))).show();
    });

    $(document).on('click', '.sites .page-item', function (e) {
        e.preventDefault();
        let el = $(this);
        let page = parseInt(el.parents('.sites').data('pagination'));
        let item = parseInt($(this).data('item'));
        let position = parseInt(item * page);

        $(document).find('.sites .page-item').removeClass('active');
        $(document).find('.sites .page-item[data-item=' + item + ']').addClass('active');

        $(document).find('.staging-site-list').hide();
        $(document).find('.staging-site-list').slice(parseInt(position - page), position).show();

        let prevEl = $(document).find('.sites .prev-item');
        if (el.prev().length > 0) {
            prevEl.removeClass('disabled');
        } else {
            prevEl.addClass('disabled');
        }

        let nextEl = $(document).find('.sites .next-item');
        if (el.next().length > 0) {
            nextEl.removeClass('disabled');
        } else {
            nextEl.addClass('disabled');
        }
    });

    $(document).on('click', '.sites .prev-item', function (e) {
        e.preventDefault();
        let prev = $(document).find('.sites .page-item.active').prev();
        if (prev.length > 0) {
            prev.trigger('click');
        }
    });

    $(document).on('click', '.sites .next-item', function (e) {
        e.preventDefault();
        let next = $(document).find('.sites .page-item.active').next();
        if (next.length > 0) {
            next.trigger('click');
        }
    });
    // Site list pagination end //

})(jQuery, window, document, instawp_migrate);

