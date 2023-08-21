=== InstaWP Connect - 1-click WP Staging & Migration (beta) ===
Contributors: instawp
Tags: clone, migrate, staging, backup, restore
Requires at least: 5.4
Tested up to: 6.3
Requires PHP: 7.0
Stable tag: 0.0.9.24
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

Create a staging WordPress site from production sites in seconds. (beta version).

== Description ==
InstaWP allows you to create WordPress websites for testing, development and staging with seconds. Using this companion plugin you can connect your existing WordPress sites anywhere on the internet to your InstaWP. After your site is connected, you can create 1-click staging websites from within the WP admin panel.

## Features

- All staging websites are created on InstaWP, so zero mess in your hosting account.
- Auto login to your Connected sites from InstaWP (https://app.instawp.io/connects)
- Heartbeat from your Connected sites keeps you updates on site health. 
- Basic site information shown inside InstaWP (coming soon..).
- 2 way push from staging to prod and vice versa. (Iterative sync coming soon..).

## What is InstaWP?

InstaWP is an online WordPress development environment to get you started with a new WP site within seconds. You can get a free account here - [https://app.instawp.io/onboard](https://app.instawp.io/onboard). Key features include:

- Instant site creation. 
- Code Editor, DB Editor and Logs viewer.
- Magic login to WP admin panel. 
- Migrate to any hosting provider. 
- Access SFTP/SSH (pro).
- Map custom domain (pro).
- Save as template, snapshot of site. 
- Shared templates for public sandbox. 
- Invite team members (pro). 
- Git integration and Github actions support. 
- Preset configurations. 
- 3rd party services integrations.


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

== Changelog ==

= 0.0.9.24 =
- 21/08/2023 - FIX - Stabilising the migrations process.
- 21/08/2023 - FIX - Auto login issue with magic login button.
- 21/08/2023 - NEW - Remote Management features added on the Beta programs.

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