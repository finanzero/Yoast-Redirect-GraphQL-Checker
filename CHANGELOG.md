# Changelog

## 1.1.0

- `yoastRedirectForUrl` now checks the [Redirection](https://redirection.me/) plugin's store first (`Red_Item::get_for_url()`), falling back to Yoast SEO Premium's store if Redirection isn't active or has no match. Keeps working unchanged through a migration from one source to the other.
- Admin dependency notice now accepts either Redirection or Yoast SEO Premium as the redirect source, instead of requiring Yoast SEO Premium specifically.
- No GraphQL schema change: the `yoastRedirectForUrl` field and `YoastRedirect` type names/shape are unchanged (see [#1](https://github.com/finanzero/Yoast-Redirect-GraphQL-Checker/issues/1) for why the names were kept despite the broadened scope).

## 1.0.1

- Added `ABSPATH` guard against direct file access.
- Added an admin notice when WPGraphQL or Yoast SEO Premium is missing/inactive, instead of failing silently.
- `url` argument on `yoastRedirectForUrl` is now `String!` (required) instead of optional — matches how every known consumer already calls it, and turns a missing argument into a clear GraphQL validation error instead of an ambiguous `null` result.
- Sanitize the `url` argument before use.
- Refactored anonymous closures into named, prefixed (`yrgc_`) functions.
- Added standard plugin headers (`Requires at least`, `Requires PHP`, `Requires Plugins`, `Text Domain`).

## 1.0.0

- Initial release: `yoastRedirectForUrl` GraphQL field backed by Yoast SEO Premium's redirect store, supporting plain and regex redirect formats.
