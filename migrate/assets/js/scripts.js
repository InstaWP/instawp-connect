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

    let instawp_migrate_api_call_interval = null,
        instawp_migrate_api_call = () => {

            let instawp_migrate_container = $('.instawp-wrap .nav-item-content.create'),
                el_bar_backup = instawp_migrate_container.find('.instawp-progress-backup'),
                el_bar_upload = instawp_migrate_container.find('.instawp-progress-upload'),
                el_bar_staging = instawp_migrate_container.find('.instawp-progress-staging'),
                el_migration_loader = instawp_migrate_container.find('.instawp-migration-loader'),
                el_migration_progress_wrap = instawp_migrate_container.find('.migration-running'),
                el_site_detail_wrap = instawp_migrate_container.find('.migration-completed'),
                el_screen_buttons = instawp_migrate_container.find('.screen-buttons'),
                el_screen_buttons_last = instawp_migrate_container.find('.screen-buttons-last');

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
                }, success: function (response) {

                    if (response.success) {

                        console.log(response.data);

                        el_bar_backup.find('.progress-bar').css('width', response.data.backup.progress + '%');
                        el_bar_backup.find('.progress-text').text(response.data.backup.progress + '%');

                        el_bar_upload.find('.progress-bar').css('width', response.data.upload.progress + '%');
                        el_bar_upload.find('.progress-text').text(response.data.upload.progress + '%');

                        el_bar_staging.find('.progress-bar').css('width', response.data.migrate.progress + '%');
                        el_bar_staging.find('.progress-text').text(response.data.migrate.progress + '%');

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
        if (screen_current === 4) {
            create_container.addClass('loading');

            instawp_migrate_api_call();
            instawp_migrate_api_call_interval = setInterval(instawp_migrate_api_call, 500);
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
            el_selected_staging_options.append('<div class="' + option_id + ' border-primary-900 border card-active py-2 px-4 text-grayCust-700 text-xs font-medium rounded-lg mr-3">' + option_label + '</div>');
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
            screen_current = parseInt(el_instawp_screen.val()),
            screen_next = screen_current + parseInt(screen_increment),
            instawp_migrate_type = $('input[name="instawp_migrate[type]"]:checked').val();

        // Empty check on first screen
        if (el_btn_migrate.hasClass('continue') && screen_current === 1 && (typeof instawp_migrate_type === 'undefined' || instawp_migrate_type.length <= 0)) {
            return;
        }

        if (el_btn_migrate.hasClass('back') || screen_current !== 3) {
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
        // let all_nav_items = $('.instawp-wrap .nav-items .nav-item');
        // all_nav_items.first().addClass('active').find('a').toggleClass('text-primary-900 border-primary-900');

        let this_nav_item_id = localStorage.getItem('instawp_admin_current'), all_nav_items = $('.instawp-wrap .nav-items .nav-item');

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
        if (confirm('Do you really want to abort the migration?')) {
            $.ajax({
                type: 'POST', url: plugin_object.ajax_url, context: this, data: {
                    'action': 'instawp_abort_migration',
                }, success: function () {
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

        let el_reset_button = $(this), el_settings_form = $('.instawp-form'), el_settings_reset_type = el_settings_form.find('#instawp_reset_type'), el_settings_form_response = el_settings_form.find('.instawp-form-response');

        el_settings_form.addClass('loading');
        el_settings_form_response.html('');
        clearInterval(instawp_migrate_api_call_interval);

        $.ajax({
            type: 'POST', url: plugin_object.ajax_url, context: this, data: {
                'action': 'instawp_reset_plugin', 'reset_type': el_settings_reset_type.val(),
            }, success: function (response) {
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


})(jQuery, window, document, instawp_migrate);

