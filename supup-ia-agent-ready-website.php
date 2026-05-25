<?php
/**
 * Plugin Name:       IA Agent Ready Website
 * Plugin URI:        https://supersonique-studio.com
 * Description:       Improve your Agent-Readiness score: Link headers (RFC 8288), robots.txt AI bots & Content Signals, MCP Server Card, Agent Skills Index v0.2.0, OAuth/OIDC, Web Bot Auth, Markdown Negotiation, WebMCP.
 * Version:           1.3.0
 * Author:            Supersonique Studio
 * Author URI:        https://supersonique-studio.com
 * License:           GPL-2.0-or-later
 * Text Domain:       supersonique-agent-ready
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SUPUP_VERSION', '1.3.0' );
define( 'SUPUP_OPTION',  'sar_settings' );

// ============================================================
// 0. INTERNATIONALIZATION
// ============================================================

add_action( 'plugins_loaded', 'supup_load_textdomain' );
function supup_load_textdomain() {
    load_plugin_textdomain( 'supersonique-agent-ready', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// ============================================================
// 1. ACTIVATION / DEACTIVATION
// ============================================================

register_activation_hook( __FILE__, 'supup_activate' );
function supup_activate() {
    if ( ! get_option( SUPUP_OPTION ) ) {
        update_option( SUPUP_OPTION, supup_defaults() );
    }
    supup_add_rewrite_rules();
    flush_rewrite_rules();
    supup_write_static_files();
}

register_deactivation_hook( __FILE__, 'supup_deactivate' );
function supup_deactivate() {
    flush_rewrite_rules();
    supup_delete_static_files();
}

// Regenerate static files on every settings save
add_action( 'update_option_' . SUPUP_OPTION, 'supup_write_static_files', 10, 0 );

// Auto-merge new default keys into an existing stored option (so new toggles
// added in future versions don't appear unchecked after an upgrade).
add_filter( 'option_' . SUPUP_OPTION, 'supup_merge_defaults' );
function supup_merge_defaults( $value ) {
    if ( ! is_array( $value ) ) {
        return supup_defaults();
    }
    return array_merge( supup_defaults(), $value );
}

function supup_defaults() {
    return [
        'serve_mode'               => 'static',
        'link_headers_enabled'     => true,
        'link_header_api_catalog'  => true,
        'link_header_agent_skills' => true,
        'link_header_service_doc'  => false,
        'link_header_custom'       => '',
        'robots_ai_bots_enabled'   => true,
        'robots_ai_bots_allow'     => true,
        'robots_ai_bots_list'      => implode( "\n", [
            'GPTBot', 'Claude-Web', 'anthropic-ai', 'PerplexityBot',
            'CCBot', 'Google-Extended', 'Bytespider', 'FacebookBot',
            'Applebot-Extended', 'ImagesiftBot', 'Omgilibot', 'Omgili',
        ] ),
        'robots_signals_enabled'   => true,
        'signal_ai_train'          => 'no',
        'signal_search'            => 'yes',
        'signal_ai_input'          => 'no',
        'mcp_enabled'              => true,
        'mcp_server_name'          => get_bloginfo( 'name' ),
        'mcp_server_version'       => '1.0.0',
        'mcp_transport_url'        => '',
        'skills_enabled'           => true,
        'oauth_resource_enabled'   => true,
        'oidc_discovery_enabled'   => true,
        'webbot_auth_enabled'      => true,
        'markdown_nego_enabled'    => true,
        'webmcp_enabled'           => true,
    ];
}

// ============================================================
// 2. LINK RESPONSE HEADERS (RFC 8288)
// ============================================================

add_action( 'send_headers', 'supup_send_link_headers', 1 );
function supup_send_link_headers() {
    if ( headers_sent() ) {
        return;
    }
    $opts = get_option( SUPUP_OPTION, supup_defaults() );
    if ( empty( $opts['link_headers_enabled'] ) ) {
        return;
    }
    $home = trailingslashit( home_url() );
    if ( ! empty( $opts['link_header_api_catalog'] ) ) {
        header( 'Link: <' . $home . '.well-known/api-catalog>; rel="api-catalog"', false );
    }
    if ( ! empty( $opts['link_header_agent_skills'] ) ) {
        header( 'Link: <' . $home . '.well-known/agent-skills/index.json>; rel="agent-skills"', false );
    }
    if ( ! empty( $opts['link_header_service_doc'] ) ) {
        header( 'Link: <' . $home . 'docs>; rel="service-doc"', false );
    }
    if ( ! empty( $opts['link_header_custom'] ) ) {
        foreach ( explode( "\n", $opts['link_header_custom'] ) as $line ) {
            $line = trim( $line );
            if ( $line ) {
                header( 'Link: ' . $line, false );
            }
        }
    }
}

// <link> tags in <head> as a DOM fallback for agents that parse HTML
add_action( 'wp_head', 'supup_inject_link_tags', 1 );
function supup_inject_link_tags() {
    $opts = get_option( SUPUP_OPTION, supup_defaults() );
    if ( empty( $opts['link_headers_enabled'] ) ) {
        return;
    }
    $home = trailingslashit( home_url() );
    if ( ! empty( $opts['link_header_api_catalog'] ) ) {
        echo '<link rel="api-catalog" href="' . esc_url( $home . '.well-known/api-catalog' ) . '">' . "\n";
    }
    if ( ! empty( $opts['link_header_agent_skills'] ) ) {
        echo '<link rel="agent-skills" href="' . esc_url( $home . '.well-known/agent-skills/index.json' ) . '">' . "\n";
    }
    if ( ! empty( $opts['link_header_service_doc'] ) ) {
        echo '<link rel="service-doc" href="' . esc_url( $home . 'docs' ) . '">' . "\n";
    }
}

// ============================================================
// 3. ROBOTS.TXT – AI BOTS + CONTENT SIGNALS
// ============================================================

add_filter( 'robots_txt', 'supup_modify_robots_txt', 99, 2 );
function supup_modify_robots_txt( $output, $public ) {
    $opts  = get_option( SUPUP_OPTION, supup_defaults() );
    $extra = '';
    if ( ! empty( $opts['robots_ai_bots_enabled'] ) ) {
        $bots      = array_filter( array_map( 'trim', explode( "\n", $opts['robots_ai_bots_list'] ) ) );
        $directive = empty( $opts['robots_ai_bots_allow'] ) ? 'Disallow: /' : 'Disallow:';
        $extra    .= "\n# === AI Agent Crawlers ===\n";
        foreach ( $bots as $bot ) {
            $extra .= "User-agent: {$bot}\n{$directive}\n\n";
        }
    }
    if ( ! empty( $opts['robots_signals_enabled'] ) ) {
        $train  = sanitize_text_field( $opts['signal_ai_train'] ?? 'no' );
        $srch   = sanitize_text_field( $opts['signal_search']   ?? 'yes' );
        $input  = sanitize_text_field( $opts['signal_ai_input'] ?? 'no' );
        $extra .= "# === Content Signals (contentsignals.org) ===\n";
        $extra .= "Content-Signal: ai-train={$train}, search={$srch}, ai-input={$input}\n";
    }
    return $output . $extra;
}

// ============================================================
// 4. REWRITE RULES FOR /.well-known/
// ============================================================

add_action( 'init', 'supup_add_rewrite_rules' );
function supup_add_rewrite_rules() {
    add_rewrite_rule( '^\.well-known/mcp/server-card\.json$',              'index.php?supup_endpoint=mcp_server_card',          'top' );
    add_rewrite_rule( '^\.well-known/agent-skills/index\.json$',           'index.php?supup_endpoint=agent_skills_index',       'top' );
    add_rewrite_rule( '^\.well-known/api-catalog$',                        'index.php?supup_endpoint=api_catalog',              'top' );
    add_rewrite_rule( '^\.well-known/oauth-protected-resource$',           'index.php?supup_endpoint=oauth_protected_resource', 'top' );
    add_rewrite_rule( '^\.well-known/openid-configuration$',               'index.php?supup_endpoint=oidc_config',              'top' );
    add_rewrite_rule( '^\.well-known/oauth-authorization-server$',         'index.php?supup_endpoint=oidc_config',              'top' );
    add_rewrite_rule( '^\.well-known/http-message-signatures-directory$',  'index.php?supup_endpoint=wba_directory',            'top' );
}

add_filter( 'query_vars', 'supup_query_vars' );
function supup_query_vars( $vars ) {
    $vars[] = 'supup_endpoint';
    return $vars;
}

add_action( 'template_redirect', 'supup_handle_endpoints' );
function supup_handle_endpoints() {
    $endpoint = get_query_var( 'supup_endpoint' );
    if ( ! $endpoint ) {
        return;
    }
    $opts = get_option( SUPUP_OPTION, supup_defaults() );

    switch ( $endpoint ) {

        case 'mcp_server_card':
            if ( empty( $opts['mcp_enabled'] ) ) { status_header( 404 ); exit; }
            $home = trailingslashit( home_url() );
            $card = [
                '$schema'      => 'https://spec.modelcontextprotocol.io/schemas/server-card/v1.json',
                'serverInfo'   => [
                    'name'    => sanitize_text_field( $opts['mcp_server_name'] ?? get_bloginfo( 'name' ) ),
                    'version' => sanitize_text_field( $opts['mcp_server_version'] ?? '1.0.0' ),
                ],
                'transport'    => [
                    'type' => 'http',
                    'url'  => ! empty( $opts['mcp_transport_url'] ) ? esc_url_raw( $opts['mcp_transport_url'] ) : $home . 'mcp',
                ],
                'capabilities' => [ 'tools' => true, 'resources' => false, 'prompts' => false ],
                'contact'      => [ 'url' => $home ],
            ];
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Cache-Control: public, max-age=3600' );
            echo wp_json_encode( $card, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            exit;

        case 'agent_skills_index':
            if ( empty( $opts['skills_enabled'] ) ) { status_header( 404 ); exit; }
            $home   = trailingslashit( home_url() );
            $index  = [
                '$schema' => 'https://agentskills.io/schemas/index/v0.2.0.json',
                'version' => '0.2.0',
                'source'  => $home . '.well-known/agent-skills/index.json',
                'skills'  => array_values( apply_filters( 'supup_agent_skills', supup_default_skills() ) ),
            ];
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Cache-Control: public, max-age=3600' );
            echo wp_json_encode( $index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            exit;

        case 'api_catalog':
            $home    = trailingslashit( home_url() );
            $catalog = [
                'linkset' => [ [
                    'anchor'      => $home,
                    'service-doc' => [ [ 'href' => $home ] ],
                    'describedby' => [ [ 'href' => $home . 'sitemap.xml', 'type' => 'application/xml' ] ],
                ] ],
            ];
            header( 'Content-Type: application/linkset+json; charset=utf-8' );
            header( 'Cache-Control: public, max-age=3600' );
            echo wp_json_encode( $catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            exit;

        case 'oauth_protected_resource':
            if ( empty( $opts['oauth_resource_enabled'] ) ) { status_header( 404 ); exit; }
            $issuer = rtrim( trailingslashit( home_url() ), '/' );
            $data   = [
                'resource'                 => $issuer,
                'authorization_servers'    => [ $issuer ],
                'scopes_supported'         => [ 'read', 'write' ],
                'bearer_methods_supported' => [ 'header' ],
            ];
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Cache-Control: public, max-age=3600' );
            echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            exit;

        case 'oidc_config':
            if ( empty( $opts['oidc_discovery_enabled'] ) ) { status_header( 404 ); exit; }
            $home   = trailingslashit( home_url() );
            $issuer = rtrim( $home, '/' );
            $data   = [
                'issuer'                                => $issuer,
                'authorization_endpoint'                => $home . 'oauth/authorize',
                'token_endpoint'                        => $home . 'oauth/token',
                'jwks_uri'                              => $home . '.well-known/jwks.json',
                'response_types_supported'              => [ 'code' ],
                'grant_types_supported'                 => [ 'authorization_code', 'client_credentials' ],
                'scopes_supported'                      => [ 'openid', 'read' ],
                'token_endpoint_auth_methods_supported' => [ 'client_secret_basic', 'client_secret_post' ],
                'subject_types_supported'               => [ 'public' ],
                'id_token_signing_alg_values_supported' => [ 'RS256' ],
            ];
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Cache-Control: public, max-age=3600' );
            echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            exit;

        case 'wba_directory':
            if ( empty( $opts['webbot_auth_enabled'] ) ) { status_header( 404 ); exit; }
            header( 'Content-Type: application/http-message-signatures-directory+json; charset=utf-8' );
            header( 'Cache-Control: public, max-age=3600' );
            echo wp_json_encode( [ 'keys' => [] ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
            exit;
    }
}

function supup_default_skills() {
    $home      = trailingslashit( home_url() );
    $site_name = get_bloginfo( 'name' );
    /* translators: %s is the site name */
    $search_desc = sprintf( __( 'Search %s articles and pages by keyword.', 'supersonique-agent-ready' ), $site_name );
    /* translators: %s is the site name */
    $sitemap_desc = sprintf( __( 'Full XML sitemap listing all public pages and posts of %s.', 'supersonique-agent-ready' ), $site_name );

    return [
        [
            'name'        => 'search',
            'type'        => 'query',
            'description' => $search_desc,
            'url'         => $home . '?s={query}',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'query' => [
                        'type'        => 'string',
                        'description' => __( 'Search keywords', 'supersonique-agent-ready' ),
                    ],
                ],
                'required'   => [ 'query' ],
            ],
            'sha256'      => hash( 'sha256', $home . '?s=' ),
        ],
        [
            'name'        => 'sitemap',
            'type'        => 'navigation',
            'description' => $sitemap_desc,
            'url'         => $home . 'sitemap.xml',
            'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
            'sha256'      => hash( 'sha256', $home . 'sitemap.xml' ),
        ],
    ];
}

