<?php

/**
 * Plugin Name: Yoast Redirect GraphQL Checker
 * Plugin URI: https://github.com/finanzero/Yoast-Redirect-GraphQL-Checker
 * Description: Exposes Yoast SEO Premium redirects to WPGraphQL, so a headless frontend can resolve a redirect before rendering.
 * Version: 1.0.1
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

define('YRGC_VERSION', '1.0.1');

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

    if (! yrgc_has_yoast_premium_redirects()) {
        $missing[] = 'Yoast SEO Premium';
    }

    if (empty($missing)) {
        return;
    }

    printf(
        '<div class="notice notice-warning"><p>%s</p></div>',
        esc_html(
            sprintf(
                /* translators: %s: comma-separated list of missing plugin names */
                __('Yoast Redirect GraphQL Checker requires %s to be installed and active. The yoastRedirectForUrl GraphQL field will not return any redirects until then.', 'yoast-redirect-graphql-checker'),
                implode(', ', $missing)
            )
        )
    );
}
add_action('admin_notices', 'yrgc_admin_dependency_notice');

/**
 * Register the YoastRedirect object type.
 */
function yrgc_register_redirect_type()
{
    register_graphql_object_type('YoastRedirect', [
        'description' => __('A single Yoast SEO redirect', 'yoast-redirect-graphql-checker'),
        'fields' => [
            'origin' => [
                'type' => 'String',
                'description' => __('The redirect rule as configured in Yoast (plain path or regex pattern)', 'yoast-redirect-graphql-checker'),
            ],
            'target' => [
                'type' => 'String',
                'description' => __('Where the origin redirects to', 'yoast-redirect-graphql-checker'),
            ],
            'type' => [
                'type' => 'String',
                'description' => __('HTTP redirect status code configured in Yoast, e.g. "301"', 'yoast-redirect-graphql-checker'),
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
 */
function yrgc_register_redirect_field()
{
    register_graphql_field('RootQuery', 'yoastRedirectForUrl', [
        'type' => 'YoastRedirect',
        'description' => __('Check if a given URL has a Yoast redirect configured', 'yoast-redirect-graphql-checker'),
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
 * @param mixed $source Unused; required by the WPGraphQL resolver signature.
 * @param array $args   GraphQL field arguments, containing `url`.
 * @return array|null
 */
function yrgc_resolve_redirect_for_url($source, $args)
{
    if (empty($args['url']) || ! is_string($args['url'])) {
        return null;
    }

    if (! yrgc_has_yoast_premium_redirects()) {
        return null;
    }

    $redirects = (new WPSEO_Redirect_Option())->get_from_option();
    $normalized_url = rtrim(sanitize_text_field(wp_unslash($args['url'])), '/');

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
