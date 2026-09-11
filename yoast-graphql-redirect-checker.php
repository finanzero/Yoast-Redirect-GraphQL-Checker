<?php

/**
 * Plugin Name: Yoast Redirect GraphQL Checker
 * Plugin URI: https://github.com/finanzero/Yoast-Redirect-GraphQL-Checker
 * Description: Exposes redirects (Redirection, or Yoast SEO Premium as a fallback) to WPGraphQL, so a headless frontend can resolve a redirect before rendering.
 * Version: 1.1.0
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * Requires Plugins: wp-graphql
 * Author: Finanzero
 * Author URI: https://github.com/finanzero
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: yoast-redirect-graphql-checker
 *
 * @package YoastRedirectGraphQLChecker
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

define('YRGC_VERSION', '1.1.0');

/**
 * Whether the Redirection plugin's redirect store is available.
 *
 * Red_Item only exists when Redirection is installed and active.
 *
 * @return bool
 */
function yrgc_has_redirection_plugin()
{
    return class_exists('Red_Item');
}

/**
 * Whether Yoast SEO Premium's redirect store is available.
 *
 * WPSEO_Redirect_Option only exists in the Premium add-on (redirects are a
 * Premium-only feature), so this doubles as the Premium-active check.
 *
 * @return bool
 */
function yrgc_has_yoast_premium_redirects()
{
    return class_exists('WPSEO_Redirect_Option');
}

/**
 * Warn in wp-admin when a required dependency is missing, instead of
 * failing silently or fataling deep inside a GraphQL resolver.
 */
function yrgc_admin_dependency_notice()
{
    if (! current_user_can('activate_plugins')) {
        return;
    }

    $missing = [];

    if (! class_exists('WPGraphQL')) {
        $missing[] = 'WPGraphQL';
    }

    // At least one redirect source is required; Redirection and Yoast SEO
    // Premium are interchangeable here (Redirection is checked first).
    if (! yrgc_has_redirection_plugin() && ! yrgc_has_yoast_premium_redirects()) {
        $missing[] = 'Redirection or Yoast SEO Premium';
    }

    if (empty($missing)) {
        return;
    }

    printf(
        '<div class="notice notice-warning"><p>%s</p></div>',
        esc_html(
            sprintf(
                /* translators: %s: comma-separated list of missing plugin/requirement names */
                __('Yoast Redirect GraphQL Checker requires %s to be installed and active. The yoastRedirectForUrl GraphQL field will not return any redirects until then.', 'yoast-redirect-graphql-checker'),
                implode(', ', $missing)
            )
        )
    );
}
add_action('admin_notices', 'yrgc_admin_dependency_notice');

/**
 * Register the YoastRedirect object type.
 *
 * Named after this plugin's original (Yoast-only) scope; kept as-is for
 * backwards compatibility even though it's no longer Yoast-exclusive -- see
 * yrgc_resolve_redirect_for_url().
 */
function yrgc_register_redirect_type()
{
    register_graphql_object_type('YoastRedirect', [
        'description' => __('A single redirect, sourced from Redirection or Yoast SEO Premium', 'yoast-redirect-graphql-checker'),
        'fields' => [
            'origin' => [
                'type' => 'String',
                'description' => __('The redirect rule as configured in the source plugin (plain path or regex pattern)', 'yoast-redirect-graphql-checker'),
            ],
            'target' => [
                'type' => 'String',
                'description' => __('Where the origin redirects to', 'yoast-redirect-graphql-checker'),
            ],
            'type' => [
                'type' => 'String',
                'description' => __('HTTP redirect status code, e.g. "301"', 'yoast-redirect-graphql-checker'),
            ],
            'format' => [
                'type' => 'String',
                'description' => __('Either "plain" or "regex"', 'yoast-redirect-graphql-checker'),
            ],
        ],
    ]);
}

/**
 * Register the yoastRedirectForUrl root query field.
 *
 * Field name kept as-is for backwards compatibility with existing consumers
 * even though it now also checks Redirection -- see
 * yrgc_resolve_redirect_for_url().
 */
function yrgc_register_redirect_field()
{
    register_graphql_field('RootQuery', 'yoastRedirectForUrl', [
        'type' => 'YoastRedirect',
        'description' => __('Check if a given URL has a redirect configured (in Redirection, or Yoast SEO Premium as a fallback)', 'yoast-redirect-graphql-checker'),
        'args' => [
            'url' => [
                'type' => ['non_null' => 'String'],
                'description' => __('The origin URL to check for a redirect (relative path)', 'yoast-redirect-graphql-checker'),
            ],
        ],
        'resolve' => 'yrgc_resolve_redirect_for_url',
    ]);
}