// ============================================================
// 4a. STATIC FILE GENERATION (.well-known/ on disk)
// ============================================================
//
// When serve_mode = 'static', the plugin writes real JSON files inside
// ABSPATH/.well-known/, served directly by Apache (zero PHP).
// Regenerated on each settings save via update_option_<key>.

function supup_static_root() {
    return ABSPATH . '.well-known/';
}

function supup_well_known_files() {
    return [
        'oauth-protected-resource',
        'openid-configuration',
        'oauth-authorization-server',
        'http-message-signatures-directory',
        'api-catalog',
        'mcp/server-card.json',
        'agent-skills/index.json',
    ];
}

function supup_build_payloads() {
    $opts   = get_option( SUPUP_OPTION, supup_defaults() );
    $home   = trailingslashit( home_url() );
    $issuer = rtrim( $home, '/' );

    $payloads = [];

    if ( ! empty( $opts['oauth_resource_enabled'] ) ) {
        $payloads['oauth-protected-resource'] = [
            'resource'                 => $issuer,
            'authorization_servers'    => [ $issuer ],
            'scopes_supported'         => [ 'read', 'write' ],
            'bearer_methods_supported' => [ 'header' ],
        ];
    }

    if ( ! empty( $opts['oidc_discovery_enabled'] ) ) {
        $oidc = [
            'issuer'                                => $issuer,
            'authorization_endpoint'                => $home . 'oauth/authorize',
            'token_endpoint'                        => $home . 'oauth/token',
            'jwks_uri'                              => $home . '.well-known/jwks.json',
            'response_types_supported'              => [ 'code' ],
            'grant_types_supported'                 => [ 'authorization_code', 'client_credentials' ],
            'scopes_supported'                      => [ 'openid', 'read' ],
            'token_endpoint_auth_methods_supported' => [ 'client_secret_basic', 'client_secret_post' ],
            'subject_types_supported'               => [ 'public' ],
            'id_token_signing_alg_values_supported' => [ 'RS256' ],
        ];
        $payloads['openid-configuration']      = $oidc;
        $payloads['oauth-authorization-server'] = $oidc;
    }

    if ( ! empty( $opts['webbot_auth_enabled'] ) ) {
        $payloads['http-message-signatures-directory'] = [ 'keys' => [] ];
    }

    $payloads['api-catalog'] = [
        'linkset' => [ [
            'anchor'      => $home,
            'service-doc' => [ [ 'href' => $home ] ],
            'describedby' => [ [ 'href' => $home . 'sitemap.xml', 'type' => 'application/xml' ] ],
        ] ],
    ];

    if ( ! empty( $opts['mcp_enabled'] ) ) {
        $payloads['mcp/server-card.json'] = [
            '$schema'      => 'https://spec.modelcontextprotocol.io/schemas/server-card/v1.json',
            'serverInfo'   => [
                'name'    => sanitize_text_field( $opts['mcp_server_name'] ?? get_bloginfo( 'name' ) ),
                'version' => sanitize_text_field( $opts['mcp_server_version'] ?? '1.0.0' ),
            ],
            'transport'    => [
                'type' => 'http',
                'url'  => ! empty( $opts['mcp_transport_url'] ) ? esc_url_raw( $opts['mcp_transport_url'] ) : $home . 'mcp',
            ],
            'capabilities' => [ 'tools' => true, 'resources' => false, 'prompts' => false ],
            'contact'      => [ 'url' => $home ],
        ];
    }

    if ( ! empty( $opts['skills_enabled'] ) ) {
        $skills = apply_filters( 'supup_agent_skills', supup_default_skills() );
        $payloads['agent-skills/index.json'] = [
            '$schema' => 'https://agentskills.io/schemas/index/v0.2.0.json',
            'version' => '0.2.0',
            'source'  => $home . '.well-known/agent-skills/index.json',
            'skills'  => array_values( $skills ),
        ];
    }

    return $payloads;
}

