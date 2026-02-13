# Two-Way Sync

Two-way sync enables continuous change tracking and synchronization between connected WordPress sites (production and staging).

## Overview

Activity logging captures all changes on connected sites. Events are recorded and can be synced bidirectionally between production and staging environments.

## Key Files

- `includes/sync/class-instawp-sync-*.php` - Sync handler classes (12+ classes)
- `includes/sync/class-instawp-sync-apis.php` - REST API endpoints
- `includes/sync/class-instawp-sync-ajax.php` - Frontend sync operations

## Database Tables

| Table | Description |
|-------|-------------|
| `wp_instawp_events` | Activity log entries |
| `wp_instawp_sync_history` | Sync transaction history |
| `wp_instawp_event_sites` | Connected staging sites |
| `wp_instawp_event_sync_logs` | Detailed sync operation logs |

## Sync Classes

| Class | Handles |
|-------|---------|
| `InstaWP_Sync_Post` | Posts, pages, featured images |
| `InstaWP_Sync_User` | User accounts and roles |
| `InstaWP_Sync_Term` | Taxonomy terms and categories |
| `InstaWP_Sync_Plugin_Theme` | Plugin/theme installations |
| `InstaWP_Sync_Menu` | Navigation menus |
| `InstaWP_Sync_Customizer` | Customizer changes |
| `InstaWP_Sync_WC` | WooCommerce data (products, orders) |
| `InstaWP_Sync_HappyFiles` | HappyFiles Pro folders and assignments |
| `InstaWP_Sync_Option` | WordPress options |
| `InstaWP_Sync_DB` | Database operations |

## REST API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/instawp-connect/v1/sync` | Receive sync events |
| GET | `/instawp-connect/v1/sync/events` | List events |
| POST | `/instawp-connect/v1/sync/events` | Process events |
| DELETE | `/instawp-connect/v1/sync/events` | Delete events |
| GET | `/instawp-connect/v1/sync/summary` | Event summary |
| POST | `/instawp-connect/v1/sync/download-media` | Download media files |

## Features

- Event filtering by type (posts, users, plugins, etc.)
- Pagination of pending sync events
- Status tracking: pending -> syncing -> completed
- Error logging and retry mechanisms
- Bearer token authentication
- Batch processing (default: 5 items per page)

## HappyFiles Pro

Dedicated sync support for HappyFiles Pro media/post folder management. HappyFiles organizes WordPress media and posts into folders using custom taxonomies (`happyfiles_category` for attachments, `hf_cat_{post_type}` for other post types).

### How to Enable

HappyFiles sync is automatically active when:
- Global 2-way sync is enabled (`instawp_is_event_syncing` = 1)
- HappyFiles Pro plugin is active on the site (`happyfiles_category` taxonomy exists)

No separate toggle is required.

### Supported Sync Events

| Event Slug | Description |
|------------|-------------|
| `happyfiles_folder_created` | Folder creation (name, parent, position, color) |
| `happyfiles_folder_updated` | Folder rename or move to different parent |
| `happyfiles_folder_deleted` | Folder deletion |
| `happyfiles_assignment_changed` | File/post moved to or from a folder |
| `happyfiles_folder_meta_updated` | Folder position or color changed |
| `happyfiles_folder_meta_deleted` | Folder color cleared |

### Key File

- `includes/sync/class-instawp-sync-happyfiles.php` — Main sync module

### Known Limitations

- HappyFiles Pro must be installed on both source and destination sites for folder sync to work
- If a folder's parent does not exist on the destination, it will be created automatically
- HappyFiles taxonomies are excluded from the generic term sync to prevent duplicate events

## Workflow

1. Changes are detected via WordPress hooks
2. Events are recorded in `wp_instawp_events` table
3. Events can be reviewed before syncing
4. Sync processes events and pushes/pulls changes
5. Media files downloaded as needed
