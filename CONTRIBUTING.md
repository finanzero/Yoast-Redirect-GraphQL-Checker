# Contributing

Thanks for considering a contribution to Yoast Redirect GraphQL Checker!

## Reporting bugs / requesting features

Open an [issue](../../issues/new/choose) using the appropriate template. Please include your WordPress, WPGraphQL, and Yoast SEO Premium versions when reporting a bug.

## Submitting changes

1. Fork the repo.
2. Create a branch off `main`: `git checkout -b feature/my-feature`.
3. Make your changes, keeping the plugin dependency-free (no Composer/npm build step).
4. Test against a local WordPress install with WPGraphQL and Yoast SEO Premium active.
5. Commit with a clear message: `git commit -am 'Add some feature'`.
6. Push and open a pull request against `main`, describing the change and how you tested it.

## Code style

- Follow the existing formatting in `yoast-graphql-redirect-checker.php` (WordPress PHP coding conventions).
- Keep the GraphQL field's public contract (`origin`, `target`, `type`, `format`) stable; propose schema changes in an issue first.

## Security

Do not open a public issue for security vulnerabilities — see [SECURITY.md](SECURITY.md).
