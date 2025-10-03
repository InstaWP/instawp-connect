=== InstaWP Connect - 1-click WP Staging & Migration ===
Contributors: instawp
Tags: clone, migrate, staging, backup, restore
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 0.1.1.8
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

Create a staging WordPress site from production (live site). Ideal for testing updates, version change or re-write. Sync back only the changes.

== Description ==
InstaWP Connect is a WordPress staging and migration plugin developed by the InstaWP team. It works as a companion tool for InstaWP - a famous tool for creating WordPress websites to test plugin or theme, product demo creation, client project delivery, and everything that can improve your WordPress workflows.

The aim of this plugin is to speed up your WordPress staging process and complete it in seconds without you putting in any effort.

Once you activate InstaWP Connect, you can click Create Site and connect your site to your InstaWP account. Once it’s connected, you can select to:

- Create quick staging [media folder will not be copied over].
- Create custom staging [skip plugins, themes, media, or any specific files or folders]
- Create full staging [everything from your live site will be added to the staging site]

One-click, and the staging creation process will begin!

Once the process is complete (it takes a few minutes depending on the site’s size), you will be able to find the staging of your live site in the InstaWP dashboard. Now, it’s possible to test, develop, edit, clone, migrate, or log in to your [staging site](https://instawp.com/wordpress-staging/) in seconds.

## PLUGIN FEATURES
Using this companion plugin, you can connect your existing WordPress sites anywhere on the internet to your InstaWP. After your site is connected, you can create 1-click staging websites from within the WP admin panel.

- Create full, custom, or quick staging websites in your InstaWP dashboard
- Auto-login to your Connected sites from InstaWP (https://app.instawp.io/connects)
- 2-way sync (push from staging to production and production to staging)
- Check the status of actions performed during 2-way sync
- Connect and disconnect your WordPress live site
- Keep all info about your site’s health.
- Do everything related to Remote Management securely (File Manager, Database Manager, Plugin / Themes Installer) - Beta
- Utilize dozens of InstaWP features by connecting your site to it.

## BENEFITS OF USING INSTAWP CONNECT
- Staging in seconds
- Zero mess in your hosting account
- Comes with a FREE Staging Environment
- Simplified migration and WordPress website backup
- Connect multiple staging sites to your live site.

## WHAT IS INSTAWP?
InstaWP is an online WordPress development environment to get you started with a new WP site within seconds. It allows you to create WordPress websites for testing, development, and staging in seconds.

You can get a free account here – [https://app.instawp.io/onboard](https://app.instawp.io/onboard).

### Key features include:
- Instant site creation
- Code Editor, DB Editor, and Logs viewer.
- Magic login to the WP admin panel.
- Migrate to any hosting provider that hosts your server (e.g., RunCloud, CloudWays, ServerAvatar, Pressable, SpinupWP, and WP Bolt)
- Access SFTP/SSH (pro).
- Drag-and-drop plugin installation
- Bulk theme/plugin installation from WP Repo using slugs
- Map a custom domain (pro) and host your site with InstaWP.
- Save your site as a template (snapshot of the site that can be reused).
- Shared templates for a public sandbox.
- Invite team members (pro).
- Git integration and GitHub actions support.
- Preset configurations.
- 3rd party services integrations (e.g., Mailchimp, active Campaign, Atarim, etc.)
- Core Faker

### Useful Resources

- [Visit Website](http://instawp.com/)
- [Read the InstaWP Documentation](https://docs.instawp.com/)
- [Know more about our API](https://docs.instawp.com/en/category/api-docs-12yssdo/)
- Read on How To Setup [WordPress Staging Site](https://instawp.com/set-up-wordpress-staging-site/)

Need support or want to partner with us? Go to our [website](http://instawp.com/) and use the live chat feature. You can also email us at support@instawp.com. We will be happy to assist.

== Installation ==

1. Generate a new API key from your InstaWP Account from https://app.instawp.io/user/api-tokens
2. Paste the key into the InstaWP Connect Settings. 
3. Go to "Create New" and connect the InstaWP Connect to the cloud. 
4. Now enjoy creating staging websites. 
5. Go to https://app.instawp.io/connects to see list of all your connected sites. 

== Screenshots ==

1. Staging Process - Select Staging Type.
2. Staging Process - Select Options.
3. Staging Process - Confirm Selection.
4. Staging Process - Monitor Progress.
5. All Staging Sites
6. Update API Key and Settings.

== Frequently Asked Questions ==
= How can I report security issues? =
You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage, and handle any security vulnerabilities. [Report a security vulnerability](https://patchstack.com/database/vdp/instawp-connect).

== Changelog ==

= 0.1.1.8 - 03 October 2025 =
- Fixed: Admin dashboard page CSS.
- Optimized: Connect Heartbeat.

= 0.1.1.7 - 16 September 2025 =
- Fixed: Staging site status restore issue.
- Fixed: Display of the correct connected team name.
- Improved: Error logging.
- Updated: Dependency packages.

= 0.1.1.6 - 12 August 2025 =
- Fixed: Removed migration helper plugins after migration.
- Fixed: Excluded migration helper plugins from migration.

= 0.1.1.5 - 06 August 2025 =
- Fixed: Source and Destination migration file path.

= 0.1.1.3 - 03 July 2025 =
- Improved: Optimized sync query performance
- Added: Retry mechanism for sync failures 
- Added: Error logs
- Added: Exclusion of custom plugin/theme sync events
- Fixed: Migration issues on sites with a single PHP worker
- Fixed: Activity Logs not working.
- Fixed: bunny.net CDN cache purging was not working.

= 0.1.1.2 - 24 June 2025 =
- Added: Plan validation and auto select plan based on site size.
- Fixed: Plan description text.
- Fixed: Untranslated texts.

= 0.1.1.1 - 20 June 2025 =
- Fixed: Staging site can't be created for some users.

= 0.1.1.0 - 20 June 2025 =
- NEW: Site Plan Selection in Confirmation screen.
- NEW: Added 2 Way Sync support for Mega Menu plugin.
- Fixed: UI issues and back/next navigation state.
- Fixed: Destination migration file path for Elementor cloud.
- Fixed: Reduced excessive event log for user_roles option sync.

= 0.1.0.93 - 09 June 2025 =
- Added: Migration support without requiring Connects
- Updated: instawp_last_migration_details option post plugin installation
- Updated: Excluded wp-settings.php for WP Cloud migrations
- Fixed: Corrected siteurl after migration

= 0.1.0.92 - 28 May 2025 =
- Fix: Debug Log not enabling for Site Management

= 0.1.0.91 - 26 May 2025 =
- Fix: Handle the response from the generate destination file function
- Update: Plugin zip url

= 0.1.0.90 - 20 May 2025 =
- Update: Updated the messages of Migration screen
- Update: Updated Site management features list
- Fix: Connected team name and user email ID were not showing on some cases

= 0.1.0.89 - 07 May 2025 =
- Enhancement: Redesigned initial plugin screen for better user experience
- Enhancement: Added visibility of connected team name and user email ID on plugin interface
- Update: Upgraded Adminer database manager to latest version for improved security and features
- Fix: Optimized database queries to reduce multiple calls on Connect plugin page
- Fix: Updated Connect helpers package with latest improvements and fixes

= 0.1.0.88 - 05 May 2025 =
- Fix: V1 API response code
- Fix: Enhanced clarity in error messages
- Fix: Push destination file handling in end-to-end migration

= 0.1.0.87 - 17 April 2025 =
- FIX: Application password verification issue.

= 0.1.0.86 - 09 April 2025 =
- NEW: Exposed update migration API handle post migration activities on the website.
- FIX: Yoast seo metadata sync.
- Fix: Optimized fetch sync per page.
- FIX: InstaWP pull migration issue fixed.
- FIX: Performance optimization done to the entire plugin.

= 0.1.0.85 - 25 March 2025 =
- FIX: Fixed pull migration SSL support issue.

= 0.1.0.84 - 12 March 2025 =
- FIX: Removed file manager from connect helpers to avoid security issues.
- FIX: Exclude few option keys from migration.

= 0.1.0.83 - 07 March 2025 =
- NEW: Added plugin deactivation warning while there is a migration in progress.
- NEW: Added migration in progress notification on the top admin bar.
- FIX: Removed un-used codes.
- FIX: Added email support on magic login api.

= 0.1.0.82 - 05 March 2025 =
- FIX: Optimized query for fetching sync events.
- FIX: Fixed post migration cleanup for auto migration of demo site.

= 0.1.0.81 - 24 February 2025 =
- NEW: Added new plugin page banner.
- FIX: Fixed push migration support for WP Cloud infrastructure.

= 0.1.0.80 - 12 February 2025 =
- NEW: Dynamic sync batch size adjustment.
- NEW: Media import from source with sync server fallback on errors.
- NEW: Exception handling in 2 Way Sync events receiver API.
- FIX: Prevent duplicate image uploads during sync.
- FIX: Store connect origin to check if the connect is valid for the current domain.
- FIX: Fixed pull and push migration flow.
- FIX: Fixed website disconnect flow from plugin side.

= 0.1.0.79 - 23 January 2025 =
- FIX: Fixed default username for magic login.
- FIX: Added managed parameter to config API.
- FIX: Fixed cache clearing issue with WP Rocket.
- FIX: Push migration exclude files check.

= 0.1.0.78 - 16 January 2025 =
- NEW: Added extra log message for handling pull migration failure.

= 0.1.0.77 - 15 January 2025 =
- NEW: Added FSE theme global styles, Templates, Template parts sync support.
- FIX: Fixed 404 errors in push migration.

= 0.1.0.76 - 10 January 2025 =
- FIX: Added support to disconnect connect via CLI.
- FIX: Fixed support to mark staging site as parent via API.

= 0.1.0.75 - 07 January 2025 =
- FIX: Added changes to directly connect to the advanced plan.
- FIX: Added changes to create connect as unmanaged.
- FIX: Fixed pull migration issue.

= 0.1.0.74 - 03 January 2025 =
- NEW: Show warning message if the plugin is not on latest version.
- FIX: Fixed file skip support on WP Cloud infrastructure.
- FIX: Added support for getting site_url from database not from built-in function during the migration.
- FIX: Fixed openssl_encrypt warning issue regarding IV.
- FIX: Fixed IWPDB issue during pull migration file transfer.

= 0.1.0.73 - 26 December 2024 =
- FIX: Fixed file permission issue for the info file on push migration.

= 0.1.0.72 - 19 December 2024 =
- FIX: Moved push migration info file location to root directory.

= 0.1.0.71 - 16 December 2024 =
- FIX: Fix push migration issue.

= 0.1.0.70 - 16 December 2024 =
- NEW: Added white label support for enterprise customers.
- NEW: Added support to toggle Activity logs via REST API.
- FIX: Stabilize staging creation process.

= 0.1.0.69 - 06 December 2024 =
- FIX: Added support for multisite regarding the wp-config.
- FIX: Added database backup before the push migration.
- FIX: Change staging icon inside the plugin.

= 0.1.0.68 - 27 November 2024 =
- FIX: Sync image with webp extension.
- FIX: Sync Spectra block content.
- FIX: Sync media url from content.
- FIX: Sync Stackable image and taxonomy blocks.
- FIX: Sync home url
- FIX: Fixed htaccess missing rule on skip media migration.

= 0.1.0.67 - 22 November 2024 =
- FIX: Send Site Title, Favicon url, PHP version, Plugin version and WordPress version on initial connect.
- FIX: Added support for core update in activity logs.
- FIX: Fixed push migration issue of database table prefix.
- FIX: Update staging sites list after connect.
- FIX: Added support to clear sync events via WP CLI.

= 0.1.0.66 - 19 November 2024 =
- FIX: Sync Kadence form, dynamic list, dynamic HTML, Navigation block content.
- FIX: Fixed database table schema missing issue in pull migration.

= 0.1.0.65 - 15 November 2024 =
- NEW: Push migration create backup of plugins, themes and mu-plugin files before start.
- FIX: Sync plugin and theme update php error
- FIX: Auto fetch JWT from API.
- FIX: Added support for wp-content in the root path.
- FIX: Elementor image or icon upload sync.
- FIX: Scaled image sync.
- FIX: Elementor dynamic page content
- FIX: Kadence block css sync.
- FIX: WordPress editor image, icon and gallery block sync.
- FIX: Kadence dynamic page content sync.
- FIX: Sync post content links.

= 0.1.0.64 - 30 October 2024 =
- New: Sync WooCommerce order notes.
- NEW: Activity Log feature is now available.
- NEW: Disable WooCommerce email for WooCommerce data sync.
- FIX: Sync WooCommerce product variant.
- FIX: Sync WooCommerce order item meta.

= 0.1.0.63 - 29 October 2024 =
- NEW: Pull migration plugin and theme installation fallback to pages migration.
- FIX: Improve pull and push migration scripts.

= 0.1.0.62 - 28 October 2024 =
- FIX: Magic login not working if site is connected via InstaWP Dashboard.

= 0.1.0.61 - 26 October 2024 =
- FIX: Exclude whitelabel if WP CLI is in use.
- FIX: 2 Way Sync API for list events not working correctly on staging sites.
- FIX: Fix database table fetching issue in iwpdb.
- FIX: Remove exclude path if wp item failed to install via inventory.

= 0.1.0.60 - 17 October 2024 =
- FIX: Added API Endpoint to refresh all staging sites list.

= 0.1.0.59 - 15 October 2024 =
- FIX: Fixed plugin version.

= 0.1.0.58 - 15 October 2024 =
- NEW: WordPress core folders are now excluded from the migration process.
- FIX: Issue with syncing WooCommerce meta data.
- FIX: WooCommerce order ID now correctly displays on the staging site, matching the production site.
- FIX: WooCommerce order creation and modification dates now sync properly.
- FIX: Corrected synchronization of WooCommerce order totals.
- FIX: Resolved issue where blank items were being added to WooCommerce orders on the staging site.
- FIX: WooCommerce order origin now syncs correctly.
- FIX: Post meta data is now saved correctly when triggered by action hooks.
- FIX: Proper synchronization of post meta data saved via the save_post hook.

= 0.1.0.57 - 3 October 2024 =
- FIX - Showing error if wp-config.php is not writable.
- FIX - Duplicate plugin and theme installation slug in migration settings
- NEW - Show progress of plugin and theme installations during staging creation.
- NEW - Add plugin and theme details in file count table
- FIX - Relative directory path for migration
- FIX - Delete staging sites when the plugin is disconnected from the app.
- FIX - Fixed 2 way sync event recording for woocommerce

= 0.1.0.56 - 18 September 2024 =
- NEW - Added a faster migration method on the staging site creation.
- FIX - Fixed user interface issues while creation staging site.
- FIX - Fixed Debug Log can't delete issue.
- FIX - Set default heartbeat interval to 4 hours.
- FIX - 2 Way Sync Customizer Changes not saving.
- FIX - 2 Way Sync error in Elementor checking.
- FIX - 2 Way Sync Divi Builder issue.

= 0.1.0.55 - 10 September 2024 =
- FIX - Fixed BuddyBoss blocking InstaWP Connect plugin's REST API.
- FIX - Improved migration error handling.
- FIX - Fixed Migration Issues.
- FIX - Plugin and theme update api doesn't clear old update data.

= 0.1.0.54 - 5 September 2024 =
- FIX - Fixed migration related issues in push migration.

= 0.1.0.53 - 4 September 2024 =
- FIX - Modified option to hide plugin menu item from users.
- FIX - Sync Tab Access option is not saving if there is no option selected.
- FIX - Missing Magic Login icon.

= 0.1.0.52 - 3 September 2024 =
- NEW - Scheduled Updates endpoint.
- FIX - Removed InstaWP menu from topbar and menu for sub-sites in multisite.
- FIX - Plugin settings not accessible on some cases of InstaWP Live.
- FIX - Added checksum and retry logic in pull migration.
- FIX - Migration issues.

= 0.1.0.51 - 23 August 2024 =
- NEW - Added support for retaining users after push in auto migration.
- FIX - Fixed htaccess for url path.

= 0.1.0.50 - 14 August 2024 =
- NEW - Added cache flushing in post migration activities.
- FIX - Fixed API Endpoints method.

= 0.1.0.49 - 13 August 2024 =
- NEW - Added 2 Way Sync APIs.
- FIX - Fixed WP CLI command not working.
- FIX - Fixed WordPress table prefix issue.
- FIX - Fixed API Endpoint method.

= 0.1.0.48 - 7 August 2024 =
- NEW - Auto Updates endpoints.
- FIX - Removed File Manager due to security concern.
- FIX - 2 Way Sync for images present in post content.
- FIX - Added bot checking for temporary login url.
- FIX - Changed Sites endpoint.

= 0.1.0.47 - 19 July 2024 =
- NEW - Added Users API.
- FIX - Added staging in top bar and add notice for local cli migration.
- FIX - Fixed heartbeat total size issue on WordPress v6.6.
- FIX - Fixed skip rocket cache tables.
- FIX - Fixed migration issues.

= 0.1.0.46 - 15 July 2024 =
- FIX - Changes "Create Staging" text to "Setting" in case of staging site.
- FIX - Fixed theme install sync event not recording.
- FIX - Fixed authorization header not working on some cases.
- FIX - Fixed migration issues.

= 0.1.0.45 - 8 July 2024 =
- NEW - Added Temporary Login support.
- FIX - Fixed User interface issues.
- FIX - Fixed support for disable blog plugin for file & db manager.
- FIX - Fixed scan command.
- FIX - Fixed heartbeat issue.

= 0.1.0.44 - 28 Jun 2024 =
- FIX - Fixed security vulnerability issue in auto login.

= 0.1.0.43 - 27 Jun 2024 =
- NEW - WP Scanner CLI commands added.
- NEW - Implemented attachment syncing using s3 bucket in 2 way sync.
- NEW - Added real time migration logs.
- FIX - Fixed file name validation method in pull migration.
- FIX - Fixed connect disconnection on deactivation and restore on activation.

= 0.1.0.42 - 14 Jun 2024 =
- FIX - Fixed PHP errors on some sites.
- FIX - Fixed htaccess issue after migration.
- FIX - Fixed GridPane hosting missing path issue.

= 0.1.0.41 - 12 Jun 2024 =
- NEW - Integrated 3rd party API to log 2 way sync events from any 3rd party plugin.
- FIX - Renamed all actions and filters.
- FIX - Fixed temporary path populate logic.
- FIX - Fixed 2 way sync events display logic.
- FIX - Fixed PHP errors on some sites.

= 0.1.0.40 - 05 Jun 2024 =
- FIX - Fixed config API.
- FIX - Fixed migration script of pulling sites.

= 0.1.0.39 - 04 Jun 2024 =
- NEW - WooCommerce Summary API.
- FIX - Fixed config API and added extra security.
- FIX - Update the migration messages and logs.
- FIX - Encrypted push options data.
- FIX - Fixed file size calculating issue in serve script.

= 0.1.0.38 - 24 May 2024 =
- FIX - Excluded SEOPress 404 posts from 2 way sync.
- FIX - Fixed headers already sent issue.
- FIX - Fixed push migration API responses.
- FIX - Fixed post migration cleanup API.
- FIX - Post migration cleanup API.

= 0.1.0.37 - 20 May 2024 =
- NEW - Added 2 way sync navigation menu support.
- NEW - Added Edge Cache purge support.
- FIX - Push Migration table prefix issue.
- FIX - Added Push Migration event logging.
- FIX - Fix push json file name issue.
- FIX - Added 2 way sync event display issue.
- FIX - Update and Delete plugins/themes API.

= 0.1.0.36 - 14 May 2024 =
- FIX - File manager missing error due to auto deletion of file from server.
- FIX - 2 way sync elementor issue.
- FIX - Mechanism to get the root path.

= 0.1.0.35 - 13 May 2024 =
- FIX - 2 way sync plugin updates are not working.
- FIX - 2 way sync even if WooCommerce toggle off then product & orders recorded.
- FIX - 2 way sync duplicate events are recording.
- FIX - Show Staging site list in descending order by site created time.
- FIX - Fix multiple instawp-cache-cleared parameter in URL after cache clear.
- FIX - Fix auto login issue if default login url is blocked or changed by any plugin.
- FIX - Several other migration related issues.

= 0.1.0.34 - 3 May 2024 =
- NEW - Added Expand/Collapse to migration visibility.
- FIX - Typos and untranslated strings.
- FIX - 2 way sync duplicate event logging.

= 0.1.0.33 - 2 May 2024 =
- FIX - Fixed display of parent sites list for 2 way sync.

= 0.1.0.32 - 2 May 2024 =
- FIX - Fixed DOMAIN_CURRENT_SITE constant support for push.
- FIX - Fixed missing class at mark at staging site.
- FIX - Post migration cleanup API.

= 0.1.0.31 - 26 Apr 2024 =
- FIX - Fixed File Manager Settings was not saving.
- FIX - Fixed wrong text in sync quota warning.
- FIX - Added scroll view to large files list.
- FIX - Updated AdminerEvo to latest version.
- FIX - Fixed Plugin activation after update using API.
- FIX - Fixed PHP errors at cleanup.
- FIX - Post migration cleanup API.

= 0.1.0.30 - 22 Apr 2024 =
- FIX - Fixed plugin config API.
- FIX - Fixed post cleanup API and starting to store last migration details.

= 0.1.0.29 - 19 Apr 2024 =
- FIX - Fixed plugin uninstallation issue on some cases.
- FIX - Stabilize the staging site creation process.

= 0.1.0.28 - 17 Apr 2024 =
- NEW - Added Migration Visibility.
- NEW - Added support for installing plugins/themes in post cleanup migration API.
- FIX - Stabilize the staging site creation process.

= 0.1.0.27 - 11 Apr 2024 =
- FIX - Migration not working with db host ports.
- FIX - API Signature checking functions.
- FIX - Caching issue for WordPress Posts 2 Way Sync.

= 0.1.0.26 - 10 Apr 2024 =
- FIX - Fixed API Domain missing issue.

= 0.1.0.25 - 9 Apr 2024 =
- FIX - Fixed Action Scheduler error after staging creation.
- FIX - Strengthen plugin security.
- FIX - All WPCS-related issues.
- FIX - Remove legacy codes and implement code reusability.
- FIX - Replaced wrong database constant.
- FIX - Total Site Size calculation issue.
- FIX - Skip size in the confirmation window.

= 0.1.0.24 - 1 Apr 2024 =
- FIX - Fixed plugin update issue using REST API.

= 0.1.0.23 - 29 Mar 2024 =
- NEW - Added post migration cleanup API.
- FIX - Fixed vulnerability issue in a REST API.
- FIX - Added check for empty and/or invalid filepath during local push.

= 0.1.0.22 - 26 Mar 2024 =
- NEW - Added Magic Login support for Solid Security plugin.
- FIX - 2 way sync issues.

= 0.1.0.21 - 22 Mar 2024 =
- NEW - Added full support for PHP 5.6.
- NEW - Added User Create/Update/Delete APIs.
- FIX - Migration exclude screen undefined path issue.
- FIX - Magic login errors.
- FIX - Fix heartbeat actions ordering.
- FIX - Fix Total site calculation for migration.
- FIX - Only changed post object parameters will be logged in 2 way sync.
- FIX - 2 way sync optimizations.
- FIX - Magic login support for maintenance mode plugin by webfactory.
- FIX - Rest api blocking issue by security plugins.

= 0.1.0.20 - 15 Mar 2024 =
- NEW - Added support for PHP 5.6.
- FIX - Improve plugin performance.
- FIX - Increase security in 2-way-sync.
- FIX - 2 way sync issues.
- FIX - Added 2 way sync from local site to staging.

= 0.1.0.19 - 5 Mar 2024 =
- NEW - Added Activity Log.
- NEW - Added CLI command for reset staging.
- NEW - Added migration support for Elementor Cloud.
- NEW - Added option to hide shortcut and hide flashing.
- FIX - Improve plugin overall performance every dashboard page loading.
- FIX - Fixed config issues after staging creation from local environment.
- FIX - Fixed support for COOKIE_DOMAIN constant in wp-config.
- FIX - Stabilize the staging site creation process.
- FIX - 2 way sync event date timezone issue.
- FIX - 2 way sync duplicate event issue.
- FIX - 2 way sync performance optimizations.
- FIX - Added animation on staging site refresh button.
- FIX - Staging site list remove automatically due to transient

= 0.1.0.18 - 20 Feb 2024 =
- NEW - Added option to filter sync changes based on the status.
- FIX - Missing query var error.
- FIX - Handle database host/port/IPv6 support for push.
- FIX - File Manager auto login issue.
- FIX - Database Manager DB Host issue.
- FIX - Data missing for Heartbeat.
- FIX - Staging sites list not updating upon clicking refresh button.
- FIX - Fixed connect id issue.
- FIX - URL replace not working for Elementor.
- FIX - 2 way sync Issues.

= 0.1.0.17 - 15 Feb 2024 =
- FIX - Fixed iwpdb database table structure.
- FIX - Fixed connect id issue.

= 0.1.0.16 - 15 Feb 2024 =
- NEW - Deselect search engine visibility option after go live.
- NEW - Exclude cache folders by default on the migration.
- FIX - Fixed regular expression for missing images issue in migration.
- FIX - Fixed iwpdb database table structure for missing file issue in migration.
- FIX - Minor updates on UI of the plugin page.
- FIX - Fixed 2-way-sync for Elementor plugin.
- FIX - Comment unnecessary lines from htaccess during the migration for staging websites.
- FIX - Fixed htaccess rule for images from both source and current website.
- FIX - Fixed root path finding logic in push/go live case.

= 0.1.0.15 - 9 Feb 2024 =
- NEW - Added cleanup on uninstalling the plugin.
- NEW - Added admin bar navigation on InstaWP icon.
- NEW - Added connect dashboard link to the connect message.
- FIX - Fixed screen reload after migration finished.
- FIX - Fixed unnecessary API hit to staging-sites endpoint.
- FIX - Fixed search engine visibility issue.
- FIX - Fixed redirect issue after connect from authentication screen.
- FIX - Fixed 2-ways-sync issues.
- FIX - Fixed migration issues during staging website creation.
- FIX - Fixed issue with missing database column.

= 0.1.0.14 - 30 Jan 2024 =
- NEW - Added support for Core, Theme and Plugin Update via REST API.
- NEW - Added support for Theme and Plugin Activation and Deactivation via REST API.
- NEW - Added support for Enable or Disable Theme and Plugin Auto Update via REST API.
- FIX - Auto Disconnect from InstaWP API.
- FIX - Auto login method updated and fixed multiple issues regarding the magic login.

= 0.1.0.13 - 25 Jan 2024 =
- NEW - Added support for WordPress Customizer.
- NEW - Added support for RunCloud Hub cache purge support.
- FIX - Changed image upload mechanism for 2 way sync.
- FIX - Added check for heartbeat run 10 times.
- FIX - Install sync tables only when needed.
- FIX - Fixed mysql allocated length issue in IWPDB.
- FIX - Migration Issues.

= 0.1.0.12 - 19 Jan 2024 =
- NEW - Added support for Flywheel.
- NEW - Added support for WooCommerce in 2 ways sync.
- FIX - Fixed file mimetype issue during the migration process.

= 0.1.0.11 - 15 Jan 2024 =
- NEW - Added support for Malcare security plugin.
- FIX - Fixed migration issues while checking usages.

= 0.1.0.10 - 12 Jan 2024 =
- FIX - Fixed vulnerability issues.
- FIX - Fixed issues of event tracking in 2 way sync.

= 0.1.0.9 - 10 Jan 2024 =

- NEW - Added support for local websites using WP CLI.
- FIX - Updated Composer libraries.
- FIX - File and Database Manager auto login screen loader direction.
- FIX - Missing Nonce checking and access control.
- FIX - Fixed 2 ways sync limit issue.

= 0.1.0.8 - 4 Jan 2024 =

- NEW - Added support for custom name of the staging website.
- FIX - Fixed 2 way sync event tracking issue.

= 0.1.0.7 - 28 Dec 2023 =

- NEW - Really Simple SSL Redirect plugin support in htaccess file.
- FIX - Quick staging actions now skip everything possible to make the migration real quick.
- FIX - Display InstaWP icon at admin bar in frontend as well.
- FIX - Fixed heartbeat sending issue.
- FIX - Fixed migration issues with wp-config file.


= 0.1.0.6 - 22 Dec 2023 =

- NEW - Added row level database tracking progress on the migration.
- FIX - Fixed heartbeat sending issue.

= 0.1.0.5 - 20 Dec 2023 =

- NEW - Added option to enable or disable 2 way sync events.
- NEW - Added option to individually select events for sync.
- NEW - Send heartbeat immediately after connect a website.
- FIX - File Permission issue if the file name has any unwanted characters.
- FIX - Fixed migration issue with wp-config file on parent directory.

= 0.1.0.4 - 14 Dec 2023 =

- FIX - Revert support for PHP 7.4.
- FIX - Fixed issue with 2 way sync recording.

= 0.1.0.3 - 14 Dec 2023 =

- NEW - Added a way to see debug log from inside our plugin.
- NEW - Taxonomy and WP Option support for 2 way sync.
- FIX - Fixed issue with 2 way sync specially for Posts (CPT), Plugin and Theme changes.
- FIX - Stabilize the staging site creation process.

= 0.1.0.2 - 12 Dec 2023 =

- NEW - Added file permission issue during precheck.
- FIX - Stabilize the staging site creation process.

= 0.1.0.1 =

- NEW - Added hard disable search engine visibility for staging sites.
- FIX - Fixed CSS Loading in plugin due to http instead of https.

= 0.1.0.0 - 1 Dec 2023 =

- FIX - Incorrect versioning, updated to 0.1.0.0 instead of 0.0.1.0

= 0.1.0.0 - 1 Dec 2023 =

- FIX - Fixed 2 migrate v3 issues.
- FIX - Fixed auto login issue.
- FIX - Stabilize the staging site creation process.
- FIX - Remove SQLite PHP extension or PDO SQLite PHP check.

= 0.0.9.51 - 28 Nov 2023 =

- FIX - Fixed 2 Way Sync not working.
- FIX - Fixed Site Dropdown CSS.
- FIX - Fixed some PHP errors.
- FIX - Optimized 2 Way Sync Codebase.
- FIX - Heartbeat event logic.
- FIX - Fixed auto login issue.
- FIX - Fixed migration issue in destination script.
- FIX - Stabilize the staging site creation process.

= 0.0.9.50 - 22 Nov 2023 =

- NEW - Added connect logs to app.
- FIX - Fixed get_source_site_detail api.
- FIX - Removed SqlLite support.
- FIX - Fix Download log button in failed screen.
- FIX - Stabilize the staging site creation process.

= 0.0.9.49 - 14 Nov 2023 =

- FIX - Fixed migration issues and stabilize the process.
- FIX - Table export logic.
- FIX - Removed URL extra slash.

= 0.0.9.48 - 11 Nov 2023 =

- FIX - Skip database tables was not sending create table schema.
- FIX - Skip known log tables during the migration.
- FIX - Removed action scheduler.
- FIX - Changed recording blink for 2 way sync.
- FIX - Fixed displaying sites url on staging sites list.

= 0.0.9.47 - 8 Nov 2023 =

- FIX - Implemented new migration method and stabilize the process.
- FIX - Fixed .htaccess issue during the migration.
- FIX - Added pre-check before the migration start.

= 0.0.9.46 - 1 Nov 2023 =

- FIX - PharData library is now working properly.
- FIX - Improvement in Migrate v3 Push to make it stable.
- FIX - Improvement in Migrate v3 Pull to make it stable.
- FIX - Show Inventory toggle option and make it checked by default.
- FIX - Config API Key issue.
- FIX - Calculation of total sizes to check usages limit before migration start.
- FIX - Fixed the issue of automatically changing of api domain and connect id.
- FIX - WordFence whitelist IP address dynamically.

= 0.0.9.45 - 27 Oct 2023 =

- FIX - Provide an alternative of Phardata library when ZipArchive is not present
- FIX - Stop staging process if SQLite library is not present
- FIX - Improvement in staging process for stability
- FIX - Removed unused files, making plugin .zip less than 500kb.

= 0.0.9.44 =
- 26/10/2023 - FIX - Skip Large files option not working.
- 26/10/2023 - FIX - Collapsible Files & Table skip screen.
- 26/10/2023 - FIX - Confirmation of files & database size.
- 26/10/2023 - FIX - Removed Reset option.
- 26/10/2023 - NEW - Migration (v3) functionality added (beta).
- 26/10/2023 - FIX - Include Parent theme if 'active_themes_only' is selected.
- 26/10/2023 - NEW - Site usage API for Remote Management.
- 26/10/2023 - FIX - File and Database manager not working for Remote Management.
- 26/10/2023 - FIX - Several 2 Way Sync fixes.
- 26/10/2023 - FIX - 2 Way Sync - Pagination UI.
- 26/10/2023 - FIX - 2 Way Sync - Event type count fixes for each batch.
- 26/10/2023 - FIX - 2 Way Sync - Fixed event pagination issue.

= 0.0.9.43 =
- 18/10/2023 - FIX - Add supported plugins check.
- 18/10/2023 - FIX - Fix character check issue.
- 18/10/2023 - FIX - Stabilise migrations/staging process.

= 0.0.9.42 =- 17/10/2023 - NEW - Add support for non-zip and non-pdo servers.
- 17/10/2023 - FIX - Stabilise migrations/staging process.
- 17/10/2023 - FIX - Improve 2 way sync functionalities.

= 0.0.9.41 =
- 11/10/2023 - FIX - Fix folder creation issue.

= 0.0.9.40 =
- 11/10/2023 - NEW - Re-write of entire staging process from ground up, Goal: Stable staging process.
- 11/10/2023 - FIX - Removed un-used files.

= 0.0.9.33 =
- 29/09/2023 - NEW - Added option to whitelist InstaWP IP during staging creation.
- 29/09/2023 - FIX - Fixed repeat staging site creation issues.
- 29/09/2023 - FIX - Implemented excluded file and folders size check.
- 29/09/2023 - FIX - Stabilise migrations/staging process.
- 29/09/2023 - FIX - Replaced Tailwind CDN with NPM Package.

= 0.0.9.32 =
- 25/09/2023 - NEW - Introduced batch-based sync system.
- 25/09/2023 - FIX - Fixed repeat staging site creation issues.
- 25/09/2023 - FIX - Stabilise migrations/staging process.

= 0.0.9.31 =
- 14/09/2023 - NEW - Exclude selected files from the migration process.
- 14/09/2023 - FIX - Bath syncing change events for 2 way sync.
- 12/09/2023 - FIX - Deployer mode updated with new WaaS flow.
- 14/09/2023 - FIX - Stabilise migrations/staging process.

= 0.0.9.30 =
- 08/09/2023 - NEW - Split uploading backup files to cloud part based.
- 08/09/2023 - NEW - Exclude unnecessary backup files created from other plugins during the migration process
- 08/09/2023 - FIX - Fixed abort migration issues and duplicate migration starting.

= 0.0.9.29 =
- 04/09/2023 - FIX - Fixed critical error on staging sites loading.
- 04/09/2023 - FIX - Fixed disconnect API with a warning about site deletion.
- 04/09/2023 - FIX - Fixed two-way sync sites listing issue.

= 0.0.9.28 =
- 02/09/2023 - FIX - Fixed database restore timeout issue.
- 02/09/2023 - FIX - Fixed Radis Object Cache plugin conflict.

= 0.0.9.27 =
- 31/08/2023 - NEW - Added disconnect button in plugin screen.
- 31/08/2023 - FIX - Fixed loading media from parent site when doing quick migration.
- 31/08/2023 - FIX - Fixed flush permalink issue through while happening through CLI.

= 0.0.9.26 =
- 28/08/2023 - FIX - Fixed repetitive migration process.

= 0.0.9.25 =
- 24/08/2023 - NEW - Added plugin installation/update/delete features in two-way sync.
- 24/08/2023 - NEW - Added media transferring features in two-way sync.
- 24/08/2023 - FIX - Fixed migration abort issues from external sources.
- 24/08/2023 - FIX - Fixed starting of repetitive migration after completing one.
- 24/08/2023 - FIX - Fixed over populating native debug.log files.
- 24/08/2023 - FIX - Stabilise migrations/staging process.

= 0.0.9.24 =
- 21/08/2023 - NEW - Remote Management features added on the Beta programs.
- 21/08/2023 - FIX - Stabilising the migrations process.
- 21/08/2023 - FIX - Auto login issue with magic login button.

= 0.0.9.23 =
- 07/08/2023 - FIX - Fix reset permalink after successful migration.
- 07/08/2023 - FIX - Stabilise migrations/staging process.

= 0.0.9.22 =
- 05/08/2023 - FIX - Stabilise migrations/staging process.

= 0.0.9.21 =
- 02/08/2023 - NEW - Automatically hide Staging Sites from search engines.
- 02/08/2023 - FIX - Restrict staging site creation for a site already marked as staging.
- 02/08/2023 - FIX - Stabilise migrations/staging process.

= 0.0.9.20 =
- 28/07/2023 - FIX - Fix limit checking warning screen issues.
- 28/07/2023 - FIX - Allow migration from local WordPress installations.
- 28/07/2023 - FIX - Fixed some UI issues regarding user experience.

= 0.0.9.19 =
- 24/07/2023 - FIX - Fix Sync API vulnerability issue, disclosure by WordFence.

= 0.0.9.18 =
- 20/07/2023 - NEW - [ BETA ] Implemented two-way sync. Production to staging and vice versa. Includes Compatibility with: Posts, Media, Pages, Custom Post Types
- 20/07/2023 - NEW - Implement new migration algorithm, So website migration is now much more stable and faster.
- 20/07/2023 - NEW - Added remote management features.
- 20/07/2023 - FIX - Fix WaaS and Migrate V2 migration flows.
- 20/07/2023 - FIX - Minor changes on the migration user interface.

= 0.0.9.17 =
- 06/07/2023 - FIX - Fix site usage checking issues.
- 06/07/2023 - FIX - Fix WaaS and Migrate V2 migration flows.

= 0.0.9.16 =
- 04/07/2023 - FIX - Fix minor migration issues. Website migration is now much more stable and faster.

= 0.0.9.15 =
- 15/06/2023 - FIX - Fix migration issues on wizard go live flow.
- 15/06/2023 - FIX - Update user interface to display limit issues before starting migration.
- 15/06/2023 - FIX - Added a "Start Over" button in the limit warning screen.

= 0.0.9.14 =
- 31/05/2023 - FIX - Fix some migration issues on staging to production flow.

= 0.0.9.13 =
- 30/05/2023 - NEW - New user interface of the plugin.
- 30/05/2023 - FIX - Fix many errors in the migration process.
- 30/05/2023 - FIX - Fix migration issues on staging to production flow.

= 0.0.9.12 =
- 24/05/2023 - FIX - Wizard Mode Migration errors fixed.

= 0.0.9.11 =
- 11/05/2023 - FIX - Wizard Mode Migration is now updated.
- 11/05/2023 - FIX - Migration Process errors fixed, migration is now more stable.
- 15/05/2023 - FIX - Added Migration Status Timelapsed.

= 0.0.9.10 =
- 28/04/2023 - UPDATE - Improve calculating migration progress status.

= 0.0.9.9 =
- 18/04/2023 - FIX - Fix many errors while restoring process.

= 0.0.9.8 =
- 13/04/2023 - UPDATE - Heartbeat interval default changed to 15 minutes.
- 13/04/2023 - UPDATE - API key manual input allowed in the settings page.
- 13/04/2023 - FIX - API key is not setting properly after returning from auth screen.

= 0.0.9.7 =
- 11/04/2023 - NEW - Added plugin reset functionality.

= 0.0.9.6 =
- 11/04/2023 - NEW - Parallel approaches for site migration.
- 11/04/2023 - Improved performance and updated user interface.
- 11/04/2023 - FIX - Many bug fixes.

= 0.0.9.3 =
- Still in beta version, but more stable
- 2 way Sync option enabled (from staging to prod)
- Many bug fixes

= 0.0.7 =
- Major bug fixes and quick backup option.

= 0.0.1 =
- Initial release of the plugin.
