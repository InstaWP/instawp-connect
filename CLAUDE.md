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

## Developer-Specific Preferences
Developer preference files are stored in `.claude/{git_username}.claude` where `{git_username}` is from `git config user.name` (e.g., `.claude/randhirinsta.claude`).
These files are gitignored and contain:
- Personal coding preferences
- Project-specific learnings
- Technical discoveries and solutions
- Notes for future reference