function supup_write_static_files() {
    $opts = get_option( SUPUP_OPTION, supup_defaults() );
    if ( ( $opts['serve_mode'] ?? 'static' ) !== 'static' ) {
        // User switched to dynamic mode → clean up existing files
        supup_delete_static_files();
        return false;
    }
    $root = supup_static_root();
    if ( ! file_exists( $root ) && ! wp_mkdir_p( $root ) ) {
        return new WP_Error( 'mkdir', 'Cannot create ' . $root );
    }
    if ( ! is_writable( $root ) ) {
        return new WP_Error( 'perms', $root . ' is not writable' );
    }

    $payloads = supup_build_payloads();
    foreach ( $payloads as $rel => $data ) {
        $target = $root . $rel;
        $dir    = dirname( $target );
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        file_put_contents( $target, $json );
        @chmod( $target, 0644 );
    }

    // .htaccess inside .well-known/ to force the right Content-Type on extension-less files
    $htaccess  = "# Generated by IA Agent Ready Website — do not edit manually\n";
    $htaccess .= "<IfModule mod_mime.c>\n";
    $htaccess .= "  AddType application/json .json\n";
    $htaccess .= "</IfModule>\n";
    $htaccess .= "<IfModule mod_headers.c>\n";
    $htaccess .= "  <FilesMatch \"^(oauth-protected-resource|openid-configuration|oauth-authorization-server)$\">\n";
    $htaccess .= "    Header set Content-Type \"application/json; charset=utf-8\"\n";
    $htaccess .= "    Header set Cache-Control \"public, max-age=3600\"\n";
    $htaccess .= "  </FilesMatch>\n";
    $htaccess .= "  <FilesMatch \"^http-message-signatures-directory$\">\n";
    $htaccess .= "    Header set Content-Type \"application/http-message-signatures-directory+json; charset=utf-8\"\n";
    $htaccess .= "  </FilesMatch>\n";
    $htaccess .= "  <FilesMatch \"^api-catalog$\">\n";
    $htaccess .= "    Header set Content-Type \"application/linkset+json; charset=utf-8\"\n";
    $htaccess .= "  </FilesMatch>\n";
    $htaccess .= "</IfModule>\n";
    $htaccess .= "# Allow access (in case the server blocks extension-less files)\n";
    $htaccess .= "<IfModule mod_authz_core.c>\n";
    $htaccess .= "  Require all granted\n";
    $htaccess .= "</IfModule>\n";
    file_put_contents( $root . '.htaccess', $htaccess );

    return true;
}

function supup_delete_static_files() {
    $root = supup_static_root();
    if ( ! file_exists( $root ) ) {
        return;
    }
    foreach ( supup_well_known_files() as $rel ) {
        $f = $root . $rel;
        if ( file_exists( $f ) ) {
            @unlink( $f );
        }
    }
    @unlink( $root . '.htaccess' );
    @rmdir( $root . 'mcp' );
    @rmdir( $root . 'agent-skills' );
}

// ============================================================
// 4b. MARKDOWN CONTENT NEGOTIATION (Accept: text/markdown)
// ============================================================