/**
 * Resolver for yoastRedirectForUrl.
 *
 * Checks Redirection first (the actively-maintained, free source of truth),
 * falling back to Yoast SEO Premium's store if Redirection isn't active or
 * has no match. This keeps the field working unchanged through a migration
 * from one source to the other, in either direction.
 *
 * @param mixed $source Unused; required by the WPGraphQL resolver signature.
 * @param array $args   GraphQL field arguments, containing `url`.
 * @return array|null
 */
function yrgc_resolve_redirect_for_url($source, $args)
{
    if (empty($args['url']) || ! is_string($args['url'])) {
        return null;
    }

    $normalized_url = rtrim(sanitize_text_field(wp_unslash($args['url'])), '/');

    if (yrgc_has_redirection_plugin()) {
        $redirect = yrgc_match_redirection_redirect($normalized_url);
        if ($redirect) {
            return $redirect;
        }
    }

    if (yrgc_has_yoast_premium_redirects()) {
        $redirect = yrgc_match_yoast_redirect($normalized_url);
        if ($redirect) {
            return $redirect;
        }
    }

    return null;
}

/**
 * Checks the Redirection plugin's store for a redirect matching the URL.
 *
 * Uses Red_Item::get_for_url(), the same lookup Redirection's own frontend
 * module uses, which already filters to enabled items/groups. Deliberately
 * *not* using Red_Item::get_match() for the final check -- that method fires
 * a `redirection_visit` action which increments hit counts / writes a log
 * entry as a side effect, which isn't appropriate for what's meant to be a
 * cheap, read-only existence check called on every page navigation.
 *
 * @param string $normalized_url The URL to check, with any trailing slash removed.
 * @return array|null
 */
function yrgc_match_redirection_redirect($normalized_url)
{
    $candidates = Red_Item::get_for_url($normalized_url);

    foreach ($candidates as $item) {
        $is_regex = $item->is_regex();

        if ($is_regex) {
            // get_for_url() pre-filters exact/plain matches in SQL, but
            // fetches every regex-format redirect as a candidate regardless
            // of whether its pattern actually matches -- that check still
            // has to happen here. The pattern is admin-configured (trusted),
            // the subject is the requested URL (untrusted); a malformed
            // saved pattern is treated as "no match" rather than breaking
            // every other redirect lookup.
            if (1 !== @preg_match('~' . $item->get_url() . '~', $normalized_url)) {
                continue;
            }
        }

        $match_data = $item->match ? $item->match->get_data() : null;
        $target = is_array($match_data) && isset($match_data['url']) ? $match_data['url'] : '';

        return [
            'origin' => $item->get_url(),
            'target' => $target,
            'type' => (string) $item->get_action_code(),
            'format' => $is_regex ? 'regex' : 'plain',
        ];
    }

    return null;
}

/**
 * Checks Yoast SEO Premium's store for a redirect matching the URL.
 *
 * @param string $normalized_url The URL to check, with any trailing slash removed.
 * @return array|null
 */
function yrgc_match_yoast_redirect($normalized_url)
{
    $redirects = (new WPSEO_Redirect_Option())->get_from_option();

    foreach ($redirects as $redirect) {
        if (empty($redirect['origin']) || empty($redirect['url'])) {
            continue;
        }

        $origin = rtrim($redirect['origin'], '/');
        $is_match = false;

        if ('regex' === $redirect['format']) {
            // $origin is admin-configured (trusted), not user input; the
            // subject being matched is the untrusted value. Errors are
            // suppressed because a malformed saved pattern must not break
            // every other redirect lookup -- preg_match() returning false
            // is treated the same as "no match".
            $is_match = 1 === @preg_match('~' . $origin . '~', $normalized_url);
        } else {
            $is_match = $origin === $normalized_url;
        }

        if ($is_match) {
            return [
                'origin' => $redirect['origin'],
                'target' => $redirect['url'],
                'type' => $redirect['type'],
                'format' => $redirect['format'],
            ];
        }
    }

    return null;
}

add_action('graphql_register_types', 'yrgc_register_redirect_type');
add_action('graphql_register_types', 'yrgc_register_redirect_field');
