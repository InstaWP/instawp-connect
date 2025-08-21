# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

InstaWP Connect is a WordPress plugin that enables 1-click staging, migrations, and site management through integration with the InstaWP platform. The plugin provides REST API endpoints for remote site management, two-way sync capabilities, and background processing for migrations.

## Build System & Development Commands

### Asset Compilation
```bash
# Watch Tailwind CSS compilation
npm run tailwind-start

# Watch SCSS compilation  
npm start

# Build production assets
gulp
```

### Code Quality
```bash
# Run PHP CodeSniffer
phpcs

# Build plugin ZIP for distribution
gulp
```

### Composer Dependencies
```bash
# Install dependencies
composer install

# Update autoloader (production build)
composer build-nd
```

## Core Architecture

### Plugin Structure
- **Main File**: `instawp-connect.php` - Plugin bootstrap and constants
- **Core Class**: `includes/class-instawp.php` - Main plugin orchestrator
- **Admin Interface**: `admin/class-instawp-admin.php` - WordPress admin integration
- **Migration UI**: `migrate/class-instawp-migrate.php` - Staging/migration interface

### API System
The plugin uses a layered REST API architecture:

- **Base API**: `includes/apis/class-instawp-rest-api.php` - Core authentication and utilities
- **Migration API**: `includes/apis/class-instawp-rest-api-migration.php` - Pull/push operations
- **Management API**: `includes/apis/class-instawp-rest-api-manage.php` - Remote site management
- **Content API**: `includes/apis/class-instawp-rest-api-content.php` - Content operations
- **Sync APIs**: `includes/sync/class-instawp-sync-apis.php` - Two-way sync endpoints

### Authentication Pattern
All API endpoints use bearer token authentication via the `validate_api_request()` method. The pattern is:
1. Register endpoint with `permission_callback` (most use `__return_true` but validate internally)
2. Each callback method calls `$this->validate_api_request($request)` 
3. API key validation uses SHA256 hashed bearer tokens

### Background Processing
Uses WooCommerce Action Scheduler for background tasks:
- **Heartbeat**: `instawp_send_heartbeat` (daily), `instawp_handle_heartbeat` (user-configurable)
- **File Scanning**: `instawp_prepare_large_files_list` (hourly)
- **Cleanup**: `instawp_clean_migrate_files` (daily)

### Database Schema
Custom tables with `instawp_` prefix:
- `instawp_events` - Event tracking for sync operations
- `instawp_sync_history` - Sync operation history
- `instawp_event_sites` - Site-specific events
- `instawp_activity_logs` - Activity logging
- Migration tables: `iwp_files_sent`, `iwp_db_sent`, `iwp_options`

### Sync System Architecture
Two-way synchronization between staging and production:
- **Event-driven**: Changes tracked in events table with unique hashes
- **Incremental**: Only syncs changes since last sync operation
- **Conflict Resolution**: Last-write-wins with timestamp comparison
- **Content Types**: Posts, users, terms, options, plugins, themes, WooCommerce data

### Helper Classes (Vendor Package)
Located in `vendor/instawp/connect-helpers/src/`:
- **Curl**: API communication with InstaWP platform
- **Helper**: Utility functions and site information
- **Option**: WordPress option management
- **Inventory**: Site inventory collection (plugins, themes, core)
- **Cache**: Multi-plugin cache clearing
- **DatabaseManager**: Database operations and cleanup

### Performance Considerations
The plugin performs several resource-intensive operations:
- **Filesystem Scanning**: Hourly recursive directory scanning for large files
- **Site Usage Calculation**: Full filesystem traversal on API request
- **Heartbeat Data**: Comprehensive site data collection every 4 hours
- **Activity Logging**: Database writes on every admin action (when enabled)

### AJAX Handlers
Located in `includes/class-instawp-ajax.php`:
- Migration progress tracking and initialization
- Directory browsing and file operations
- Database table management
- Site plans and usage limit checking

### Migration Process Flow
1. **Initialization**: Create migration session with unique ID and key
2. **Pre-flight**: Size calculations, compatibility checks, plan validation
3. **File Transfer**: Chunked file uploads with progress tracking
4. **Database Transfer**: Table-by-table export/import with conflict resolution
5. **Post-processing**: URL replacements, cache clearing, cleanup

## Important Security Notes

- Most REST endpoints currently use `permission_callback => __return_true` but validate API keys in callback methods
- File system operations have limited safety checks - be cautious with path manipulation
- Database queries in sync operations may have SQL injection risks - use prepared statements
- Large file scanning can consume significant server resources

## Code Standards

The project follows WordPress Coding Standards with some exclusions defined in `phpcs.xml`. Key points:
- Use `wp_prepare()` for all database queries
- Sanitize all user inputs with appropriate WordPress functions
- Text domain: `instawp-connect`
- Minimum PHP version: 7.4
- Minimum WordPress version: 5.8.0