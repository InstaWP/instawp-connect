=== InstaWP Connect - 1-click WP Staging & Migration (beta) ===
Contributors: instawp
Tags: clone, migrate, staging, backup, restore
Requires at least: 4.5
Tested up to: 6.2
Requires PHP: 5.4
Stable tag: 0.0.9.11
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

1. Create a new staging site.
2. Details of a staging site.
3. Update API key and Settings.

== Changelog ==

= 0.0.1 =
- Initial release of the plugin. 

= 0.0.7 =
- Major bug fixes and quick backup option. 

= 0.0.9.3 =
- Still in beta version, but more stable
- 2 way Sync option enabled (from staging to prod)
- Many bug fixes

= 0.0.9.6 =
- 11/04/2023 - NEW - Parallel approaches for site migration.
- 11/04/2023 - Improved performance and updated user interface.
- 11/04/2023 - FIX - Many bug fixes.

= 0.0.9.7 =
- 11/04/2023 - NEW - Added plugin reset functionality.

= 0.0.9.8 =
- 13/04/2023 - UPDATE - Heartbeat interval default changed to 15 minutes.
- 13/04/2023 - UPDATE - API key manual input allowed in the settings page.
- 13/04/2023 - FIX - API key is not setting properly after returning from auth screen.

= 0.0.9.9 =
- 18/04/2023 - FIX - Fix many errors while restoring process.

= 0.0.9.10 =
- 28/04/2023 - UPDATE - Improve calculating migration progress status.

= 0.0.9.11 =
- 11/05/2023 - FIX - Wizard Mode Migration is now updated.
- 11/05/2023 - FIX - Migration Process errors fixed, migration is now more stable.
- 15/05/2023 - FIX - Added Migration Status Timelapsed.
