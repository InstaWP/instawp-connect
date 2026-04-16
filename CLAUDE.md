# InstaWP Connect - Claude AI Context

## Session Start Instructions (MANDATORY - EXECUTE FIRST)

### BEFORE YOUR FIRST RESPONSE - Execute These Steps Silently:

**Step 1 - Load developer context (EXECUTE IMMEDIATELY):**
1. Run `git config user.name` to get the developer's username
   - If the command fails or returns empty, ask the user: "What is your developer username?"
2. Read `.claude/{git_username}.claude` file (e.g., `.claude/randhirinsta.claude`)
3. If the file doesn't exist, create it with the template below

**Step 2 - Display confirmation:**
```
Context loaded:
- CLAUDE.md (project guidelines)
- .claude/{git_username}.claude (developer preferences)
```

**Step 3 - ONLY NOW respond to user's actual question/request**

### Why This Order Matters:
- The developer file contains critical project context and past learnings
- Loading it FIRST ensures your response benefits from this knowledge
- Skipping this step means you lose valuable context

### Throughout the Session:
Keep the developer file updated with new learnings discovered during the session.

### Template for New Developer File:
```
# Developer: {git_username}

## Coding Preferences
## Project Context
## Technical Learnings
## Notes
```

## Project Overview
WordPress plugin for site migration, backup, and staging functionality.

## Important Files
- `includes/class-instawp-iwpdb.php` - Database abstraction for migration tracking
- `iwp-serve/index.php` - Standalone source migration endpoint (runs WITHOUT WordPress)
- `iwp-dest/index.php` - Standalone destination migration endpoint (runs WITHOUT WordPress)
- `includes/functions-pull-push.php` - Migration helper functions

## Architecture Notes
- Migration scripts run as standalone PHP (no WordPress context)
- Uses mysqli directly when WordPress is not available
- Encrypted options file stores DB credentials for bootstrap

### Core Architectural Principles

**Data Layer Abstraction:**
- **Handle data at its storage layer (options), not at its semantic layer (widgets, theme mods, etc.)**
  - Work directly with the underlying storage mechanism (e.g., `wp_options` table)
  - Avoid working with high-level abstractions like widgets, theme mods, or customizer settings
  - This ensures complete data capture and avoids WordPress API limitations

**Design Principles:**
- **DRY (Don't Repeat Yourself)**: Eliminate code duplication through proper abstraction
- **SSOT (Single Source of Truth)**: One authoritative source for each piece of data
- **SoC (Separation of Concerns)**: Keep different responsibilities in separate, focused modules
- **Proper Abstraction**: Create core-level implementations that enforce these principles

**Implementation Guidelines:**
- Build core utility functions that can be reused across features
- Centralize data access patterns in dedicated classes
- Use dependency injection and composition over inheritance
- Keep business logic separate from data access logic

## Security Checklist (CRITICAL)

### AJAX Handler Authorization
**Every `wp_ajax_` handler MUST have both:**
1. `check_ajax_referer( 'instawp-connect', 'security' );` — verifies nonce (CSRF protection)
2. `current_user_can( 'manage_options' )` or `current_user_can( InstaWP_Setting::get_allowed_role() )` — verifies authorization

A nonce alone is NOT sufficient. Nonces only prove the request came from a logged-in user's browser session — any Subscriber/Author/Editor can obtain the nonce from page source and call the action. Without a capability check, this is a **Broken Access Control vulnerability (CWE-862)**.

**Pattern to follow:**
```php
public function my_ajax_handler() {
    check_ajax_referer( 'instawp-connect', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error();
    }

    // ... handler logic
}
```

### REST API Routes
REST routes use `'permission_callback' => '__return_true'` but validate bearer tokens (API key hash) inside the handler via `validate_api_request()`. This is functionally secure but should ideally be moved to proper `permission_callback` functions for WordPress best practices.

### Past Incidents
- **v0.1.2.5 (Mar 2026)**: `disconnect_api()`, `refresh_staging_sites()`, `change_plan()` in `class-instawp-ajax.php` were missing `current_user_can('manage_options')`. `handle_select2()` in `class-instawp-sync-ajax.php` was missing both nonce check and capability check — any authenticated user could enumerate WordPress users. All fixed.

## Code Standards

**ALWAYS follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/):**
- PHP: Tabs for indentation, Yoda conditions, proper spacing
- Naming: `snake_case` for functions/variables, prefix with `instawp_` or `iwp_`
- Documentation: PHPDoc blocks for all functions and classes
- Formatting: Space after control structures, braces on same line

