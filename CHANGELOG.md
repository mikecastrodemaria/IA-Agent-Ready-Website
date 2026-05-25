# Changelog

All notable changes to this project are documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project follows [Semantic Versioning](https://semver.org/).

---

## [1.3.0] – 2026-05-25

### Added
- **Static mode**: writes real JSON files into `ABSPATH/.well-known/` served directly by Apache (zero PHP, cache-friendly). Default mode.
- **Auto-generated `.htaccess`** inside `.well-known/` with the correct `Content-Type` for each file (`application/json`, `application/linkset+json`, `application/http-message-signatures-directory+json`) and `Cache-Control: public, max-age=3600`.
- **Automatic regeneration** of static files on every options save through the `update_option_sar_settings` hook.
- **Mode selector** in the admin (Static / Dynamic) with writability indicator for `ABSPATH/.well-known/` and a detailed list of generated files (size, direct link).
- **`option_sar_settings` filter** that automatically merges defaults into the stored option — new features activate automatically after an upgrade, no need to manually tick each toggle.
- **Full internationalization**: source code and admin UI in English with `__()`/`esc_html__()` wrappers and `load_plugin_textdomain()`. French translation shipped under `/languages/supersonique-agent-ready-fr_FR.po`.

### Changed
- Plugin activation now immediately writes every `.well-known/` file to disk.
- Plugin deactivation removes every static file and any empty subdirectory.
- Dynamic mode kept as a working fallback (Apache routes to `index.php` if the static file doesn't exist, thanks to the standard WordPress `.htaccess` behavior).

### Technical
- Helpers: `supup_static_root()`, `supup_well_known_files()`, `supup_build_payloads()`, `supup_write_static_files()`, `supup_delete_static_files()`, `supup_default_skills()`.
- Sanitization for the `serve_mode` option (allowed values: `static`, `dynamic`).
- `Domain Path: /languages` header for translations.

---

## [1.2.0] – 2026-05-25

### Added
- **OAuth/OIDC Discovery**: `/.well-known/openid-configuration` and `/.well-known/oauth-authorization-server` endpoints (RFC 8414 / OpenID Connect Discovery) with issuer, authorization_endpoint, token_endpoint, jwks_uri, response_types_supported, grant_types_supported, scopes_supported, token_endpoint_auth_methods_supported, subject_types_supported, id_token_signing_alg_values_supported.
- **Web Bot Auth**: `/.well-known/http-message-signatures-directory` endpoint formatted as `application/http-message-signatures-directory+json` (RFC 9421).
- **Markdown Negotiation**: on-the-fly HTML → Markdown conversion when an agent sends `Accept: text/markdown`. Headers `Content-Type: text/markdown; charset=utf-8` and `Vary: Accept`. Supports headings (h1–h6), links, images, lists, bold, italic, code, blockquote.
- **OAuth Protected Resource** enabled by default, body enriched with `resource`, `authorization_servers`, `scopes_supported`, `bearer_methods_supported`.
- Admin toggles for `markdown_nego_enabled`, `webbot_auth_enabled`, `oidc_discovery_enabled`.
- New endpoints displayed in the "Endpoint status" admin table.

### Changed
- **WebMCP** moved from `wp_footer:99` to `wp_head:1` with a retry loop (50× × 100 ms) that waits for `navigator.modelContext` to be defined before calling `provideContext()`. Improves detection by headless scanners.
- Version bump 1.1.0 → 1.2.0.

---

## [1.1.0] – 2026-05-25

### Added
- **OAuth Protected Resource (RFC 9728)**: `/.well-known/oauth-protected-resource` endpoint with `resource`, `authorization_servers`, `scopes_supported`, `bearer_methods_supported`.
- **WebMCP**: script injected in `<footer>` that registers tools via `navigator.modelContext.provideContext()`. Default tools: `search`, `get_sitemap`, `get_homepage`.
- **`supup_webmcp_tools` filter** to add custom WebMCP tools.
- **`<link>` tags injected in `<head>`** as a DOM fallback for agents that read the DOM (complementing the HTTP Link headers).
- **Cloudflare admin notice**: warning when Link headers are enabled but likely stripped by Cloudflare cache, with an example Transform Rule.

---

## [1.0.0] – 2026-05-25

### Added
- **Link Response Headers (RFC 8288)**: `Link:` HTTP headers on every response (api-catalog, agent-skills, service-doc, custom).
- **AI Bot Rules**: `User-agent` robots.txt directives for 12 AI crawlers (GPTBot, Claude-Web, anthropic-ai, PerplexityBot, CCBot, Google-Extended, Bytespider, FacebookBot, Applebot-Extended, ImagesiftBot, Omgilibot, Omgili).
- **Content Signals**: `Content-Signal` directive in robots.txt (ai-train, search, ai-input) — contentsignals.org / IETF standard.
- **MCP Server Card**: `/.well-known/mcp/server-card.json` endpoint compliant with SEP-2127.
- **Agent Skills Index**: `/.well-known/agent-skills/index.json` endpoint compliant with RFC v0.2.0, with extensible `supup_agent_skills` filter.
- **API Catalog (RFC 9727)**: `/.well-known/api-catalog` endpoint formatted as `application/linkset+json`.
- **Admin page**: complete UI in **Settings → 🤖 Agent Ready** with live status table.
- Safe **default values** enabled on install.
- **Uninstall cleanup** via `uninstall.php`.

### Security
- All values are sanitized via `sanitize_textarea_field`, `sanitize_text_field`, `esc_url_raw`.
- `manage_options` capability check on every admin page.
- WordPress nonce on the settings form via `settings_fields()`.
- `ABSPATH` check at the top of the file.

---

## [Upcoming] – 1.4.0

### Planned
- UI to add custom skills without code (admin form)
- Real MCP HTTP endpoint (`/mcp`) to expose tools server-side, not just WebMCP client-side
- Auto-generated dummy `jwks.json` to round out the OIDC discovery
- More advanced Markdown conversion (tables, code blocks, frontmatter)
- SEO plugin compatibility (Yoast, RankMath) to enrich the agent-skills index with the sitemap
- PHPUnit automated tests
- Support for `x-markdown-tokens` header (compatible with Cloudflare AI Crawl Control)
- Additional language packs (de_DE, es_ES, it_IT)
