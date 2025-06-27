v1.0.5
======
- Fixed: bunny CDN cache purging mechanism

v1.0.4
======
- Added: Support for migration without creating Connects
- Replace `$managed` and `$plan_id` parameters with a `$config` array for flexibility
- Add new methods `get_connect_plan` and `remove_connect_plan_id` for better plan management
- Improve code readability and maintainability by consolidating plan-related logic