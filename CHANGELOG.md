# Changelog

## 1.0.1

- Added `ABSPATH` guard against direct file access.
- Added an admin notice when WPGraphQL or Yoast SEO Premium is missing/inactive, instead of failing silently.
- `url` argument on `yoastRedirectForUrl` is now `String!` (required) instead of optional — matches how every known consumer already calls it, and turns a missing argument into a clear GraphQL validation error instead of an ambiguous `null` result.
- Sanitize the `url` argument before use.
- Refactored anonymous closures into named, prefixed (`yrgc_`) functions.
- Added standard plugin headers (`Requires at least`, `Requires PHP`, `Requires Plugins`, `Text Domain`).

## 1.0.0

- Initial release: `yoastRedirectForUrl` GraphQL field backed by Yoast SEO Premium's redirect store, supporting plain and regex redirect formats.
