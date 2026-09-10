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

    let formatBytes = (bytes, decimals = 2) => {
        if (!+bytes) return '0 Bytes'

        const k = 1024
        const dm = decimals < 0 ? 0 : decimals
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']

        const i = Math.floor(Math.log(bytes) / Math.log(k))

        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`
    }

    let blinkElement = (selector, times, interval) => {
        let blinkCount = 0;
        let blinkInterval = setInterval(function () {
            $(selector).toggleClass('bg-yellow-100');
            blinkCount++;
            if (blinkCount >= times * 2) {
                clearInterval(blinkInterval);
                $(selector).removeClass('bg-yellow-100');
            }
        }, interval);
    }

    let getQueryParameter = (name) => {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }

    let getCurrentDateTime = () => {
        const now = new Date();

        // Get date components
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');

        // Get time components
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');

        // Concatenate date and time components
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    // Simple toast notifications (top-right)
    let instawpToastContainer = null;
    let ensureToastContainer = () => {
        if (!instawpToastContainer) {
            instawpToastContainer = $('<div class="instawp-toast-container"></div>')
                .css({ position: 'fixed', top: '16px', right: '16px', zIndex: 99999, display: 'flex', flexDirection: 'column', gap: '8px' });
            $('body').append(instawpToastContainer);
        }
        return instawpToastContainer;
    }

    let showInstawpToast = (message, type = 'success') => {
        let container = ensureToastContainer();
        let bg = (type === 'success') ? '#059669' : '#DC2626'; // emerald-600 / red-600
        let toast = $('<div class="instawp-toast"></div>')
            .css({ background: bg, color: '#fff', padding: '10px 14px', borderRadius: '8px', boxShadow: '0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1)' })
            .text(message || (type === 'success' ? 'Success. Settings updated.' : 'Error. Please try again.'));
        container.append(toast);
        setTimeout(function () {
            toast.fadeOut(300, function () { $(this).remove(); });
        }, 3000);
    }

    let elapsedInterval = null;

    let updateTimer = (startTime) => {
        // Get current time
        const currentTime = new Date();

        // Calculate time difference in milliseconds
        const timeDifference = currentTime.getTime() - new Date(startTime + " UTC").getTime();

        // Calculate elapsed time in days, hours, minutes, and seconds
        const days = Math.floor(timeDifference / (1000 * 60 * 60 * 24));
        const hours = Math.floor((timeDifference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((timeDifference % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((timeDifference % (1000 * 60)) / 1000);

        let string = '';
        if (seconds >= 0) {
            string = seconds + 's';
        }
        if (minutes > 0) {
            string = minutes + 'm ' + string;
        }
        if (hours > 0) {
            string = hours + 'h ' + string;
        }
        if (days > 0) {
            string = days + 'd ' + string;
        }
        $(document).find('#visibility-timer').text(string);
    }

    let migrationSteps = {};
    $.each(plugin_object.trans.stages, function (stage_key, stage_value) {
        migrationSteps[stage_key] = stage_value;
    });

    let instawp_migrate_progress = () => {

        let create_container = $('.instawp-wrap .nav-item-content.create'),
            el_bar_files = create_container.find('.instawp-progress-files'),
            el_bar_db = create_container.find('.instawp-progress-db'),
            el_visibility_box = create_container.find('#visibility-box'),
            el_bar_restore = create_container.find('.instawp-progress-restore'),
            el_migration_loader = create_container.find('.instawp-migration-loader'),
            el_migration_progress_wrap = create_container.find('.migration-running'),
            el_site_detail_wrap = create_container.find('.migration-completed'),
            el_migration_error_wrap = create_container.find('.migration-error'),
            el_migration_error_message = el_migration_error_wrap.find('.error-message'),
            el_migration_download_log = el_migration_error_wrap.find('.instawp-download-log'),
            el_screen_buttons = create_container.find('.screen-buttons'),
            el_screen_buttons_last = create_container.find('.screen-buttons-last'),
            el_stage_wrapper = create_container.find('.instawp-progress-stage');

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
                'action': 'instawp_migrate_progress',
                'visible': !$(document).find('#visibility-box-area').hasClass('hidden'),
                'security': plugin_object.security,
            },
            success: function (response) {
                if (response.success) {
                    let progress_files = response.data.progress_files ? response.data.progress_files : 0,
                        progress_db = response.data.progress_db ? response.data.progress_db : 0,
                        progress_restore = response.data.progress_restore ? response.data.progress_restore : 0,
                        progress_stages = response.data.stage,
                        failed_message = response.data.failed_message,
                        processed_files = response.data.processed_files ? response.data.processed_files : [],
                        processed_db = response.data.processed_db ? response.data.processed_db : [],
                        startTime = response.data.started_at ?? null,
                        current_stage = 'processing',
                        stage_migration_finished = false;

                    if (!elapsedInterval) {
                        elapsedInterval = setInterval(() => {
                            updateTimer(startTime)
                        }, 1000);
                    }

                    el_bar_files.find('.instawp-progress-bar').css('width', progress_files + '%');
                    el_bar_files.find('.progress-text').text(progress_files + '%');

                    el_bar_db.find('.instawp-progress-bar').css('width', progress_db + '%');
                    el_bar_db.find('.progress-text').text(progress_db + '%');

                    el_bar_restore.find('.instawp-progress-bar').css('width', progress_restore + '%');
                    el_bar_restore.find('.progress-text').text(progress_restore + '%');

                    $.each(progress_stages, function (stage_key, stage_value) {
                        if (stage_value === true) {
                            if (stage_key === 'migration-finished') {
                                stage_migration_finished = true;
                            }
                            current_stage = stage_key;
                            //el_stage_wrapper.find('.stage-' + stage_key).find('.stage-status').addClass('active');
                        }
                    });

                    if (Object.keys(migrationSteps).includes(current_stage)) {
                        el_visibility_box.find('#visibility-content-area').append('<div class="visibility-content-item flex gap-3 items-center hover:bg-zinc-800 hover:rounded-lg py-1.5 px-2.5 "><span class="text-gray-100 min-w-36">' + response.data.timestamp + '</span><span class="text-gray-100 break-all font-medium">' + migrationSteps[current_stage] + '</span></div>');
                        delete migrationSteps[current_stage];
                    }

                    let current_stage_item = el_visibility_box.find('.stage-' + current_stage);
                    if (current_stage_item.hasClass('hidden')) {
                        el_visibility_box.find('.stage').addClass('hidden');
                        current_stage_item.removeClass('hidden');
                    }

                    let content_html = '';

                    $.each(processed_files, function (key, value) {
                        content_html += '<div class="visibility-content-item flex gap-3 items-center hover:bg-zinc-800 hover:rounded-lg py-1.5 px-2.5 group ' + value.status + '">';
                        content_html += '<span class="text-gray-100 min-w-36">' + response.data.timestamp + '</span><span class="text-gray-100 break-all group-[.sent]:text-emerald-300 group-[.failed]:text-rose-500 group-[.skipped]:text-yellow-300 group-[.invalid]:text-red-300">' + value.filepath + ' (' + value.size + ')';
                        if (value.status === 'in-progress') {
                            content_html += ' <span class="hidden group-hover:inline-block cursor-pointer ml-2 px-2 py-1 text-xs rounded-lg border border-zinc-700 text-rose-500 instawp-skip-item" data-type="file" data-item="' + value.id + '">' + plugin_object.trans.skip_item_txt + '</span>';
                        }
                        content_html += '</span></div>';
                    });

                    $.each(processed_db, function (key, value) {
                        content_html += '<div class="visibility-content-item flex gap-3 items-center hover:bg-zinc-800 hover:rounded-lg py-1.5 px-2.5 group ' + value.status + '">';
                        content_html += '<span class="text-gray-100 min-w-36">' + response.data.timestamp + '</span><span class="text-gray-100 break-all group-[.sent]:text-emerald-300 group-[.failed]:text-rose-500 group-[.skipped]:text-yellow-300 group-[.invalid]:text-red-300">' + value.table_name + ' - ' + value.offset + ' / ' + value.rows_total + ' rows';
                        if (value.status === 'in-progress') {
                            content_html += ' <span class="hidden group-hover:inline-block cursor-pointer ml-2 px-2 py-1 text-xs rounded-lg border border-zinc-700 text-rose-500 instawp-skip-item" data-type="db" data-item="' + value.table_name + '">' + plugin_object.trans.skip_item_txt + '</span>';
                        }
                        content_html += '</span></div>';
                    });

                    if (content_html) {
                        el_visibility_box.find('#visibility-content-area').append(content_html);

                        let el_box_area = el_visibility_box.find('#visibility-content-area');
                        el_box_area.animate({
                            scrollTop: el_box_area[0].scrollHeight
                        }, 500);
                    }

                    // Completed
                    if (stage_migration_finished === true) {
                        create_container.removeClass('loading').addClass('completed');
                        el_visibility_box.find('#visibility-box, .full-screen-btn').addClass('hidden');
                        clearInterval(create_container.attr('interval-id'));

                        if (
                            typeof response.data.dest_wp.url !== 'undefined' &&
                            typeof response.data.dest_wp.username !== 'undefined' &&
                            typeof response.data.dest_wp.password !== 'undefined' &&
                            typeof response.data.dest_wp.auto_login_url !== 'undefined'
                        ) {

                            let delay = (typeof response.data.delay_success !== 'undefined' && response.data.delay_success !== null) ? parseInt(response.data.delay_success) : 1;
                            delay = 0 === delay ? 1 : delay * 1000;
                            setTimeout(() => {
                                el_migration_progress_wrap.addClass('hidden');
                                el_site_detail_wrap.removeClass('hidden');
                                el_migration_loader.text(el_migration_loader.data('complete-text'));

                                // Remove active class
                                el_stage_wrapper.find('.stage-status').removeClass('active');

                                el_site_detail_wrap.find('#instawp-site-url').attr('href', response.data.dest_wp.url).find('span').html(response.data.dest_wp.url);
                                el_site_detail_wrap.find('#instawp-site-username').html(response.data.dest_wp.username);
                                el_site_detail_wrap.find('#instawp-site-password').html(response.data.dest_wp.password);
                                el_site_detail_wrap.find('#instawp-site-magic-url').attr('href', response.data.dest_wp.auto_login_url);

                                // screen-buttons-last
                                el_screen_buttons.addClass('hidden');
                                el_screen_buttons_last.removeClass('hidden');
                            }, delay);
                        }
                    }
                } else {
                    create_container.removeClass('loading').addClass('completed');
                    clearInterval(create_container.attr('interval-id'));

                    if (response.data.failed_message) {
                        el_migration_error_message.html(response.data.failed_message);
                    } else {
                        el_migration_error_message.html(response.data.message);
                    }

                    console.log(response);

                    el_migration_download_log.data('migrate-id', response.data.migrate_id);
                    el_migration_download_log.data('server-logs', response.data.server_logs);

                    el_migration_loader.removeClass('text-primary-900').addClass('text-red-700').text(el_migration_loader.data('error-text'));
                    el_migration_progress_wrap.addClass('hidden');
                    el_migration_error_wrap.removeClass('hidden');
                    el_screen_buttons_last.removeClass('hidden');
                }
            },
            error: function () {
                window.location = window.location.href.split("?")[0] + '?page=instawp';
            }
        });
    },
        // The V3 progress apparatus is driven by the V3 progress endpoint, which a V4 run never
        // calls. Left in place it shows "Files 0%", "Database 0%" and "Processing (0/N stages)" for
        // the entire migration and reads as a stalled run -- which is exactly how it was reported.
        // So the V4 branch strips it at the START, not at completion, and shows only the notice and
        // the Track Migration link.
        //
        // Abort goes with it. It navigates to ?clear=all, which clears LOCAL plugin state only; the
        // agent-side migration keeps running regardless. Offering it here promises a cancel we
        // cannot perform.
        instawp_staging_v4_chrome = (create_container) => {
            // .instawp-v3-progress is the whole block -- both bars, their "Files"/"Database"
            // labels and the stage list. Hiding the bars by their own classes would leave the
            // labels behind.
            create_container.find('.instawp-v3-progress, .instawp-migrate-abort, .notice-serve-with-wp')
                .addClass('hidden');

            // Which message depends on whether a link is already on screen, and BOTH cases are
            // real: a fresh start has none for the first few minutes, while a RESUMED run had its
            // href seeded server-side from the stored agent_url and shows it on first paint.
            // Deciding here rather than at each call site keeps the two paths from drifting.
            let el_notice = create_container.find('.instawp-v4-running'),
                el_link = create_container.find('.instawp-track-migration'),
                has_link = el_link.length > 0 && !el_link.hasClass('hidden');

            el_notice.text(el_notice.data(has_link ? 'tracking-text' : 'waiting-text'));
            el_notice.removeClass('hidden');

            // The V4 twin of the Abort button hidden just above. Revealed here rather than rendered
            // visible, so it can only appear on a live V4 run -- never on V3, and never on a screen
            // that is not actually polling.
            create_container.find('.instawp-v4-cancel').removeClass('hidden');
        },
        /*
         * Surface a non-terminal error, once per distinct message.
         *
         * Keyed on the MESSAGE, not on a boolean: the watcher polls every 3s, so a shown-once flag
         * would suppress a second, different error, and re-showing on every poll would resurrect a
         * notice the admin just dismissed. Storing the dismissed text means the same error stays
         * dismissed while a NEW one still gets through.
         */
        instawp_staging_v4_notice = (create_container, message) => {
            if (typeof message !== 'string' || message.length === 0) {
                return;
            }

            let el_error = create_container.find('.instawp-v4-error');

            if (el_error.data('dismissed') === message) {
                return;
            }

            // .text(), not .html(): this is a server-supplied string that quotes the destination's
            // own output, so it must never be rendered as markup on an admin page.
            el_error.find('.instawp-v4-error-message').text(message);
            el_error.removeClass('hidden');
        },
        // Stop a V4 watch and return the wizard to a non-running state. `loading` is added in
        // instawp_migrate_init's beforeSend and only `doing-ajax` is removed on complete, so it has
        // to be undone here or the UI stays visually mid-migration forever.
        //
        // The elapsedInterval clear below is now defensive only -- the V4 branches no longer start
        // one -- but it is kept because this helper also runs after a V3 screen has been on the page,
        // and leaking a per-second interval is worse than a redundant clearInterval.
        instawp_staging_v4_stop = (create_container, watcher) => {
            clearInterval(watcher);
            create_container.removeAttr('interval-id');
            create_container.removeClass('loading');

            if (elapsedInterval) {
                clearInterval(elapsedInterval);
                elapsedInterval = null;
            }
        },
        instawp_staging_v4_fail = (create_container, message) => {
            // Guard against both terminal handlers running. clearInterval stops new polls but any
            // request already in flight still settles, so a run that gave up after N failures could
            // then receive a `completed` — leaving a green header above a red error box.
            if (create_container.hasClass('completed') || create_container.hasClass('migration-failed')) {
                return;
            }

            create_container.addClass('migration-failed');

            // Nothing left to cancel once the run is terminal.
            create_container.find('.instawp-v4-cancel').addClass('hidden');

            let el_error_wrap = create_container.find('.migration-error'),
                el_loader = create_container.find('.instawp-migration-loader');

            // The header still says "In Progress..." otherwise — a purple in-progress label sitting
            // next to a red error box. V3 does exactly this at its own error path; the strings are
            // translated data-attributes on the element, so nothing is hardcoded here.
            el_loader.removeClass('text-primary-900').addClass('text-red-700').text(el_loader.data('error-text'));

            // The template's .error-message <p> is EMPTY — there is no default to fall back to, so
            // an absent reason would render a red box with an icon, a button and no words. Fall back
            // to the loader's translated "Migration Failed".
            // .text(), not .html(): this is a server-supplied string and the fallback is plain
            // text anyway, so there is nothing to gain from rendering it as markup on an admin page.
            el_error_wrap.find('.error-message').text(
                message && message.length > 0 ? message : el_loader.data('error-text')
            );

            // V4 never populates data-migrate-id / data-server-logs, so this button would download
            // an empty `undefined-log.txt`. V3 hides it on its error path for the same reason.
            el_error_wrap.find('.instawp-download-log').addClass('hidden');

            el_error_wrap.removeClass('hidden');
            create_container.find('.migration-running').addClass('hidden');
        },
        instawp_staging_v4_complete = (create_container) => {
            // `completed` has to look terminal too, and the first attempt at this made it LESS
            // terminal than `failed`: the header said "Completed" while the spinner kept turning,
            // the progress bars sat at 0%, and a live Abort button remained — on a migration that
            // had already succeeded. Abort navigates to ?clear=all, so it was not merely cosmetic.
            //
            // Mirrors what V3's completed path does: hide the live-progress furniture, and give the
            // user a forward action. `.migration-running` deliberately STAYS visible — the Track
            // Migration link lives inside it and is the whole point of this flow.
            // Checks BOTH classes. An earlier version checked only `completed`, which left the
            // exact ordering the fail() comment describes wide open: give up after 5 failures, then
            // an in-flight poll settles with `completed` and writes a green "Completed" header over
            // a red error box, revealing the forward actions while .migration-error is showing.
            if (create_container.hasClass('completed') || create_container.hasClass('migration-failed')) {
                return;
            }

            let el_loader = create_container.find('.instawp-migration-loader');

            create_container.addClass('completed');
            el_loader.text(el_loader.data('complete-text'));
            // instawp_staging_v4_chrome() already hid the V3 progress apparatus at start; what is
            // left to retire here is the in-progress notice, which is now untrue.
            create_container.find('.instawp-v4-running').addClass('hidden');
            create_container.find('.instawp-v4-cancel').addClass('hidden');
            create_container.find('.screen-buttons-last').removeClass('hidden');
        },
        instawp_staging_v4_watch = (create_container) => {
            let failures = 0;

            // V4 staging: the migration agent owns the live view, so we poll only until it hands us
            // a URL, then surface the wizard's existing "track migration" link. Deliberately NOT the
            // V3 progress loop — there is no V3 migration row to report on.
            let watcher = setInterval(function () {
                $.post(plugin_object.ajax_url, {
                    'action': 'instawp_staging_status_v4',
                    'security': plugin_object.security,
                }, function (response) {
                    if (!response.success) {
                        // Give up rather than poll admin-ajax every 3s for the life of the page.
                        // Some failures are permanent (the run option is gone, client-app 4xx) and
                        // are indistinguishable here from a transient blip, so bound the retries.
                        failures += 1;

                        if (failures >= 5) {
                            instawp_staging_v4_stop(create_container, watcher);
                            instawp_staging_v4_fail(create_container, response.data && response.data.message);
                        }

                        return;
                    }

                    failures = 0;

                    // Prefer migration_url, fall back to tracking_url — resolved server-side and
                    // handed over as agent_url.
                    if (typeof response.data.agent_url !== 'undefined' && response.data.agent_url.length > 0) {
                        create_container.find('.instawp-track-migration').attr('href', response.data.agent_url).removeClass('hidden');
                        create_container.find('.instawp-track-migration-area').removeClass('justify-end').addClass('justify-between');

                        // Now that a link exists, stop telling the user one is coming.
                        let el_v4_notice = create_container.find('.instawp-v4-running');

                        el_v4_notice.text(el_v4_notice.data('tracking-text'));
                    }

                    // A run can carry an error while client-app still reports a NON-terminal status —
                    // an unusable destination SSH, say. The terminal handler below never fires for
                    // those, so without this the admin watches a spinner that will never resolve and
                    // is told nothing. Shown inside the running panel so the link stays put, and
                    // dismissible because some of these clear on a retry.
                    instawp_staging_v4_notice(create_container, response.data.message);

                    // A terminal status must LOOK terminal. Clearing the poll alone left the
                    // spinner turning and the elapsed timer counting up forever, so `failed`
                    // rendered identically to `completed` and to still-in-progress — the user was
                    // never told the migration had failed.
                    if (['completed', 'failed'].indexOf(response.data.status) !== -1) {
                        instawp_staging_v4_stop(create_container, watcher);

                        if ('failed' === response.data.status) {
                            instawp_staging_v4_fail(create_container, response.data.message);
                        } else {
                            instawp_staging_v4_complete(create_container);
                        }
                    }
                }).fail(function () {
                    failures += 1;

                    if (failures >= 5) {
                        instawp_staging_v4_stop(create_container, watcher);
                        instawp_staging_v4_fail(create_container, '');
                    }
                });
            }, 3000);

            create_container.attr('interval-id', watcher);
        },
        instawp_migrate_init = () => {

            let create_container = $('.instawp-wrap .nav-item-content.create'),
                el_migration_error_wrap = create_container.find('.migration-error'),
                el_migration_progress_wrap = create_container.find('.migration-running'),
                el_migration_error_message = el_migration_error_wrap.find('.error-message'),
                el_migration_download_log = el_migration_error_wrap.find('.instawp-download-log'),
                el_migration_loader = create_container.find('.instawp-migration-loader');

            if (create_container.hasClass('loading')) {
                return;
            }

            $.ajax({
                type: 'POST',
                url: plugin_object.ajax_url,
                context: this,
                beforeSend: function () {
                    create_container.addClass('loading doing-ajax');
                },
                complete: function () {
                    create_container.removeClass('doing-ajax');
                },
                data: {
                    'action': 'instawp_migrate_init',
                    'settings': create_container.serialize(),
                    'security': plugin_object.security,
                },
                success: function (response) {
                    console.log(response);

                    if (response.success) {
                        // The engine decides the path. V3 below is untouched.
                        if (response.data.engine === 'v4') {
                            // Before the watcher, so the dead V3 widgets are never painted: run()
                            // does not return the agent URL, so the first poll is ~3s away and the
                            // user would otherwise spend that time looking at 0% bars.
                            instawp_staging_v4_chrome(create_container);

                            /*
                             * NO elapsed timer on V4, deliberately.
                             *
                             * updateTimer() parses `startTime + " UTC"`, a MySQL datetime -- what V3
                             * sends. V4 sends time(), an epoch int, so `new Date("1757505600 UTC")`
                             * is Invalid Date and the element was set to empty text every second for
                             * the life of the page. It was invisible either way: #visibility-timer
                             * lives inside .instawp-v3-progress, which chrome() has just hidden.
                             *
                             * Not repaired, because the agent's own page -- the one the Track
                             * Migration link leads to -- already shows elapsed time.
                             */
                            instawp_staging_v4_watch(create_container);

                            return;
                        }

                        if (!elapsedInterval) {
                            elapsedInterval = setInterval(() => {
                                updateTimer(response.data.started_at)
                            }, 1000);
                        }

                        // populate the tracking url
                        if (typeof response.data.tracking_url !== 'undefined' && response.data.tracking_url.length > 0) {
                            create_container.find('.instawp-track-migration').attr('href', response.data.tracking_url).removeClass('hidden');
                            create_container.find('.instawp-track-migration-area').removeClass('justify-end').addClass('justify-between');
                        }

                        if (typeof response.data.serve_with_wp !== 'undefined' && response.data.serve_with_wp) {
                            create_container.find('.notice-serve-with-wp').removeClass('hidden');
                        }

                        create_container.attr('interval-id', setInterval(instawp_migrate_progress, 3000));
                    } else {
                        create_container.removeClass('loading');
                        el_migration_progress_wrap.addClass('hidden');
                        el_migration_loader.removeClass('text-primary-900').addClass('text-red-700').text(el_migration_loader.data('error-text'));
                        // .text(), not .html(). This branch now also renders V4 failures, whose
                        // message is client-app's own string forwarded verbatim by run() -- a remote
                        // string reaching an admin page. Every V4 handler added on this branch uses
                        // .text() for the same reason; this was the one sink that did not.
                        el_migration_error_message.text(response.data.message);
                        el_migration_download_log.addClass('hidden');
                        el_migration_error_wrap.removeClass('hidden');
                        // create_container.find('#instawp-screen').val(4).trigger('change');
                    }
                }
            });
        };

    let popupWindow = null;
    let intervalChecker = null;
    let ajaxRequest = null;
    let progress = 0;
    let progressInterval;

    $(document).on('click', '.instawp-add-credit-card', function () {
        $(this).addClass('pointer-events-none');
        clearInterval(intervalChecker);
        popupWindow = window.open(plugin_object.api_domain + '/card?utm_source=instawp_connect&utm_medium=wp_plugin&utm_campaign=instawp_connect&source=instawp_connect', '_blank');
        intervalChecker = setInterval(function () {
            if (popupWindow && popupWindow.closed) {
                clearInterval(intervalChecker);
                $('.payment-method-warning').addClass('hidden');
                $('.instawp-button-migrate.continue').removeAttr('disabled');
                $('.instawp-add-credit-card').removeClass('pointer-events-none');
            }
        }, 500);
    });

    $(document).on('click', '.instawp-copy-cmd', function () {
        let inputField = document.createElement('input'),
            el_copy_block = $(this),
            el_copy_text = el_copy_block.find('.copy-text'),
            text_to_copy = el_copy_block.data('text-to-copy'),
            text_before_copy = el_copy_text.html(),
            text_after_copy = el_copy_text.data('text-after-copy');

        document.body.appendChild(inputField);
        inputField.value = text_to_copy;
        inputField.select();
        document.execCommand('copy', false);
        inputField.remove();

        el_copy_text.html(text_after_copy);

        setTimeout(function () {
            el_copy_text.html(text_before_copy);
        }, 2000);
    });

    $(document).on('click', '.instawp-site-name .placeholder-text', function () {
        let el_instawp_site_name = $('.instawp-site-name'),
            el_placeholder_text = el_instawp_site_name.find('.placeholder-text'),
            el_site_name_input_wrap = el_instawp_site_name.find('.site-name-input-wrap'),
            el_site_name_input = el_site_name_input_wrap.find('input#site-prefix');

        el_placeholder_text.addClass('hidden');
        el_site_name_input_wrap.removeClass('hidden');
        el_site_name_input.focus();
    });

    $(document).on('click', '.instawp-download-log', function () {

        let server_logs = $(this).data('server-logs'),
            migrate_id = $(this).data('migrate-id'),
            filename = migrate_id + '-log.txt',
            blob = new Blob([server_logs], { type: 'text/plain;charset=utf-8;' });

        if (window.navigator.msSaveOrOpenBlob) {
            window.navigator.msSaveBlob(blob, filename);
        } else {
            let downloadLink = $('<a></a>');
            downloadLink.attr('href', window.URL.createObjectURL(blob));
            downloadLink.attr('download', filename);
            downloadLink.css('display', 'none');
            $('body').append(downloadLink);
            downloadLink[0].click();
            downloadLink.remove();
        }
    });

    $(document).on('change', '#instawp-screen', function () {
        let create_container = $('.instawp-wrap .nav-item-content.create'),
            el_screen_buttons = create_container.find('.screen-buttons'),
            el_btn_back = create_container.find('.instawp-button-migrate.back'),
            el_btn_continue = create_container.find('.instawp-button-migrate.continue'),
            el_instawp_screen = create_container.find('#instawp-screen'),
            screen_current = parseInt(el_instawp_screen.val()),
            el_screen_nav_items = create_container.find('.screen-nav-items > li'),
            el_screen_loading_request = el_screen_buttons.find('p.loading-request'),
            el_staging_plan_container = create_container.find('.staging-plan-container'),
            el_payment_method_warning = create_container.find('.payment-method-warning'),
            el_custom_plan_warning = create_container.find('.custom-plan-warning'),
            el_screen = create_container.find('.screen');

        el_screen_buttons.removeClass('hidden');

        // Adjusting Back/Continue Buttons
        if (screen_current <= 1) {
            el_btn_back.addClass('hidden');
        } else if (screen_current >= 5) {
            el_screen_buttons.addClass('hidden');
            el_btn_back.addClass('hidden');
            el_btn_continue.addClass('hidden');
        } else {
            el_btn_back.removeClass('hidden');
            el_btn_continue.removeClass('hidden');
        }
        clearInterval(progressInterval);

        if (screen_current === 4) {
            el_btn_continue.text(plugin_object.trans.create_staging_txt);
            if (!create_container.find('.files-size-container .total-size').hasClass('loaded')) {
                ajaxRequest = $.ajax({
                    type: 'POST',
                    url: plugin_object.ajax_url,
                    context: this,
                    beforeSend: function () {
                        el_btn_continue.attr('disabled', true);
                        el_screen_loading_request.removeClass('hidden');
                        //el_btn_back.attr('disabled', true);

                        progress = 0;
                        create_container.find('.files-size-container .total-size').text(plugin_object.trans.calculating_size_txt + ' (0%)');

                        progressInterval = setInterval(() => {
                            if (progress < 90) {
                                progress += Math.floor(Math.random() * 5) + 2; // 2% to 6%
                                progress = Math.min(progress, 90);
                            } else if (progress < 99) {
                                progress += Math.random() < 0.3 ? 1 : 0; // Very slow increase
                                progress = Math.min(progress, 99);
                            }
                            create_container.find('.files-size-container .total-size').text(plugin_object.trans.calculating_size_txt + ' (' + progress + '%)');
                        }, 300);
                    },
                    data: {
                        'action': 'instawp_get_site_plans',
                        'settings': create_container.serialize(),
                        'security': plugin_object.security,
                    },
                    success: function (response) {
                        clearInterval(progressInterval);
                        create_container.find('.files-size-container .total-size').text(plugin_object.trans.calculating_size_txt + ' (100%)');
                        //el_btn_back.removeAttr('disabled');

                        setTimeout(function () {
                            create_container.find('.files-size-container .total-size').html(response.data.total_size_formatted).addClass('loaded');
                            el_screen_loading_request.addClass('hidden');

                            if (response.success && response.data && !response.data.is_legacy) {
                                el_staging_plan_container.removeClass('hidden').html(response.data.content);
                                const firstEnabledRadio = create_container.find('.staging-plans input[type="radio"]:not(:disabled)').first();
                                if (firstEnabledRadio.length) {
                                    firstEnabledRadio.prop('checked', true);

                                    if (response.data.has_payment_method) {
                                        el_payment_method_warning.addClass('hidden');
                                        el_btn_continue.removeAttr('disabled');
                                    } else {
                                        el_payment_method_warning.removeClass('hidden');
                                    }
                                } else {
                                    el_custom_plan_warning.removeClass('hidden');
                                    el_custom_plan_warning.find('.disk-quota-value').html(response.data.total_size_formatted);
                                }
                            } else {
                                el_btn_continue.removeAttr('disabled');
                            }
                        }, 500);
                    }
                });
            } else {
                if (!el_custom_plan_warning.hasClass('hidden')) {
                    el_btn_continue.attr('disabled', true);
                }
            }
        } else {
            if (ajaxRequest && ajaxRequest.readyState !== 4) {
                ajaxRequest.abort();
                ajaxRequest = null;
                create_container.find('.files-size-container .total-size').text(plugin_object.trans.calculating_size_txt + ' (0%)');
            }
            el_btn_continue.text(plugin_object.trans.next_step_txt).removeAttr('disabled');
            el_screen_loading_request.addClass('hidden');
            el_screen_buttons.removeClass('justify-end').addClass('justify-between');
        }

        // Changing Screen Nav
        // el_screen_nav_items.each(function (index) {
        //     let el_screen_nav_current = $(this),
        //         el_screen_nav_current_inner = el_screen_nav_current.find('.screen-nav'),
        //         el_screen_nav_current_line = el_screen_nav_current.find('.screen-nav .screen-nav-line');

        //     if (index < screen_current) {
        //         el_screen_nav_current_inner.addClass('active');
        //     } else {
        //         el_screen_nav_current_inner.removeClass('active');
        //     }

        //     if (index < (screen_current - 1)) {
        //         el_screen_nav_current_line.addClass('bg-primary-900').removeClass('bg-gray-200');
        //     } else {
        //         el_screen_nav_current_line.addClass('bg-gray-200').removeClass('bg-primary-900');
        //     }
        // });

        // Changing Screen
        // el_screen.removeClass('active');
        // el_screen.parent().find('.screen-' + screen_current).addClass('active');

        // Update screen navigation items
        el_screen_nav_items.each(function (index) {
            let el_screen_nav_current = $(this),
                el_screen_nav_current_inner = el_screen_nav_current.find('.screen-nav'),
                el_screen_nav_current_line = el_screen_nav_current.find('.screen-nav .screen-nav-line');

            // Toggle 'active' for nav
            el_screen_nav_current_inner.toggleClass('active', index < screen_current);

            // Update line colors
            el_screen_nav_current_line
                .toggleClass('bg-primary-900', index < (screen_current - 1))
                .toggleClass('bg-gray-200', index >= (screen_current - 1));
        });

        // Efficient screen switch (avoid flicker)
        let current_active_screen = el_screen.filter('.active'),
            new_active_screen = el_screen.parent().find('.screen-' + screen_current);

        if (!new_active_screen.hasClass('active')) {
            current_active_screen.removeClass('active');
            new_active_screen.addClass('active');
        }

        // Initiating Migration
        //
        // A RESUME also lands here: both resume paths reach screen 5 by
        // el_instawp_screen.val(5).trigger('change'), which runs this handler. Without the flag
        // that meant every page refresh during a live run called instawp_migrate_init() again --
        // starting a SECOND staging migration, and, when it failed, hiding .migration-running so
        // the screen vanished. That is the "still not visible after refresh" report.
        //
        // The flag is consumed here rather than cleared by the resume block, so it cannot leak into
        // a later, genuine screen change within the same page load.
        if (screen_current === 5 && create_container.data('instawp-resuming')) {
            create_container.removeData('instawp-resuming');
        } else if (screen_current === 5) {
            // BEFORE the request, not in its success callback. The screen has just been switched a
            // few lines above, and instawp_migrate_init() runs staging-init + start -- which is
            // destination SITE CREATION, tens of seconds. Applying the V4 chrome on success meant
            // the V3 bars and the "Processing (0/N stages)" list were on screen for that entire
            // window, which is what "it shows the old screen first" was.
            //
            // Safe unconditionally here: migrate_init() is V4-only on this branch, so screen 5 is
            // always a V4 run. If a V3 path is ever restored, gate this on the engine.
            instawp_staging_v4_chrome(create_container);

            instawp_migrate_init();
        }

        $(document).trigger('instawp_migrate_screen_change', [screen_current]);
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
            el_selected_staging_options.append('<div class="' + option_id + ' border-primary-900 border card-active py-2 px-4 text-xs font-medium rounded-lg">' + option_label + '</div>');
        }

        if (el_selected_staging_options.children().length > 0) {
            el_selected_staging_options.parent().removeClass('hidden');
        } else {
            el_selected_staging_options.parent().addClass('hidden');
        }
    });

    $(document).on('click', '.create-staging-btn', function (e) {
        $(document).find('.connected').addClass('hidden');
        $(document).find('.create-staging').removeClass('hidden');
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('step') == 1) {
        $(document).find('.create-staging-btn').click();
    }

    $(document).on('click', '.browse-staging-btn, .instawp-show-staging-sites', function (e) {
        /*
         * Switch tab FIRST, then refresh.
         *
         * The tab click persists `instawp_admin_current` to localStorage, and the refresh below
         * ends in a full page reload -- so doing it in this order is what brings the user back on
         * the Staging Sites tab rather than on whatever they had open before.
         *
         * Landing on that tab is not enough on its own: its list is rendered from the
         * `instawp_staging_sites` option, which is a CACHE. A site that has just finished migrating
         * is not in it, so the user arrives at a list that does not contain the thing they came to
         * see.
         */
        $(document).find('.nav-items > #sites > a').trigger('click');

        /*
         * Reuse the refresh button's own handler rather than repeating its AJAX call here. It
         * already does the whole job -- posts instawp_refresh_staging_sites, spins its icon, marks
         * the form loading, reloads on success and alerts on failure -- and a second copy would be
         * one more thing to keep in step with it.
         *
         * part-sites.php is always in the DOM (main.php includes every tab's template and toggles
         * .active), so this element exists even when that tab has never been opened. Guarded anyway:
         * the button is rendered twice there, and not at all if the nav item is hidden for this
         * site.
         */
        let el_refresh = $(document).find('.instawp-refresh-staging-sites').first();

        if (el_refresh.length > 0) {
            el_refresh.trigger('click');
        }
    });

    $(document).on('click', '.instawp-wrap .instawp-migration-start-over', function (e) {

        let create_container = $('.instawp-wrap .nav-item-content.create'),
            el_instawp_screen = create_container.find('#instawp-screen'),
            el_confirmation_preview = create_container.find('.confirmation-preview'),
            el_confirmation_warning = create_container.find('.confirmation-warning'),
            el_migration_loader = create_container.find('.instawp-migration-loader'),
            el_migration_progress_wrap = create_container.find('.migration-running'),
            el_site_detail_wrap = create_container.find('.migration-completed'),
            el_screen_buttons = create_container.find('.screen-buttons'),
            el_screen_doing_request = el_screen_buttons.find('p.doing-request');

        create_container.trigger("reset");
        create_container.removeClass('warning');
        create_container.removeClass('completed');
        create_container.find('.instawp-progress-bar').removeAttr('style');
        create_container.find('.instawp-track-migration').addClass('hidden').attr('href', '');
        el_confirmation_preview.removeClass('hidden');
        el_confirmation_warning.addClass('hidden');
        el_screen_doing_request.removeClass('loading');

        el_site_detail_wrap.addClass('hidden');
        el_migration_progress_wrap.removeClass('hidden');
        el_migration_loader.text(el_migration_loader.data('in-progress-text'));

        create_container.find('.card-active').removeClass('card-active border-primary-900');
        create_container.find('.confirmation-preview .selected-staging-options').html('');

        create_container.find('.screen-buttons-last').addClass('hidden');
        create_container.find('.screen-buttons').removeClass('hidden').find('.instawp-button-migrate.continue').removeClass('hidden');
        create_container.removeAttr('interval-id');
        el_instawp_screen.val(1).trigger('change');
    });

    $(document).on('click', '.instawp-wrap .instawp-staging-type', function () {

        let el_staging_type = $(this),
            el_staging_type_wrapper = el_staging_type.parent(),
            staging_type = el_staging_type.find('input[type="radio"]').val(),
            el_active_plugins_only = $('input#active_plugins_only'),
            el_active_themes_only = $('input#active_themes_only'),
            el_skip_log_tables = $('input#skip_log_tables'),
            el_skip_media_folders = $('input#skip_media_folder'),
            el_skip_large_files = $('input#skip_large_files');

        el_staging_type_wrapper.find('.instawp-staging-type').removeClass('card-active border-primary-900');
        el_staging_type_wrapper.find('input[type="radio"]').prop('checked', false);
        el_staging_type.addClass('card-active border-primary-900');
        el_staging_type.find('input[type="radio"]').prop('checked', true);

        // For Preview Screens
        $('.selected-staging-type').html(el_staging_type.find('.staging-type-label').text());

        if (staging_type === 'quick') {
            el_active_plugins_only.prop('checked', true).trigger('change');
            el_active_themes_only.prop('checked', true).trigger('change');
            el_skip_log_tables.prop('checked', true).trigger('change');
            el_skip_media_folders.prop('checked', true).trigger('change');
            el_skip_large_files.prop('checked', true).trigger('change');
        } else {
            if (el_skip_media_folders.parent().parent().hasClass('card-active')) {
                el_skip_media_folders.prop('checked', false).trigger('change');
            }
            if (el_skip_large_files.parent().parent().hasClass('card-active')) {
                el_skip_large_files.prop('checked', false).trigger('change');
            }
            if (el_active_plugins_only.parent().parent().hasClass('card-active')) {
                el_active_plugins_only.prop('checked', false).trigger('change');
            }
            if (el_active_themes_only.parent().parent().hasClass('card-active')) {
                el_active_themes_only.prop('checked', false).trigger('change');
            }
            if (el_skip_log_tables.parent().parent().hasClass('card-active')) {
                el_skip_log_tables.prop('checked', false).trigger('change');
            }
        }
    });
    $(document).find('.instawp-wrap .instawp-staging-type:first').trigger('click');

    $(document).on('click', '.instawp-wrap .instawp-button-migrate', function () {

        let el_btn_migrate = $(this),
            screen_increment = el_btn_migrate.data('increment'),
            create_container = $('.instawp-wrap .nav-item-content.create'),
            el_screen_buttons = create_container.find('.screen-buttons'),
            el_screen_doing_request = el_screen_buttons.find('p.doing-request'),
            el_instawp_site_name = el_screen_buttons.find('.instawp-site-name'),
            el_confirmation_preview = create_container.find('.confirmation-preview'),
            el_confirmation_warning = create_container.find('.confirmation-warning'),
            el_instawp_screen = create_container.find('#instawp-screen'),
            el_payment_method_warning = create_container.find('.payment-method-warning'),
            screen_current = parseInt(el_instawp_screen.val()),
            screen_next = screen_current + parseInt(screen_increment),
            instawp_migrate_type = $('input[name="migrate_settings[type]"]:checked').val();

        console.log({
            screen_current: screen_current
        });

        // Empty check on first screen
        if (el_btn_migrate.hasClass('continue') && screen_current === 1 && (typeof instawp_migrate_type === 'undefined' || instawp_migrate_type.length <= 0)) {
            return;
        }

        if (screen_current === 2) {
            $(document).trigger("instawpLoadDirectory", [false]);
        }

        if (el_btn_migrate.hasClass('back') || screen_current !== 4) {
            el_instawp_screen.val(screen_next).trigger('change');
        } else {

            // Check limit
            el_screen_buttons.removeClass('justify-between').addClass('justify-end');
            el_instawp_site_name.addClass('hidden');
            el_screen_doing_request.addClass('loading');
            el_payment_method_warning.addClass('hidden');
            el_btn_migrate.attr('disabled', true);
            create_container.find('.instawp-button-migrate.back').attr('disabled', true);

            $.ajax({
                type: 'POST',
                url: plugin_object.ajax_url,
                context: this,
                data: {
                    'action': 'instawp_check_usages_limit',
                    'settings': create_container.serialize(),
                    'security': plugin_object.security,
                },
                success: function (response) {
                    if (response.success) {
                        el_screen_doing_request.removeClass('loading');
                        el_instawp_screen.val(screen_next).trigger('change');
                    } else {
                        //create_container.addClass('warning');
                        create_container.find('.instawp-button-migrate.back').removeAttr('disabled');

                        if (response.data.issue_for === 'storage_limit_exceeded' || response.data.issue_for === 'no_payment_method' || response.data.issue_for === 'free_site_limit_exceeded' || response.data.issue_for === 'no_plan_found') {
                            el_screen_buttons.addClass('justify-between').removeClass('justify-end');
                            el_instawp_site_name.removeClass('hidden');
                            el_screen_doing_request.removeClass('loading');

                            if (response.data.issue_for === 'free_site_limit_exceeded') {
                                alert('Free limit reached');
                                location.reload();
                            } else if (response.data.issue_for === 'storage_limit_exceeded') {
                                alert('Storage limit exceeded');
                                location.reload();
                            } else if (response.data.issue_for === 'no_plan_found') {
                                alert('No plan found');
                                location.reload();
                            } else {
                                el_payment_method_warning.removeClass('hidden');
                            }
                            return;
                        }

                        el_btn_migrate.removeAttr('disabled');
                        el_confirmation_preview.addClass('hidden');
                        el_confirmation_warning.removeClass('hidden');
                        el_confirmation_warning.find('a').attr('href', response.data.button_url).html(response.data.button_text);

                        // Error found other than disk limit
                        if (undefined !== response.data.error && true === response.data.error && undefined !== response.data.http_code) {
                            // Update warning title
                            el_confirmation_warning.find('.warning-title').html(response.data.title);
                            // Update warning sub title
                            let subtitle = el_confirmation_warning.find('.warning-subtitle');
                            subtitle.html(response.data.message + '<br> HTTP Error: ' + response.data.http_code);
                            // Remove margin 
                            subtitle.removeClass('mb-2');
                            // Add margin bottom
                            subtitle.addClass('mb-4');
                            // Remove details as we don't have such details
                            el_confirmation_warning.find('.warning-details').remove();
                            return true;
                        }

                        el_confirmation_warning.find('.remaining-site').html(response.data.remaining_site);
                        el_confirmation_warning.find('.user-allow-site').html(response.data.userAllowSite);
                        el_confirmation_warning.find('.remaining-disk-space').html(response.data.remaining_disk_space);
                        el_confirmation_warning.find('.user-allow-disk-space').html(response.data.userAllowDiskSpace);
                        el_confirmation_warning.find('.require-disk-space').html(response.data.required_disk_space);

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

    /*
     * WAS `$(document).on('ready', …)`, which has been DEAD since jQuery 3.0 removed that API.
     * WordPress ships jQuery 3.7.1, so every statement in this block has silently never executed —
     * the V4 staging resume added on this branch was built on a handler that already never fired,
     * which is exactly why it reviewed as correct and did nothing on the site.
     *
     * ⚠ This one line revives FIVE dormant behaviours, not just the V4 resume: the staging-site
     * list pagination reveal, the last-viewed tab restore, the V3 `loading` progress resume, the
     * ?field= blink, and the site-name input's mousedown blur handler. All look intended, none has
     * run in production for as long as this site has been on jQuery 3, so none is covered by
     * anybody's experience of how the plugin currently behaves.
     */
    $(function () {
        let create_container = $('.instawp-wrap .nav-item-content.create'),
            el_instawp_current_tab = $('.instawp-wrap .instawp-current-tab'),
            el_instawp_current_tab_data = el_instawp_current_tab.attr('current-tab'),
            this_nav_item_id = localStorage.getItem('instawp_admin_current'),
            el_instawp_screen = create_container.find('#instawp-screen'),
            all_nav_items = $('.instawp-wrap .nav-items .nav-item'),
            current_nav_item;

        $(document).find('.staging-site-list').slice(0, parseInt($(document).find('.sites').data('pagination'))).show();

        if (el_instawp_current_tab && el_instawp_current_tab_data !== 'undefined' && el_instawp_current_tab_data !== '') {
            current_nav_item = $('.instawp-wrap #' + el_instawp_current_tab_data);
        } else if (this_nav_item_id !== null && typeof this_nav_item_id !== 'undefined') {
            current_nav_item = $('.instawp-wrap #' + this_nav_item_id);
        }

        if (current_nav_item && current_nav_item.length > 0) {
            current_nav_item.find('a').trigger('click');
        } else {
            all_nav_items.first().find('a').trigger('click');
        }

        if (create_container.hasClass('loading')) {
            // Resuming, not starting: see the guard in the #instawp-screen change handler.
            create_container.data('instawp-resuming', true);
            el_instawp_screen.val(5).trigger('change');
            create_container.attr('interval-id', setInterval(instawp_migrate_progress, 3000));
        }

        // V4 staging resume. The server decides whether the stored run is still live
        // (InstaWP_Staging_V4::resumable_run) and stamps this class; here we only re-enter the
        // screen and the watcher. Kept off the `loading` branch above deliberately: that one starts
        // the V3 progress poll, which reads a migrates_v3 row a V4 run never creates.
        if (create_container.hasClass('instawp-v4-resume')) {
            /*
             * NO screen switch here, deliberately. part-create-staging.php renders screen 5, the V4
             * notice, the seeded link and the hidden V3 block server-side, so the screen is already
             * correct before this runs -- and stays correct even if the JS never does.
             *
             * The screen used to be set with el_instawp_screen.val(5).trigger('change'), which runs
             * the #instawp-screen handler, which calls instawp_migrate_init(). Every refresh during
             * a live run therefore STARTED ANOTHER MIGRATION, and when that failed it hid
             * .migration-running so the screen disappeared. All this block owes the page now is the
             * poll.
             */

            // No elapsed timer here either -- see the v4 branch in instawp_migrate_init(). The
            // comment this replaces claimed it counted from the run's own start time rather than
            // from page load, which was true of the argument and irrelevant to the result: the value
            // never parsed, so nothing was ever rendered.
            instawp_staging_v4_watch(create_container);
        }

        const fieldValue = getQueryParameter('field');
        if (fieldValue) {
            blinkElement('.instawp-' + fieldValue + '-field', 3, 250);
        }

        $(document).mousedown(function (event) {

            let el_instawp_site_name = $('.instawp-site-name'),
                el_placeholder_text = el_instawp_site_name.find('.placeholder-text'),
                el_site_name_input_wrap = el_instawp_site_name.find('.site-name-input-wrap'),
                el_site_name_input = el_site_name_input_wrap.find('input#site-prefix'),
                $target = $(event.target);

            if (el_site_name_input_wrap.hasClass('hidden')) {
                return;
            }

            if (!$target.closest('.instawp-site-name .site-name-input-wrap input#site-prefix').length) {

                if (typeof el_site_name_input.val() !== 'undefined' && el_site_name_input.val().length > 0) {
                    let website_name = '';

                    website_name = el_site_name_input.val();
                    website_name = website_name.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-_]/g, '');
                    website_name = website_name + el_site_name_input.data('postfix');

                    el_instawp_site_name.find('p').html(website_name);
                } else {
                    el_instawp_site_name.find('p').html(el_instawp_site_name.find('p').data('text'));
                }

                el_placeholder_text.removeClass('hidden');
                el_site_name_input_wrap.addClass('hidden');
            }
        });
    });

    $(document).on('click', '.instawp-wrap .instawp-button-connect', function () {
        $.ajax({
            type: 'POST', url: plugin_object.ajax_url, context: this, data: {
                'action': 'instawp_connect_api_url',
                'security': plugin_object.security,
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

    // Remembers WHAT was dismissed rather than THAT something was, so the next poll does not
    // resurrect this message while a genuinely new one still gets through.
    $(document).on('click', '.instawp-wrap .instawp-v4-error-dismiss', function () {
        let el_error = $(this).closest('.instawp-v4-error');

        el_error.data('dismissed', el_error.find('.instawp-v4-error-message').text());
        el_error.addClass('hidden');
    });

    /*
     * Cancel a live V4 run.
     *
     * Deliberately does NOT decide what the screen should then show. The watcher is already polling
     * every 3s and already owns the terminal handling -- completed vs failed, the both-handlers
     * guard, the message. Repeating that decision here would be a second copy to keep in step, and
     * it would be wrong in the race this endpoint is most likely to lose: a 422 means the run had
     * ALREADY finished, and the poll reports that correctly while a local guess would not.
     *
     * So: ask, post, re-enable. The next poll tells the truth. Mirrors client-app's own cancel
     * button, which posts and then simply re-fetches status.
     *
     * Separate from the Abort handler below, which is V3's: that one clears a local interval and
     * navigates to ?clear=all, abandoning the screen while a V4 agent carries on migrating.
     */
    $(document).on('click', '.instawp-wrap .instawp-v4-cancel', function () {
        let el_button = $(this);

        // The confirm NAMES the consequence, in client-app's words: cancelling deletes the
        // destination site, and V3's "Do you really want to abort the migration?" does not say so.
        if (!confirm(el_button.data('confirm'))) {
            return;
        }

        /*
         * Say something IMMEDIATELY.
         *
         * The request behind this is slow -- the plugin calls client-app, which tells the agent to
         * stop and then deletes the destination site -- and the screen only catches up on the next
         * 3s poll. Disabling alone left the button reading "Cancel Migration" throughout, so the
         * click looked ignored and the outcome arrived seconds later with nothing in between.
         *
         * Original label kept rather than re-read from a data attribute, so the restore below
         * cannot disagree with what was actually on the button.
         */
        let original_text = el_button.text();

        el_button.prop('disabled', true).text(el_button.data('cancelling-text'));

        $.post(plugin_object.ajax_url, {
            'action': 'instawp_staging_cancel_v4',
            'security': plugin_object.security,
        }).done(function (response) {
            if (response && response.success) {
                // Left disabled and still reading "Cancelling...": the run IS ending, and the poll
                // hides the whole button within 3s. Restoring the label here would flash "Cancel
                // Migration" back onto a migration that is already stopping.
                return;
            }

            el_button.prop('disabled', false).text(original_text);
        }).fail(function () {
            // The run is still live, so cancelling remains something the user may retry.
            el_button.prop('disabled', false).text(original_text);
        });
    });

    $(document).on('click', '.instawp-wrap .instawp-migrate-abort', function () {
        let create_container = $('.instawp-wrap .nav-item-content.create');

        if (confirm('Do you really want to abort the migration?')) {
            clearInterval(create_container.attr('interval-id'));
            window.location = window.location.href.split("?")[0] + '?page=instawp&clear=all&iwp_nonce=' + plugin_object.security;
        }
    });

    $(document).on('click', '.instawp-wrap .instawp-reset-plugin', function () {

        if (!confirm('Do you really want to reset the plugin?')) {
            return;
        }

        let el_reset_button = $(this),
            create_container = $('.instawp-wrap .nav-item-content.create'),
            el_settings_form = $('.instawp-form'),
            el_settings_form_response = el_settings_form.find('.instawp-form-response');

        el_settings_form.addClass('loading');
        el_settings_form_response.html('');
        clearInterval(create_container.attr('interval-id'));

        $.ajax({
            type: 'POST', url: plugin_object.ajax_url, context: this, data: {
                'action': 'instawp_reset_plugin',
                'reset_type': 'soft',
                'security': plugin_object.security,
            }, success: function (response) {
                setTimeout(function () {
                    el_settings_form.removeClass('loading');
                    el_settings_form_response.addClass((response.success ? 'success' : 'error')).html(response.data.message);
                    window.location = window.location.href.split("?")[0] + '?page=instawp';
                }, 2000);
            }
        });
    });

    $(document).on('submit', '.settings .instawp-form, .developer .instawp-form', function (e) {

        e.preventDefault();

        let this_form = $(this), this_form_data = this_form.serialize();

        this_form.addClass('loading');

        $.ajax({
            type: 'POST', url: plugin_object.ajax_url, context: this, data: {
                'action': 'instawp_update_settings', 'form_data': this_form_data,
            }, success: function (response) {

                setTimeout(function () {
                    this_form.removeClass('loading');

                    if (response.success) {
                        showInstawpToast(response.data.message, 'success');
                    } else {
                        showInstawpToast(response.data.message, 'error');
                    }
                }, 1000);
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
    $(document).on('change', '#skip_large_files', function () {
        let el = $(this),
            subEl = $(document).find('.instawp-checkbox.exclude-file-item.large-file');

        subEl.not(":disabled").prop("checked", el.is(":checked")).trigger('change');
    });

    $(document).on('change', '#instawp-files-select-all', function () {
        let el = $(this),
            subEl = $(document).find('.exclude-files-container .instawp-checkbox.exclude-file-item');

        subEl.not(":disabled").prop("checked", el.is(":checked")).trigger('change');
    });

    $(document).on('change', '#instawp-database-select-all', function () {
        let el = $(this),
            subEl = $(document).find('.exclude-database-container .instawp-checkbox.exclude-database-item');

        subEl.not(":disabled").prop("checked", el.is(":checked")).trigger('change');
    });

    $(document).on('change', '.instawp-checkbox.exclude-file-item:not(.large-file)', function () {
        let el = $(this),
            parentEl = el.closest('.item'),
            subEl = parentEl.find('.sub-item .instawp-checkbox.exclude-file-item:not(.large-file)'),
            subElDisabled = subEl.not(":disabled");

        if ($(document).find('#active_plugins_only').is(":checked")) {
            subEl = subEl.not('.plugins').not('[class*=plugins-]');
        }

        if ($(document).find('#active_themes_only').is(":checked")) {
            subEl = subEl.not('.themes').not('[class*=themes-]');
        }

        if ($(document).find('#skip_media_folder').is(":checked")) {
            subEl = subEl.not('.uploads').not('[class*=uploads-]');
        }

        if ($(document).find('.exclude-files-container .instawp-checkbox.exclude-file-item:not(.large-file)').not(':checked').length) {
            $(document).find('#instawp-files-select-all').prop("checked", false);
        } else {
            $(document).find('#instawp-files-select-all').prop("checked", true);
        }

        subEl.prop("checked", el.is(":checked")).prop("disabled", el.is(":checked")).trigger('change');
    });

    $(document).on('change', '.instawp-checkbox.exclude-file-item', function () {
        let cEl = $(document).find('.instawp-checkbox.exclude-file-item:checked:not(.item-disabled)').not(":disabled");
        let sum = 0;
        let count = 0;
        cEl.each(function () {
            sum = sum + $(this).data('size')
            count = count + $(this).data('count')
        });
        if (cEl.length > 0) {
            $(document).find('.files-select').removeClass('hidden');
            $(document).find('.selected-files').html(`${count} files (${formatBytes(sum)}) skipped`);
        } else {
            $(document).find('.files-select').addClass('hidden');
        }
    });

    $(document).on('change', '.instawp-checkbox.exclude-database-item', function () {
        let cEl = $(document).find('.instawp-checkbox.exclude-database-item:checked').not(":disabled");
        let sum = 0;
        cEl.each(function () {
            sum = sum + $(this).data('size')
        });
        if (cEl.length > 0) {
            $(document).find('.db-tables-select').removeClass('hidden');
            $(document).find('.selected-db-tables').html(`${cEl.length} table(s) (${formatBytes(sum)}) skipped`);
        } else {
            $(document).find('.db-tables-select').addClass('hidden');
        }
    });

    $(document).on('change', '.instawp-wrap .nav-item-content.create input[type="radio"]:not(.plan-selector), .instawp-wrap .nav-item-content.create input[type="checkbox"]:not(.plan-selector)', function () {
        $(document).find('.files-size-container .total-size').html(plugin_object.trans.calculating_size_txt + ' (0%)').removeClass('loaded');
        $(document).find('.staging-plan-container').html('').addClass('hidden');
    });

    $(document).on('change', '.instawp-checkbox.exclude-database-item', function () {
        let el = $(this),
            parentEl = el.closest('.item'),
            subEl = parentEl.find('.sub-item .instawp-checkbox.exclude-database-item');

        if ($(document).find('.exclude-database-container .instawp-checkbox.exclude-database-item').not(':checked').length) {
            $(document).find('#instawp-database-select-all').prop("checked", false);
        } else {
            $(document).find('#instawp-database-select-all').prop("checked", true);
        }

        subEl.not(":disabled").prop("checked", el.is(":checked"));
    });

    $(document).on('change', '#active_plugins_only, input#active_themes_only, input#skip_media_folder, input#skip_log_tables', function () {
        $(document).find('.exclude-files-container').removeClass('p-4 h-80').html('<div class="loading"></div>');
        $(document).find('#instawp-files-select-all').prop("checked", false).prop("disabled", true);
        $(document).find('.instawp-files-sort-by').attr('data-sort', 'none').addClass('pointer-events-none');
        $(document).find('.instawp-checkbox.exclude-database-item.log-table').prop("checked", true);
        $(document).find('.files-select, .db-tables-select').addClass('hidden');
    });

    $(document).on('click', '.instawp-refresh-exclude-screen', function () {
        $(document).find('.exclude-files-container').removeClass('p-4 h-80').html('<div class="loading"></div>');
        $(document).find('.exclude-database-container').removeClass('p-4 h-80').html('<div class="loading"></div>');
        $(document).trigger("instawpLoadLargeFiles", [true]);
        $(document).trigger("instawpLoadDirectory", [false]);
        $(document).trigger("instawpLoadDatabase", [false]);
    });

    $(document).on('click', '.instawp-files-sort-by', function () {
        $(document).find('.instawp-files-sort-by').addClass('pointer-events-none');
        $(document).find('.exclude-files-container').removeClass('p-4 h-80').html('<div class="loading"></div>');
        $(document).find('#instawp-files-select-all').prop("checked", false).prop("disabled", true);
        $(document).trigger("instawpLoadDirectory", [true]);
    });

    $(document).on('click', '.expand-files-list', function () {
        $(this).addClass('hidden');
        $(document).find('.exclude-files-container').removeClass('hidden');
    });

    $(document).on('click', '.expand-database-list', function () {
        $(this).addClass('hidden');
        $(document).find('.exclude-database-container').removeClass('hidden');
    });

    $(document).on('click', '.instawp-database-sort-by', function () {
        $(document).find('.instawp-database-sort-by').addClass('pointer-events-none');
        $(document).find('.exclude-database-container').removeClass('p-4 h-80').html('<div class="loading"></div>');
        $(document).find('#instawp-database-select-all').prop("checked", false).prop("disabled", true);
        $(document).trigger("instawpLoadDatabase", [true]);
    });

    $(document).on('instawpLoadDirectory', function (e, sort) {
        let el_active_plugins_only = $('input#active_plugins_only'),
            el_active_themes_only = $('input#active_themes_only'),
            el_skip_media_folder = $('input#skip_media_folder'),
            el_sort_by = $(document).find('.instawp-files-sort-by').attr('data-sort'),
            el_loading = $(document).find('.exclude-files-container > .loading');

        if (el_sort_by === 'none' && sort) {
            el_sort_by = 'descending';
        } else if (el_sort_by === 'descending') {
            el_sort_by = 'ascending';
        } else if (el_sort_by === 'ascending') {
            el_sort_by = 'descending';
        }

        $(document).find('.instawp-files-details').text('');

        if (el_loading.length) {
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
                    'security': plugin_object.security
                },
                success: function (response) {
                    $(document).find('.exclude-files-container').html(response.data.content).addClass('p-4 h-80 hidden');
                    $(document).find('.expand-files-list').removeClass('hidden');
                    $(document).find('.instawp-files-details').text('(' + response.data.count + ') - ' + response.data.size);
                    $(document).find('#instawp-files-select-all').prop("disabled", false);
                    $(document).find('.instawp-files-sort-by').removeClass('pointer-events-none').attr('data-sort', el_sort_by);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log(errorThrown);
                }
            });
        }
    });

    $(document).on('instawpLoadDatabase', function (e, sort) {
        let el_sort_by = $(document).find('.instawp-database-sort-by').attr('data-sort'),
            el_loading = $(document).find('.exclude-database-container > .loading');

        if (el_sort_by === 'none' && sort) {
            el_sort_by = 'descending';
        } else if (el_sort_by === 'descending') {
            el_sort_by = 'ascending';
        } else if (el_sort_by === 'ascending') {
            el_sort_by = 'descending';
        }

        $(document).find('.instawp-database-details').text('');

        if (el_loading.length) {
            $.ajax({
                type: 'POST',
                url: plugin_object.ajax_url,
                context: this,
                data: {
                    'action': 'instawp_get_database_tables',
                    'sort_by': el_sort_by,
                    'security': plugin_object.security
                },
                success: function (response) {
                    $(document).find('.exclude-database-container').html(response.data.content).addClass('p-4 h-80');
                    $(document).find('.instawp-database-details').text('(' + response.data.count + ') - ' + response.data.size);
                    $(document).find('#instawp-database-select-all').prop("disabled", false);
                    $(document).find('.instawp-database-sort-by').removeClass('pointer-events-none').attr('data-sort', el_sort_by);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    console.log(errorThrown);
                }
            });
        }
    });

    $(document).on('instawpLoadLargeFiles', function (e, generate) {
        let el_skip_large_files = $('input#skip_large_files').is(":checked");

        $(document).find('.instawp-refresh-exclude-screen').prop("disabled", true).addClass('animate-spin');
        $(document).find('.instawp-exclude-container').addClass('hidden');

        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            data: {
                'action': 'instawp_get_large_files',
                'skip': el_skip_large_files,
                'generate': generate,
                'security': plugin_object.security
            },
            success: function (response) {
                if (response.data.content) {
                    $(document).find('.instawp-exclude-container').html(response.data.content).removeClass('hidden');
                    $(document).find('.instawp-refresh-exclude-screen').prop("disabled", false).removeClass('animate-spin');
                }

                if (response.data.has_data) {
                    $(document).find('.instawp-refresh-exclude-screen').prop("disabled", false).removeClass('animate-spin');
                } else {
                    $(document).trigger("instawpLoadLargeFiles", [false]);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(errorThrown);
            }
        });
    });

    $(document).on('click', '.instawp-wrap .expand-folder', function () {
        let el = $(this),
            imgEl = $(this).find('svg'),
            parentEl = el.closest('.item'),
            folderPath = el.data('expand-folder'),
            el_is_checked = parentEl.find('.instawp-checkbox.exclude-file-item').is(":checked"),
            el_active_plugins_only = $('input#active_plugins_only'),
            el_active_themes_only = $('input#active_themes_only'),
            el_sort_by = $(document).find('.instawp-files-sort-by').attr('data-sort'),
            el_skip_media_folder = $('input#skip_media_folder');

        if (imgEl.hasClass('rotate-icon')) {
            if (!parentEl.find('.sub-item').length) {
                $.ajax({
                    type: 'POST',
                    url: plugin_object.ajax_url,
                    context: this,
                    data: {
                        'action': 'instawp_get_dir_contents',
                        'path': '/' + folderPath,
                        'active_plugins': el_active_plugins_only.prop("checked"),
                        'active_themes': el_active_themes_only.prop("checked"),
                        'skip_media_folder': el_skip_media_folder.prop("checked"),
                        'sort_by': el_sort_by,
                        'is_checked': el_is_checked,
                        'security': plugin_object.security
                    },
                    beforeSend: function () {
                        parentEl.find('.cursor-pointer').append('<svg role="status" class="instawp-loader inline ml-3 w-4 h-4 text-primary-900 animate-spin" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg"><path data-v-fe125208="" d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="#E5E7EB"></path><path data-v-fe125208="" d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentColor"></path></svg>');
                    },
                    success: function (response) {
                        // parentEl.find('.sub-item').removeClass('hidden').html(response.data);
                        parentEl.append('<div class="pl-5 sub-item">' + response.data.content + '</div>');
                        //inputLabel.after('<svg role="status" class="inline ml-3 w-4 h-4 text-primary-900 opacity-70" fill="none" xmlns="http://www.w3.org/2000/svg"> <path style="fill: #005E54;" fill-rule="evenodd" clip-rule="evenodd" d="M1.59995 0.800049C2.09701 0.800049 2.49995 1.20299 2.49995 1.70005V3.59118C3.64303 2.42445 5.23642 1.70005 6.99995 1.70005C9.74442 1.70005 12.0768 3.45444 12.9412 5.90013C13.1069 6.36877 12.8612 6.88296 12.3926 7.0486C11.924 7.21425 11.4098 6.96862 11.2441 6.49997C10.6259 4.75097 8.95787 3.50005 6.99995 3.50005C5.52851 3.50005 4.22078 4.20657 3.39937 5.30005H6.09995C6.59701 5.30005 6.99995 5.70299 6.99995 6.20005C6.99995 6.6971 6.59701 7.10005 6.09995 7.10005H1.59995C1.10289 7.10005 0.699951 6.6971 0.699951 6.20005V1.70005C0.699951 1.20299 1.10289 0.800049 1.59995 0.800049ZM1.6073 8.95149C2.07594 8.78585 2.59014 9.03148 2.75578 9.50013C3.37396 11.2491 5.04203 12.5 6.99995 12.5C8.47139 12.5 9.77912 11.7935 10.6005 10.7L7.89995 10.7C7.40289 10.7 6.99995 10.2971 6.99995 9.80005C6.99995 9.30299 7.40289 8.90005 7.89995 8.90005H12.3999C12.6386 8.90005 12.8676 8.99487 13.0363 9.16365C13.2051 9.33243 13.3 9.56135 13.3 9.80005V14.3C13.3 14.7971 12.897 15.2 12.4 15.2C11.9029 15.2 11.5 14.7971 11.5 14.3V12.4089C10.3569 13.5757 8.76348 14.3 6.99995 14.3C4.25549 14.3 1.92309 12.5457 1.05867 10.1C0.893024 9.63132 1.13866 9.11714 1.6073 8.95149Z"></path> </svg>')
                        imgEl.removeClass('rotate-icon');
                        parentEl.find('.instawp-loader').remove();
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.log(errorThrown);
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
    $(document).on('click', '.instawp-wrap .instawp-refresh-staging-sites', function () {
        const el = $(this)
        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            data: {
                'action': 'instawp_refresh_staging_sites',
                'security': plugin_object.security
            },
            beforeSend: function () {
                el.find('svg').addClass('animate-spin-reverse');
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
        if (!confirm(plugin_object.trans.disconnect_txt)) {
            return;
        }
        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            data: {
                'action': 'instawp_disconnect_plugin',
                'api': true,
                'security': plugin_object.security
            },
            beforeSend: function () {
                $(document).find('.settings .instawp-form').addClass('loading');
            },
            success: function (response) {
                if (response.success === true) {
                    window.location = window.location.href.split("?")[0] + '?page=instawp';
                } else {
                    $(document).find('.settings .instawp-form').removeClass('loading');
                    if (confirm(response.data.message + ' ' + plugin_object.trans.disconnect_confirm_txt)) {
                        $.ajax({
                            type: 'POST',
                            url: plugin_object.ajax_url,
                            context: this,
                            data: {
                                'action': 'instawp_disconnect_plugin',
                                'api': false,
                                'security': plugin_object.security
                            },
                            beforeSend: function () {
                                $(document).find('.settings .instawp-form').addClass('loading');
                            },
                            success: function (response) {
                                if (response.success === true) {
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
                'security': plugin_object.security
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
                        $(document).trigger("instawpToggleSave", [name, value]);
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

        if ('instawp_hide_plugin_icon_topbar' === name && 'on' === value) {
            $('#wp-admin-bar-instawp').hide();
        } else if ('instawp_hide_plugin_icon_topbar' === name && 'off' === value) {
            setTimeout(function () {
                window.location.reload();
            }, 1000);
        }
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

    $(document).on('change', '#instawp_activity_log_interval', function () {
        let value = $(this).val();
        console.log(value);
        if (value === 'every_x_minutes') {
            $(document).find('.instawp-activity-log-interval-minutes-field').show();
        } else if (value === 'instantly') {
            $(document).find('.instawp-activity-log-interval-minutes-field').hide();
        }
    });

    let debounce = null;
    $(document).on('input', '#instawp_api_heartbeat, #instawp_activity_log_interval_minutes', function (e) {
        let el = $(this);
        let name = el.attr('id');
        let value = parseInt(Math.abs($(this).val()));

        if (!value) {
            return;
        }

        clearTimeout(debounce);
        debounce = setTimeout(function () {
            if (value >= el.attr('min') && value <= el.attr('max')) {
                ajaxSaveManagementSettings(name, value);
            } else {
                el.val(el.attr('min'));
                ajaxSaveManagementSettings(name, el.attr('min'));
            }
        }, 500);
    });
    // Remote Management settings save end //

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

    $(document).on('click', '.instawp-manager', function (e) {
        e.preventDefault();

        let el = $(this);
        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            data: {
                'action': 'instawp_process_ajax',
                'type': el.data('type'),
                'security': plugin_object.security
            },
            success: function (response) {
                if (response.success === false) {
                    return false;
                }
                if ('error_log' === el.data('type') && response.data.error_log) {
                    // Original text
                    let originalText = el.text();
                    // Change text
                    el.text(plugin_object.trans.copied);
                    // Copy to clipboard
                    navigator.clipboard.writeText(JSON.stringify(response.data.error_log));
                    // Revert text for 5 seconds
                    setTimeout(function () {
                        el.text(originalText);
                    }, 5000);
                } else if (response.data.login_url) {
                    window.open(response.data.login_url, '_blank');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(errorThrown);
            }
        });
    });

    $(document).on('click', '#visibility-expand', function (e) {
        e.preventDefault();
        $(this).addClass('hidden');
        $(document).find('#visibility-collapse, #visibility-box-area').removeClass('hidden');
    });

    $(document).on('click', '#visibility-collapse', function (e) {
        e.preventDefault();
        $(this).addClass('hidden');
        $(document).find('#visibility-expand').removeClass('hidden');
        $(document).find('#visibility-box-area').addClass('hidden');
    });

    $(document).on('click', '.full-screen-btn', function (e) {
        e.preventDefault();
        let el = $(document).find('#visibility-box');
        if (el.hasClass('full-screen')) {
            el.removeClass('full-screen')
            $(document).find('body').removeClass('overflow-hidden');
            $(document).find('#visibility-collapse').removeClass('hidden');
        } else {
            el.addClass('full-screen')
            $(document).find('body').addClass('overflow-hidden');
            $(document).find('#visibility-collapse').addClass('hidden');
        }
    });

    $(document).on('click', '.instawp-skip-item', function (e) {
        e.preventDefault();

        let el = $(this);
        el.closest('.visibility-content-item > .break-all').addClass('line-through');
        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            data: {
                'action': 'instawp_skip_item',
                'type': el.data('type'),
                'item': el.data('item'),
                'security': plugin_object.security
            },
            success: function (response) {
                console.log(response)
                if (response.success !== true) {
                    el.closest('.visibility-content-item > .break-all').removeClass('line-through');
                    alert(response.data.message)
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(errorThrown);
            }
        });
    });

    $(document).on('click', '.instawp-connect-plan-btn', function (e) {
        e.preventDefault();

        let el = $(this);
        el.prop('disabled', true).addClass('opacity-50').append('<svg class="instawp-loader inline ml-3 w-4 h-4 text-primary-900 animate-spin" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="#E5E7EB"></path><path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentColor"></path></svg>');

        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            data: {
                'action': 'instawp_change_plan',
                'plan_id': el.data('plan-id'),
                'security': plugin_object.security
            },
            success: function (response) {
                el.prop('disabled', false).removeClass('opacity-50').find('.instawp-loader').remove();
                if (response.success !== true) {
                    alert(response.data.message)
                } else {
                    $(document).find('#instawp-plans-container').html(response.data.plans);
                    $(document).find('#instawp-protect-site-btn').parent().remove();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                el.prop('disabled', false).removeClass('opacity-50').find('.instawp-loader').remove();
                console.log(errorThrown);
            }
        });
    });

    $(document).on('click', '#instawp-protect-site-btn', function (e) {
        e.preventDefault();
        let el = $(this);
        el.prop('disabled', true).addClass('opacity-50').append('<svg class="instawp-loader inline ml-3 w-4 h-4 text-primary-900 animate-spin" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="#E5E7EB"></path><path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentColor"></path></svg>');

        let plan_id = $(document).find('.instawp-connect-plan-btn:not(.pointer-events-none)').first().data('plan-id');
        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            data: {
                'action': 'instawp_change_plan',
                'plan_id': plan_id,
                'security': plugin_object.security
            },
            success: function (response) {
                el.prop('disabled', false).removeClass('opacity-50').find('.instawp-loader').remove();
                if (response.success !== true) {
                    alert(response.data.message)
                } else {
                    $(document).find('#instawp-plans-container').html(response.data.plans);
                    el.parent().remove();
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                el.prop('disabled', false).removeClass('opacity-50').find('.instawp-loader').remove();
                console.log(errorThrown);
            }
        });
    });

    $(document).on('click', '.instawp-update-plugin', function (e) {
        e.preventDefault();

        let updateButton = $(this),
            updateNoticeArea = updateButton.parent().find('.instawp-update-notice');

        $.ajax({
            type: 'POST',
            url: plugin_object.ajax_url,
            context: this,
            beforeSend: function () {
                updateNoticeArea.addClass('hidden').removeClass('inline-block');
                updateButton.prepend('<svg role="status" class="instawp-loader inline w-3 h-3 mr-1 text-primary-900 animate-spin" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg"><path data-v-fe125208="" d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="#E5E7EB"></path><path data-v-fe125208="" d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentColor"></path></svg>');
            },
            complete: function () {
                updateButton.find('.instawp-loader').remove();
            },
            data: {
                'action': 'instawp_update_plugin',
                'security': plugin_object.security,
            },
            success: function (response) {
                console.log(response.data.updated);
                if (response.data.updated) {
                    window.location.reload();
                } else {
                    updateNoticeArea.addClass('inline-block').removeClass('hidden');
                }
            }
        });
    });

})(jQuery, window, document, instawp_migrate);

