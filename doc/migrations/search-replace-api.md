# Search-Replace API Endpoint

## Overview

Standalone PHP API endpoint that performs serialization-aware search-replace on SQL dump files. Runs without WordPress dependencies and can be used by InstaWP client app or cloud app.

## Location

- **Endpoint**: `iwp-search-replace/index.php`
- **Functions**: `iwp-search-replace/functions.php`

## Authentication

The endpoint requires an API key passed via the `X-IWP-API-KEY` HTTP header. The key is validated against a stored key in `iwp-search-replace/.api-key`.

### Setup

Create the `.api-key` file in the `iwp-search-replace/` directory:

```bash
echo "your-secret-api-key" > iwp-search-replace/.api-key
```

## API

### Request

- **Method**: `POST`
- **Content-Type**: `application/json`
- **Header**: `X-IWP-API-KEY: <your-api-key>`

### Request Body

```json
{
    "input_file": "/absolute/path/to/input.sql",
    "output_file": "/absolute/path/to/output.sql",
    "replacements": {
        "https://old-domain.com": "https://new-domain.com",
        "/old/path": "/new/path"
    }
}
```

| Field          | Type   | Required | Description                                     |
|----------------|--------|----------|-------------------------------------------------|
| `input_file`   | string | Yes      | Absolute path to the input SQL dump file        |
| `output_file`  | string | Yes      | Absolute path for the output SQL file           |
| `replacements` | object | Yes      | Key-value pairs of search => replace strings    |

### Response

**Success (200)**:

```json
{
    "success": true,
    "message": "Search-replace completed successfully",
    "statements_count": 1234,
    "replacements_count": 56
}
```

**Error (4xx/5xx)**:

```json
{
    "success": false,
    "message": "Error description"
}
```

### Error Codes

| Code | Description                          |
|------|--------------------------------------|
| 400  | Invalid input (missing fields, bad JSON, invalid paths) |
| 401  | Missing API key                      |
| 403  | Invalid API key                      |
| 405  | Method not allowed (non-POST)        |
| 500  | Server error (key not configured, processing failure)   |

## Example

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-IWP-API-KEY: your-secret-key" \
  -d '{
    "input_file": "/var/www/html/dump.sql",
    "output_file": "/var/www/html/dump-replaced.sql",
    "replacements": {
      "https://staging.example.com": "https://example.com",
      "/home/staging/public_html": "/home/prod/public_html"
    }
  }' \
  https://your-site.com/wp-content/plugins/instawp-connect/iwp-search-replace/index.php
```

## Security

- API key validation using timing-safe comparison (`hash_equals`)
- Path traversal prevention via `realpath()` validation
- Input file existence check before processing
- Output directory existence and writability checks
- JSON input validation

## Best Practices

### Use Protocol-Prefixed Domain Replacements

**Problem**: Replacing bare domains (without protocol) will also affect email addresses, causing corruption.

**Example of the issue**:
```
Search:  abc.com
Replace: bluehost.com/website_899988sd

Result:
- https://abc.com → https://bluehost.com/website_899988sd ✓
- admin@abc.com  → admin@bluehost.com/website_899988sd  ✗ (broken email)
```

**Recommendation**: Always use protocol-prefixed replacements to avoid corrupting email addresses:

```json
{
    "replacements": {
        "https://abc.com": "https://bluehost.com/website_899988sd",
        "http://abc.com": "https://bluehost.com/website_899988sd",
        "//abc.com": "//bluehost.com/website_899988sd"
    }
}
```

This ensures URLs are replaced correctly while email addresses like `admin@abc.com` remain intact.

## Architecture

The search-replace functions in `iwp-search-replace/functions.php` serve as the Single Source of Truth (SSOT). The existing `includes/functions-pull-push.php` includes this file via `require_once`, ensuring DRY compliance. All functions are wrapped in `function_exists()` guards for safe inclusion from multiple entry points.

### Key Functions

- `iwp_serialized_str_replace()` - Serialization-aware string replacement that recalculates `s:N:"..."` lengths
- `iwp_read_next_sql_statement()` - Quote-aware SQL statement parser that correctly handles semicolons inside strings
- `iwp_search_replace_in_sql_file()` - Orchestrator that processes SQL files statement-by-statement
- `iwp_search_replace_in_sql_file_inplace()` - In-place variant using a temporary file