All code must be:

### Optimized
- Minimize database queries and file I/O operations
- Use efficient algorithms and data structures
- Avoid unnecessary loops and redundant operations
- Cache results when appropriate

### Secure
- Sanitize all user inputs
- Use prepared statements for database queries
- Escape output properly
- Validate and verify all data before use
- Never trust external input (POST, GET, files)
- Follow OWASP security guidelines

### Efficient
- Keep memory usage low (important for large migrations)
- Use streaming for large files instead of loading into memory
- Implement proper error handling with meaningful messages
- Write clean, readable, and maintainable code

### Backward Compatible
- All new code and changes must be backward compatible
- Do not break existing functionality or APIs
- Maintain support for existing database schemas and option formats
- Use feature detection instead of version checks where possible
- Deprecate old functions gracefully before removal

### Variable Declaration Before Use
- **NEVER use a variable before declaring it** — even if PHP won't fatal on it, it produces bugs and undefined behavior
- Before writing any code block that references a variable, verify where that variable is declared in the current scope
- When moving code blocks (e.g., reordering logic), trace ALL variable dependencies and move declarations above their first use
- When adding new code that references an existing variable, confirm the declaration line number is ABOVE the new code — do not assume

### Self-Review Before Presenting Edits
- After writing an edit, re-read the FULL diff and trace every variable reference to its declaration
- Check that no variable is used before its assignment in the new ordering
- Check that moved/deleted code doesn't leave dangling references
- If an edit reorders logic, draw the dependency chain: which variables feed into which lines, and does the new order satisfy all dependencies?
- Do NOT rely on the user to catch scope errors — catch them yourself before presenting

### Syntax Error Free
- All code must be free of syntax errors before committing
- Validate PHP syntax using `php -l filename.php` before finalizing changes
- Ensure proper bracket matching, semicolons, and quote pairing
- Test code execution in the target PHP version environment

## Documentation Requirements

**MANDATORY: Update documentation for every change.**

Documentation is stored in the `doc/` folder. When implementing any feature, fix, or workflow:

1. **New Feature**: Create or update relevant doc file explaining the feature, its purpose, and usage
2. **Bug Fix**: Document the issue and solution if it affects existing behavior or workflows
3. **Workflow Change**: Update or create workflow documentation explaining the process

### Documentation Structure:
```
doc/
├── migrations/
│   ├── pull.md              # Pull migration workflow
│   ├── push.md              # Push migration workflow
│   └── end-to-end.md        # End-to-end migration
├── two-way-sync.md          # Two-way sync feature
├── connect-manage-site.md   # Site connection and management
└── tools.md                 # Plugin tools and utilities
```

### Guidelines:
- Use clear, concise language
- Include code examples where applicable
- Keep documentation in sync with code changes

## Plugin Code Patterns

### Logging
- **NEVER use `error_log()`** — use `Helper::add_error_log()` from connect-helpers instead
- **Log failures only** — do not log success paths, debug info, or entry/exit of methods
- Keep logging minimal: only when an operation fails and the failure is actionable

### Debouncing / Rate Limiting
- **Do NOT use per-item transients** with `md5()` hashes for debouncing
- Use a **single `wp_options` key** with structure: `array( $item_id => array( 'data' => ..., 'expire' => time() + N ) )`
- Clean expired entries before adding new ones
- Pass `false` as third arg to `update_option()` to disable autoload for transient-like data

### Conditional Logic
- Use `if/elseif` chains when conditions are mutually exclusive — not separate `if` blocks
- Merge branches that have identical bodies (e.g., "new publish" and "update publish" both just need `'publish' === $new_status`)

### WordPress Hooks
- Do NOT skip `DOING_AUTOSAVE` unless there is a specific reason — autosaves may be legitimate content updates
- Always check `wp_is_post_revision()` to skip revision saves

### Data Structures
- When collecting items keyed by ID, use the ID as the array key (`$array[ $id ] = $value`) instead of `$array[] = $value` + `array_unique()`
- Use `array_values()` when passing keyed arrays to APIs that expect sequential arrays

## Developer-Specific Preferences
Developer preference files are stored in `.claude/{git_username}.claude` where `{git_username}` is from `git config user.name` (e.g., `.claude/randhirinsta.claude`).
These files are gitignored and contain:
- Personal coding preferences
- Project-specific learnings
- Technical discoveries and solutions
- Notes for future reference