add_action( 'template_redirect', 'supup_markdown_negotiation', 0 );
function supup_markdown_negotiation() {
    if ( is_admin() || is_feed() || is_robots() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
        return;
    }
    $opts = get_option( SUPUP_OPTION, supup_defaults() );
    if ( empty( $opts['markdown_nego_enabled'] ) ) {
        return;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if ( stripos( $accept, 'text/markdown' ) === false ) {
        return;
    }

    if ( ! headers_sent() ) {
        header( 'Vary: Accept', false );
    }

    ob_start( function( $html ) {
        if ( stripos( $html, '<html' ) === false ) {
            return $html;
        }
        $title = '';
        if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
            $title = trim( html_entity_decode( strip_tags( $m[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        }
        $body = $html;
        $body = preg_replace( '#<(script|style|noscript|nav|footer|header|aside|form|svg|iframe)\b[^>]*>.*?</\1>#is', '', $body );
        if ( preg_match( '#<(main|article)\b[^>]*>(.*?)</\1>#is', $body, $m ) ) {
            $body = $m[2];
        } elseif ( preg_match( '#<body\b[^>]*>(.*?)</body>#is', $body, $m ) ) {
            $body = $m[1];
        }
        $md = $body;
        $md = preg_replace( '#<h1[^>]*>(.*?)</h1>#is', "\n\n# $1\n\n",    $md );
        $md = preg_replace( '#<h2[^>]*>(.*?)</h2>#is', "\n\n## $1\n\n",   $md );
        $md = preg_replace( '#<h3[^>]*>(.*?)</h3>#is', "\n\n### $1\n\n",  $md );
        $md = preg_replace( '#<h4[^>]*>(.*?)</h4>#is', "\n\n#### $1\n\n", $md );
        $md = preg_replace( '#<h5[^>]*>(.*?)</h5>#is', "\n\n##### $1\n\n",$md );
        $md = preg_replace( '#<h6[^>]*>(.*?)</h6>#is', "\n\n###### $1\n\n",$md );
        $md = preg_replace( '#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '[$2]($1)', $md );
        $md = preg_replace( '#<img [^>]*src="([^"]+)"[^>]*alt="([^"]*)"[^>]*/?>#is', '![$2]($1)', $md );
        $md = preg_replace( '#<img [^>]*src="([^"]+)"[^>]*/?>#is', '![]($1)', $md );
        $md = preg_replace( '#<(strong|b)>(.*?)</\1>#is', '**$2**', $md );
        $md = preg_replace( '#<(em|i)>(.*?)</\1>#is', '*$2*', $md );
        $md = preg_replace( '#<code[^>]*>(.*?)</code>#is', '`$1`', $md );
        $md = preg_replace( '#<li[^>]*>(.*?)</li>#is', "- $1\n", $md );
        $md = preg_replace( '#<br\s*/?>#i', "\n", $md );
        $md = preg_replace( '#</p>#i', "\n\n", $md );
        $md = preg_replace( '#</?(div|section|article|ul|ol|p|span)[^>]*>#i', "\n", $md );
        $md = strip_tags( $md );
        $md = html_entity_decode( $md, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $md = preg_replace( "#[ \t]+#", ' ', $md );
        $md = preg_replace( "#\n{3,}#", "\n\n", $md );
        $md = trim( $md );

        $out = '';
        if ( $title ) {
            $out .= "# {$title}\n\n";
        }
        $out .= $md . "\n";

        if ( ! headers_sent() ) {
            header_remove( 'Content-Type' );
            header( 'Content-Type: text/markdown; charset=utf-8' );
        }
        return $out;
    } );
}

// ============================================================
// 5. WEBMCP — JS injection in <head>
// ============================================================

add_action( 'wp_head', 'supup_inject_webmcp', 1 );
function supup_inject_webmcp() {
    $opts = get_option( SUPUP_OPTION, supup_defaults() );
    if ( empty( $opts['webmcp_enabled'] ) ) {
        return;
    }
    $home      = trailingslashit( home_url() );
    $site_name = get_bloginfo( 'name' );
    $site_desc = get_bloginfo( 'description' );
    /* translators: %s is the site name */
    $desc_search = sprintf( __( 'Search %s content by keyword.', 'supersonique-agent-ready' ), $site_name );
    /* translators: %s is the site name */
    $desc_sitemap = sprintf( __( 'Get the full XML sitemap URL of %s.', 'supersonique-agent-ready' ), $site_name );
    /* translators: 1: site name, 2: site description */
    $desc_home = trim( sprintf( __( 'Get the homepage of %1$s. %2$s', 'supersonique-agent-ready' ), $site_name, $site_desc ) );

    $base_tools = [
        [
            'name'        => 'search',
            'description' => $desc_search,
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [ 'query' => [ 'type' => 'string', 'description' => __( 'Search keywords', 'supersonique-agent-ready' ) ] ],
                'required'   => [ 'query' ],
            ],
        ],
        [
            'name'        => 'get_sitemap',
            'description' => $desc_sitemap,
            'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
        ],
        [
            'name'        => 'get_homepage',
            'description' => $desc_home,
            'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
        ],
    ];
    $tools    = apply_filters( 'supup_webmcp_tools', $base_tools );
    $home_js  = wp_json_encode( $home );
    $tools_js = wp_json_encode( $tools, JSON_UNESCAPED_SLASHES );
    echo '<script id="sar-webmcp">' . "\n";
    echo '(function(){' . "\n";
    echo '  "use strict";' . "\n";
    echo '  if(typeof navigator==="undefined"){return;}' . "\n";
    echo '  var siteUrl=' . $home_js . ';' . "\n";
    echo '  var toolDefs=' . $tools_js . ';' . "\n";
    echo '  var toolsWithHandlers=toolDefs.map(function(tool){' . "\n";
    echo '    tool.execute=function(params){' . "\n";
    echo '      switch(tool.name){' . "\n";
    echo '        case "search":' . "\n";
    echo '          var url=siteUrl+"?s="+encodeURIComponent(params.query||"");' . "\n";
    echo '          return Promise.resolve({url:url,message:"Search results: "+url});' . "\n";
    echo '        case "get_sitemap":' . "\n";
    echo '          return Promise.resolve({url:siteUrl+"sitemap.xml"});' . "\n";
    echo '        case "get_homepage":' . "\n";
    echo '          return Promise.resolve({url:siteUrl});' . "\n";
    echo '        default:' . "\n";
    echo '          return Promise.resolve({url:siteUrl});' . "\n";
    echo '      }' . "\n";
    echo '    };' . "\n";
    echo '    return tool;' . "\n";
    echo '  });' . "\n";
    echo '  var attempts=0;' . "\n";
    echo '  function reg(){' . "\n";
    echo '    if(!navigator.modelContext){if(attempts++<50){return setTimeout(reg,100);}return;}' . "\n";
    echo '    try{navigator.modelContext.provideContext({tools:toolsWithHandlers});}catch(e){}' . "\n";
    echo '  }' . "\n";
    echo '  reg();' . "\n";
    echo '})();' . "\n";
    echo '</script>' . "\n";
}

// ============================================================
// 6. ADMIN PAGE
// ============================================================

add_action( 'admin_menu', 'supup_admin_menu' );
function supup_admin_menu() {
    add_options_page(
        __( 'Agent Ready', 'supersonique-agent-ready' ),
        '🤖 ' . __( 'Agent Ready', 'supersonique-agent-ready' ),
        'manage_options',
        'supersonique-agent-ready',
        'supup_admin_page'
    );
}

add_action( 'admin_init', 'supup_register_settings' );
function supup_register_settings() {
    register_setting( 'supup_settings_group', SUPUP_OPTION, [
        'sanitize_callback' => 'supup_sanitize_options',
    ] );
}

function supup_sanitize_options( $input ) {
    $defaults = supup_defaults();
    $out      = [];
    $booleans = [
        'link_headers_enabled', 'link_header_api_catalog', 'link_header_agent_skills',
        'link_header_service_doc', 'robots_ai_bots_enabled', 'robots_ai_bots_allow',
        'robots_signals_enabled', 'mcp_enabled', 'skills_enabled',
        'oauth_resource_enabled', 'oidc_discovery_enabled',
        'webbot_auth_enabled', 'markdown_nego_enabled', 'webmcp_enabled',
    ];
    foreach ( $booleans as $key ) {
        $out[ $key ] = ! empty( $input[ $key ] );
    }
    $strings = [
        'link_header_custom', 'robots_ai_bots_list',
        'mcp_server_name', 'mcp_server_version', 'mcp_transport_url',
    ];
    foreach ( $strings as $key ) {
        $out[ $key ] = sanitize_textarea_field( $input[ $key ] ?? $defaults[ $key ] );
    }
    $mode              = strtolower( trim( $input['serve_mode'] ?? 'static' ) );
    $out['serve_mode'] = in_array( $mode, [ 'static', 'dynamic' ], true ) ? $mode : 'static';

    $allowed = [ 'yes', 'no' ];
    foreach ( [ 'signal_ai_train', 'signal_search', 'signal_ai_input' ] as $key ) {
        $v           = strtolower( trim( $input[ $key ] ?? 'no' ) );
        $out[ $key ] = in_array( $v, $allowed, true ) ? $v : 'no';
    }
    supup_add_rewrite_rules();
    flush_rewrite_rules();
    return $out;
}

// ── UI helpers ──────────────────────────────────────────────
function supup_checkbox( $opts, $key ) {
    $checked = ! empty( $opts[ $key ] ) ? 'checked' : '';
    echo '<input type="checkbox" id="' . esc_attr( $key ) . '" name="' . SUPUP_OPTION . '[' . esc_attr( $key ) . ']" value="1" ' . $checked . '>';
}

function supup_select( $opts, $key ) {
    $val = $opts[ $key ] ?? 'no';
    echo '<select name="' . SUPUP_OPTION . '[' . esc_attr( $key ) . ']">';
    echo '<option value="yes"' . selected( $val, 'yes', false ) . '>' . esc_html__( 'yes — allowed', 'supersonique-agent-ready' ) . '</option>';
    echo '<option value="no"'  . selected( $val, 'no',  false ) . '>' . esc_html__( 'no — denied',  'supersonique-agent-ready' ) . '</option>';
    echo '</select>';
}

function supup_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $opts      = get_option( SUPUP_OPTION, supup_defaults() );
    $home      = trailingslashit( home_url() );
    $host      = wp_parse_url( $home, PHP_URL_HOST );
    $score_url = 'https://isitagentready.com/' . $host;
    $h2_style  = 'border-bottom:2px solid #e74c3c;padding-bottom:6px;margin-top:32px;';
    ?>
    <div class="wrap">

        <h1>🤖 <?php esc_html_e( 'Agent Ready', 'supersonique-agent-ready' ); ?> <span style="font-size:13px;font-weight:400;color:#888;">v<?php echo SUPUP_VERSION; ?></span></h1>
        <p>
            <?php
            /* translators: %s is the site home URL */
            printf( esc_html__( 'Configure agent-readiness signals for %s', 'supersonique-agent-ready' ), '<strong>' . esc_html( $home ) . '</strong>' );
            ?>
            &mdash; <a href="<?php echo esc_url( $score_url ); ?>" target="_blank"><?php esc_html_e( 'View isitagentready.com score ↗', 'supersonique-agent-ready' ); ?></a>
        </p>

        <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                ✅
                <?php
                echo wp_kses_post(
                    __( 'Settings saved. Remember to <strong>purge your caches</strong> (Cloudflare Purge Everything + any WordPress caching plugin).', 'supersonique-agent-ready' )
                );
                ?>
            </p>
        </div>
        <?php endif; ?>

        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:16px 20px;margin:16px 0;max-width:820px;">
            <h3 style="margin-top:0;">📋 <?php esc_html_e( 'Endpoint status', 'supersonique-agent-ready' ); ?></h3>
            <table class="widefat striped">
                <thead><tr>
                    <th><?php esc_html_e( 'Feature', 'supersonique-agent-ready' ); ?></th>
                    <th><?php esc_html_e( 'Endpoint', 'supersonique-agent-ready' ); ?></th>
                    <th><?php esc_html_e( 'Status',   'supersonique-agent-ready' ); ?></th>
                </tr></thead>
                <tbody>
                <?php
                $checks = [
                    [ __( 'Link headers (RFC 8288)',     'supersonique-agent-ready' ), '',                                                      ! empty( $opts['link_headers_enabled'] ),  __( 'HTTP headers + &lt;link&gt; in &lt;head&gt;',     'supersonique-agent-ready' ) ],
                    [ __( 'AI bots robots.txt',          'supersonique-agent-ready' ), $home . 'robots.txt',                                    ! empty( $opts['robots_ai_bots_enabled'] ), '' ],
                    [ __( 'Content Signals',             'supersonique-agent-ready' ), $home . 'robots.txt',                                    ! empty( $opts['robots_signals_enabled'] ), '' ],
                    [ __( 'MCP Server Card',             'supersonique-agent-ready' ), $home . '.well-known/mcp/server-card.json',              ! empty( $opts['mcp_enabled'] ),           '' ],
                    [ __( 'Agent Skills Index (v0.2.0)', 'supersonique-agent-ready' ), $home . '.well-known/agent-skills/index.json',           ! empty( $opts['skills_enabled'] ),        '' ],
                    [ __( 'API Catalog (RFC 9727)',      'supersonique-agent-ready' ), $home . '.well-known/api-catalog',                       true,                                       '' ],
                    [ __( 'OAuth Protected Resource',    'supersonique-agent-ready' ), $home . '.well-known/oauth-protected-resource',          ! empty( $opts['oauth_resource_enabled'] ), '' ],
                    [ __( 'OAuth/OIDC Discovery',        'supersonique-agent-ready' ), $home . '.well-known/openid-configuration',              ! empty( $opts['oidc_discovery_enabled'] ), '' ],
                    [ __( 'Web Bot Auth directory',      'supersonique-agent-ready' ), $home . '.well-known/http-message-signatures-directory', ! empty( $opts['webbot_auth_enabled'] ),    '' ],
                    [ __( 'Markdown Negotiation',        'supersonique-agent-ready' ), '',                                                      ! empty( $opts['markdown_nego_enabled'] ), __( 'Accept: text/markdown → text/markdown',           'supersonique-agent-ready' ) ],
                    [ __( 'WebMCP (JS in head)',         'supersonique-agent-ready' ), '',                                                      ! empty( $opts['webmcp_enabled'] ),        __( 'Script injected in wp_head + retry',              'supersonique-agent-ready' ) ],
                ];
                foreach ( $checks as [ $label, $url, $active, $note ] ) :
                    $badge = $active
                        ? '<span style="color:green;font-weight:600;">✅ ' . esc_html__( 'Active', 'supersonique-agent-ready' ) . '</span>'
                        : '<span style="color:#aaa;">⏸ ' . esc_html__( 'Disabled', 'supersonique-agent-ready' ) . '</span>';
                    if ( $url ) {
                        $display = '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . ' ↗</a>';
                    } else {
                        $display = '<em>' . wp_kses_post( $note ) . '</em>';
                    }
                    echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td>' . $display . '</td><td>' . $badge . '</td></tr>' . "\n";
                endforeach;
                ?>
                </tbody>
            </table>
            <?php if ( ! empty( $opts['link_headers_enabled'] ) ) : ?>
            <p style="margin-top:12px;padding:10px 14px;background:#fff8e1;border-left:4px solid #ffc107;border-radius:3px;font-size:13px;">
                ⚠️
                <?php
                echo wp_kses_post(
                    __( '<strong>Cloudflare Cache:</strong> if the <code>Link:</code> headers do not appear on the homepage, add a rule in <strong>Cloudflare → Rules → Transform Rules → Modify Response Header</strong>: add <code>Link</code> = <code>&lt;/.well-known/api-catalog&gt;; rel="api-catalog"</code>. The <code>&lt;link&gt;</code> tags injected into the <code>&lt;head&gt;</code> serve as an automatic fallback.', 'supersonique-agent-ready' )
                );
                ?>
            </p>
            <?php endif; ?>
        </div>

        <?php
        $mode_now      = $opts['serve_mode'] ?? 'static';
        $static_root   = supup_static_root();
        $root_exists   = file_exists( $static_root );
        $root_writable = $root_exists ? is_writable( $static_root ) : is_writable( ABSPATH );
        ?>
        <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:16px 20px;margin:16px 0;max-width:820px;">
            <h3 style="margin-top:0;">⚙️ <?php esc_html_e( 'Serve mode', 'supersonique-agent-ready' ); ?></h3>
            <p>
                <strong><?php esc_html_e( 'Current mode:', 'supersonique-agent-ready' ); ?></strong>
                <?php if ( $mode_now === 'static' ) : ?>
                    <span style="color:green;font-weight:600;">📁 <?php esc_html_e( 'Static', 'supersonique-agent-ready' ); ?></span>
                    &mdash;
                    <?php
                    /* translators: %s is the absolute path to .well-known/ */
                    printf( esc_html__( 'real JSON files in %s, served by Apache (fast, cache-friendly).', 'supersonique-agent-ready' ), '<code>' . esc_html( $static_root ) . '</code>' );
                    ?>
                <?php else : ?>
                    <span style="color:#0073aa;font-weight:600;">⚡ <?php esc_html_e( 'Dynamic', 'supersonique-agent-ready' ); ?></span>
                    &mdash; <?php esc_html_e( 'WordPress rewrite rules + PHP on every request.', 'supersonique-agent-ready' ); ?>
                <?php endif; ?>
            </p>
            <p>
                <strong><?php esc_html_e( 'Disk write:', 'supersonique-agent-ready' ); ?></strong>
                <?php if ( $root_writable ) : ?>
                    <span style="color:green;">✅ <?php esc_html_e( 'OK', 'supersonique-agent-ready' ); ?></span>
                    (<code><?php echo esc_html( $root_exists ? $static_root : ABSPATH ); ?></code> <?php esc_html_e( 'is writable', 'supersonique-agent-ready' ); ?>)
                <?php else : ?>
                    <span style="color:#c00;">❌ <?php esc_html_e( 'Not possible', 'supersonique-agent-ready' ); ?></span>
                    &mdash;
                    <?php
                    /* translators: %s is the ABSPATH constant */
                    printf( esc_html__( 'chmod 755 on %s or switch to Dynamic mode.', 'supersonique-agent-ready' ), '<code>' . esc_html( ABSPATH ) . '</code>' );
                    ?>
                <?php endif; ?>
            </p>
            <?php if ( $mode_now === 'static' && $root_exists ) :
                $present = count( array_filter( supup_well_known_files(), function( $f ) use ( $static_root ) { return file_exists( $static_root . $f ); } ) );
            ?>
            <details>
                <summary style="cursor:pointer;font-weight:600;">📂 <?php
                    /* translators: %d is the number of generated files */
                    printf( esc_html__( 'Generated files (%d)', 'supersonique-agent-ready' ), $present );
                ?></summary>
                <ul style="margin:8px 0 0 24px;font-size:13px;">
                <?php foreach ( supup_well_known_files() as $f ) :
                    $full = $static_root . $f;
                    $url  = $home . '.well-known/' . $f;
                    $ok   = file_exists( $full );
                    ?>
                    <li>
                        <?php echo $ok ? '✅' : '⏸'; ?>
                        <code>.well-known/<?php echo esc_html( $f ); ?></code>
                        <?php if ( $ok ) : ?>
                            (<?php echo esc_html( size_format( filesize( $full ) ) ); ?>) &mdash;
                            <a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php esc_html_e( 'View ↗', 'supersonique-agent-ready' ); ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields( 'supup_settings_group' ); ?>

            <h2 style="<?php echo $h2_style; ?>">⓪ <?php esc_html_e( 'Serve mode', 'supersonique-agent-ready' ); ?>
                <small style="color:#888;font-size:13px;"><?php esc_html_e( 'Static = Apache files / Dynamic = WP rewrites', 'supersonique-agent-ready' ); ?></small></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Mode', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="radio" name="<?php echo SUPUP_OPTION; ?>[serve_mode]" value="static" <?php checked( $mode_now, 'static' ); ?>>
                            <strong>📁 <?php esc_html_e( 'Static', 'supersonique-agent-ready' ); ?></strong> &mdash;
                            <?php echo wp_kses_post( __( 'Writes real JSON files in <code>.well-known/</code>. Served directly by Apache, zero PHP, Cloudflare-friendly. <em>Recommended.</em>', 'supersonique-agent-ready' ) ); ?>
                        </label>
                        <label style="display:block;">
                            <input type="radio" name="<?php echo SUPUP_OPTION; ?>[serve_mode]" value="dynamic" <?php checked( $mode_now, 'dynamic' ); ?>>
                            <strong>⚡ <?php esc_html_e( 'Dynamic', 'supersonique-agent-ready' ); ?></strong> &mdash;
                            <?php echo wp_kses_post( __( 'WordPress rewrite rules + PHP. Only use this if static mode cannot write to <code>.well-known/</code> (permissions).', 'supersonique-agent-ready' ) ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Files are regenerated automatically on every save. On plugin deactivation, static files are removed.', 'supersonique-agent-ready' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2 style="<?php echo $h2_style; ?>">① <?php esc_html_e( 'Link Response Headers', 'supersonique-agent-ready' ); ?>
                <small style="color:#888;font-size:13px;"><?php esc_html_e( 'RFC 8288 — Discoverability 67→100', 'supersonique-agent-ready' ); ?></small></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable', 'supersonique-agent-ready' ); ?></th>
                    <td><?php supup_checkbox( $opts, 'link_headers_enabled' ); ?>
                        <label for="link_headers_enabled">
                            <?php echo wp_kses_post( __( 'Send <code>Link:</code> HTTP headers and inject <code>&lt;link&gt;</code> tags into <code>&lt;head&gt;</code>', 'supersonique-agent-ready' ) ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'API Catalog', 'supersonique-agent-ready' ); ?></th>
                    <td><?php supup_checkbox( $opts, 'link_header_api_catalog' ); ?>
                        <label for="link_header_api_catalog"><code>rel="api-catalog"</code> → <code>/.well-known/api-catalog</code></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Agent Skills', 'supersonique-agent-ready' ); ?></th>
                    <td><?php supup_checkbox( $opts, 'link_header_agent_skills' ); ?>
                        <label for="link_header_agent_skills"><code>rel="agent-skills"</code> → <code>/.well-known/agent-skills/index.json</code></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Service Doc', 'supersonique-agent-ready' ); ?></th>
                    <td><?php supup_checkbox( $opts, 'link_header_service_doc' ); ?>
                        <label for="link_header_service_doc"><code>rel="service-doc"</code> → <code>/docs</code></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Custom headers', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <textarea name="<?php echo SUPUP_OPTION; ?>[link_header_custom]" rows="3" cols="60" class="large-text"><?php echo esc_textarea( $opts['link_header_custom'] ); ?></textarea>
                        <p class="description">
                            <?php echo wp_kses_post( __( 'One header per line. Example: <code>&lt;/api&gt;; rel="service-doc"</code>', 'supersonique-agent-ready' ) ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2 style="<?php echo $h2_style; ?>">② robots.txt
                <small style="color:#888;font-size:13px;"><?php esc_html_e( 'Bot Access Control 50→100', 'supersonique-agent-ready' ); ?></small></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'AI bot rules', 'supersonique-agent-ready' ); ?></th>
                    <td><?php supup_checkbox( $opts, 'robots_ai_bots_enabled' ); ?>
                        <label for="robots_ai_bots_enabled">
                            <?php echo wp_kses_post( __( 'Add <code>User-agent</code> directives for AI crawlers', 'supersonique-agent-ready' ) ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Allow / Block', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <label><input type="radio" name="<?php echo SUPUP_OPTION; ?>[robots_ai_bots_allow]" value="1" <?php checked( ! empty( $opts['robots_ai_bots_allow'] ) ); ?>>
                            ✅ <?php echo wp_kses_post( __( 'Allow (<code>Disallow:</code> empty)', 'supersonique-agent-ready' ) ); ?></label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="<?php echo SUPUP_OPTION; ?>[robots_ai_bots_allow]" value="0" <?php checked( empty( $opts['robots_ai_bots_allow'] ) ); ?>>
                            🚫 <?php echo wp_kses_post( __( 'Block (<code>Disallow: /</code>)', 'supersonique-agent-ready' ) ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Bot list', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <textarea name="<?php echo SUPUP_OPTION; ?>[robots_ai_bots_list]" rows="12" cols="35"><?php echo esc_textarea( $opts['robots_ai_bots_list'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One bot per line.', 'supersonique-agent-ready' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Content Signals', 'supersonique-agent-ready' ); ?></th>
                    <td><?php supup_checkbox( $opts, 'robots_signals_enabled' ); ?>
                        <label for="robots_signals_enabled">
                            <?php echo wp_kses_post( __( 'Add the <code>Content-Signal</code> directive to robots.txt', 'supersonique-agent-ready' ) ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><code>ai-train</code></th>
                    <td><?php supup_select( $opts, 'signal_ai_train' ); ?>
                        <span class="description"><?php esc_html_e( 'Training AI models on the content', 'supersonique-agent-ready' ); ?></span></td>
                </tr>
                <tr>
                    <th scope="row"><code>search</code></th>
                    <td><?php supup_select( $opts, 'signal_search' ); ?>
                        <span class="description"><?php esc_html_e( 'Indexing in AI search engines', 'supersonique-agent-ready' ); ?></span></td>
                </tr>
                <tr>
                    <th scope="row"><code>ai-input</code></th>
                    <td><?php supup_select( $opts, 'signal_ai_input' ); ?>
                        <span class="description"><?php esc_html_e( 'Use as agent context (RAG…)', 'supersonique-agent-ready' ); ?></span></td>
                </tr>
            </table>

            <h2 style="<?php echo $h2_style; ?>">③ <?php esc_html_e( 'MCP Server Card', 'supersonique-agent-ready' ); ?>
                <small style="color:#888;font-size:13px;"><?php esc_html_e( 'SEP-2127 — API/MCP Discovery +1', 'supersonique-agent-ready' ); ?></small></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <?php supup_checkbox( $opts, 'mcp_enabled' ); ?>
                        <label for="mcp_enabled">
                            <?php echo wp_kses_post( __( 'Serve <code>/.well-known/mcp/server-card.json</code>', 'supersonique-agent-ready' ) ); ?>
                        </label>
                        &nbsp; <a href="<?php echo esc_url( $home . '.well-known/mcp/server-card.json' ); ?>" target="_blank"><?php esc_html_e( 'View ↗', 'supersonique-agent-ready' ); ?></a>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Server name', 'supersonique-agent-ready' ); ?></th>
                    <td><input type="text" name="<?php echo SUPUP_OPTION; ?>[mcp_server_name]" value="<?php echo esc_attr( $opts['mcp_server_name'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Version', 'supersonique-agent-ready' ); ?></th>
                    <td><input type="text" name="<?php echo SUPUP_OPTION; ?>[mcp_server_version]" value="<?php echo esc_attr( $opts['mcp_server_version'] ); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'MCP Transport URL', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <input type="url" name="<?php echo SUPUP_OPTION; ?>[mcp_transport_url]" value="<?php echo esc_attr( $opts['mcp_transport_url'] ); ?>" class="large-text" placeholder="<?php echo esc_attr( $home . 'mcp' ); ?>">
                        <p class="description">
                            <?php
                            /* translators: %s is the default MCP transport URL */
                            printf( wp_kses_post( __( 'Leave empty to use %s by default.', 'supersonique-agent-ready' ) ), '<code>' . esc_html( $home . 'mcp' ) . '</code>' );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2 style="<?php echo $h2_style; ?>">④ <?php esc_html_e( 'Agent Skills Index', 'supersonique-agent-ready' ); ?>
                <small style="color:#888;font-size:13px;"><?php esc_html_e( 'RFC v0.2.0 — API/MCP Discovery +1', 'supersonique-agent-ready' ); ?></small></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <?php supup_checkbox( $opts, 'skills_enabled' ); ?>
                        <label for="skills_enabled">
                            <?php echo wp_kses_post( __( 'Serve <code>/.well-known/agent-skills/index.json</code>', 'supersonique-agent-ready' ) ); ?>
                        </label>
                        &nbsp; <a href="<?php echo esc_url( $home . '.well-known/agent-skills/index.json' ); ?>" target="_blank"><?php esc_html_e( 'View ↗', 'supersonique-agent-ready' ); ?></a>
                        <p class="description">
                            <?php echo wp_kses_post( __( 'Built-in skills: <strong>search</strong> and <strong>sitemap</strong> with a valid <code>inputSchema</code> (v0.2.0). Extensible via <code>add_filter(\'supup_agent_skills\', ...)</code>', 'supersonique-agent-ready' ) ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2 style="<?php echo $h2_style; ?>">④b <?php esc_html_e( 'Markdown Negotiation', 'supersonique-agent-ready' ); ?>
                <small style="color:#888;font-size:13px;"><?php esc_html_e( 'Content 0→100', 'supersonique-agent-ready' ); ?></small></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <?php supup_checkbox( $opts, 'markdown_nego_enabled' ); ?>
                        <label for="markdown_nego_enabled">
                            <?php echo wp_kses_post( __( 'Respond in <code>text/markdown</code> when the agent sends <code>Accept: text/markdown</code>', 'supersonique-agent-ready' ) ); ?>
                        </label>
                        <p class="description">
                            <?php echo wp_kses_post( __( 'On-the-fly HTML → Markdown conversion (headings, links, lists, bold/italic). Adds <code>Vary: Accept</code>.', 'supersonique-agent-ready' ) ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2 style="<?php echo $h2_style; ?>">④c <?php esc_html_e( 'Web Bot Auth', 'supersonique-agent-ready' ); ?>
                <small style="color:#888;font-size:13px;"><?php esc_html_e( 'Cloudflare / HTTP Message Signatures', 'supersonique-agent-ready' ); ?></small></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <?php supup_checkbox( $opts, 'webbot_auth_enabled' ); ?>
                        <label for="webbot_auth_enabled">
                            <?php echo wp_kses_post( __( 'Serve <code>/.well-known/http-message-signatures-directory</code> (empty directory)', 'supersonique-agent-ready' ) ); ?>
                        </label>
                        &nbsp; <a href="<?php echo esc_url( $home . '.well-known/http-message-signatures-directory' ); ?>" target="_blank"><?php esc_html_e( 'View ↗', 'supersonique-agent-ready' ); ?></a>
                    </td>
                </tr>
            </table>

            <h2 style="<?php echo $h2_style; ?>">⑤ <?php esc_html_e( 'OAuth Protected Resource', 'supersonique-agent-ready' ); ?>
                <small style="color:#888;font-size:13px;"><?php esc_html_e( 'RFC 9728 — optional for brochure sites', 'supersonique-agent-ready' ); ?></small></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <?php supup_checkbox( $opts, 'oauth_resource_enabled' ); ?>
                        <label for="oauth_resource_enabled">
                            <?php echo wp_kses_post( __( 'Serve <code>/.well-known/oauth-protected-resource</code> (minimal endpoint)', 'supersonique-agent-ready' ) ); ?>
                        </label>
                        &nbsp; <a href="<?php echo esc_url( $home . '.well-known/oauth-protected-resource' ); ?>" target="_blank"><?php esc_html_e( 'View ↗', 'supersonique-agent-ready' ); ?></a>
                        <p class="description"><?php esc_html_e( 'Enable only if your site exposes protected APIs. For a brochure site the endpoint is minimal but enough to pass the check.', 'supersonique-agent-ready' ); ?></p>
                    </td>
                </tr>
            </table>

            <h2 style="<?php echo $h2_style; ?>">⑤b <?php esc_html_e( 'OAuth/OIDC Discovery', 'supersonique-agent-ready' ); ?>
                <small style="color:#888;font-size:13px;"><?php esc_html_e( 'RFC 8414 / OpenID Connect', 'supersonique-agent-ready' ); ?></small></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <?php supup_checkbox( $opts, 'oidc_discovery_enabled' ); ?>
                        <label for="oidc_discovery_enabled">
                            <?php echo wp_kses_post( __( 'Serve <code>/.well-known/openid-configuration</code> and <code>/.well-known/oauth-authorization-server</code>', 'supersonique-agent-ready' ) ); ?>
                        </label>
                        &nbsp; <a href="<?php echo esc_url( $home . '.well-known/openid-configuration' ); ?>" target="_blank"><?php esc_html_e( 'View ↗', 'supersonique-agent-ready' ); ?></a>
                        <p class="description">
                            <?php echo wp_kses_post( __( 'Minimal endpoint to pass the check. The <code>oauth/authorize</code> and <code>oauth/token</code> URLs are not actually implemented — declarative only.', 'supersonique-agent-ready' ) ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2 style="<?php echo $h2_style; ?>">⑥ WebMCP
                <small style="color:#888;font-size:13px;"><?php esc_html_e( 'navigator.modelContext — API/MCP Discovery +1', 'supersonique-agent-ready' ); ?></small></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable', 'supersonique-agent-ready' ); ?></th>
                    <td>
                        <?php supup_checkbox( $opts, 'webmcp_enabled' ); ?>
                        <label for="webmcp_enabled">
                            <?php echo wp_kses_post( __( 'Inject the WebMCP script into <code>wp_head</code>', 'supersonique-agent-ready' ) ); ?>
                        </label>
                        <p class="description">
                            <?php echo wp_kses_post( __( 'Registers the <strong>search</strong>, <strong>get_sitemap</strong> and <strong>get_homepage</strong> tools via <code>navigator.modelContext.provideContext()</code>. Extensible via <code>add_filter(\'supup_webmcp_tools\', ...)</code>', 'supersonique-agent-ready' ) ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( '💾 ' . __( 'Save settings', 'supersonique-agent-ready' ) ); ?>
        </form>

        <p style="margin-top:24px;">
            <a href="<?php echo esc_url( $score_url ); ?>" target="_blank" class="button button-primary button-hero">🔍 <?php esc_html_e( 'Re-scan on isitagentready.com', 'supersonique-agent-ready' ); ?></a>
        </p>

    </div>
    <?php
}

// ============================================================
// 7. EXTENSIBILITY — reference examples (commented)
// ============================================================
//
// Add a custom skill from functions.php:
//
// add_filter( 'supup_agent_skills', function( array $skills ): array {
//     $skills[] = [
//         'name'        => 'contact',
//         'type'        => 'action',
//         'description' => 'Send a message to the team.',
//         'url'         => home_url( '/contact' ),
//         'inputSchema' => [
//             'type'       => 'object',
//             'properties' => [
//                 'message' => [ 'type' => 'string' ],
//             ],
//             'required'   => [ 'message' ],
//         ],
//         'sha256'      => hash( 'sha256', home_url( '/contact' ) ),
//     ];
//     return $skills;
// } );
//
// Add a custom WebMCP tool:
//
// add_filter( 'supup_webmcp_tools', function( array $tools ): array {
//     $tools[] = [
//         'name'        => 'list_latest_posts',
//         'description' => 'List the 10 most recent blog posts.',
//         'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
//     ];
//     return $tools;
// } );
