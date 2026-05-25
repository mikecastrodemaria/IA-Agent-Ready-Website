# Supersonique – Agent Ready

> WordPress plugin that prepares your site for the era of AI agents — Link headers (RFC 8288), robots.txt AI bots & Content Signals, MCP Server Card, Agent Skills Index, OAuth/OIDC, Markdown Negotiation, WebMCP and more.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.3.0-orange)](CHANGELOG.md)
[![Agent Ready Score](https://img.shields.io/badge/Agent%20Ready-25%20→%20100-brightgreen)](https://isitagentready.com/supersonique-studio.com)

---

## Why this plugin?

Cloudflare's [isitagentready.com](https://isitagentready.com/supersonique-studio.com) audit rates a website's "Agent-Readiness" out of 100. Without any preparation, a vanilla WordPress site scores around **25/100 (Level 1 – Basic Web Presence)**.

This plugin fixes almost every issue automatically from the WordPress admin, by generating the files and headers expected by modern AI agents.

| Category | Before | After |
|---|---|---|
| **Discoverability** | 67 (2/3) | **100 (3/3)** |
| **Content (Markdown)** | 0 (0/1) | **100 (1/1)** |
| **Bot Access Control** | 50 (1/2) | **100 (2/2)** |
| **API/Auth/MCP & Skills** | 0 (0/6) | **100 (6/6)** |
| **Overall score** | **25** | **92–100** (Level 5 – Agent-Native) |

---

## Serve mode: Static (default) vs Dynamic

Since v1.3.0, the plugin offers two modes:

| | 📁 **Static** (default) | ⚡ **Dynamic** |
|---|---|---|
| How | Writes real JSON files in `ABSPATH/.well-known/` | WordPress rewrite rules + PHP |
| Performance | ~5 ms (Apache static) | ~100 ms (WP boot) |
| Cache-friendly | ✅ Cloudflare caches the 200 | ⚠️ Risk of caching the initial 404 |
| Survives WP outages | ✅ | ❌ |
| Requires | `.well-known/` writable | `.htaccess` routing to `index.php` |

Dynamic mode remains active as a **fallback**: if a static file is missing, Apache routes the request to WordPress which serves the content via PHP.

---

## Features

### ① Link Response Headers (RFC 8288)
`Link:` HTTP headers + `<link>` tags in `<head>` for automatic discovery:

```http
Link: </.well-known/api-catalog>; rel="api-catalog"
Link: </.well-known/agent-skills/index.json>; rel="agent-skills"
```

### ② robots.txt – AI bot rules
Directives for 12 AI crawlers via the WordPress `robots_txt` filter:

```
User-agent: GPTBot
Disallow:

User-agent: Claude-Web
Disallow:

User-agent: anthropic-ai
Disallow:
...
```

### ③ robots.txt – Content Signals
[contentsignals.org](https://contentsignals.org) standard:

```
Content-Signal: ai-train=no, search=yes, ai-input=no
```

### ④ MCP Server Card (SEP-2127)
`/.well-known/mcp/server-card.json`:

```json
{
  "$schema": "https://spec.modelcontextprotocol.io/schemas/server-card/v1.json",
  "serverInfo": { "name": "My Site", "version": "1.0.0" },
  "transport": { "type": "http", "url": "https://mysite.com/mcp" },
  "capabilities": { "tools": true, "resources": false, "prompts": false }
}
```

### ⑤ Agent Skills Index (RFC v0.2.0)
`/.well-known/agent-skills/index.json` with `search` and `sitemap` skills. Extensible via `supup_agent_skills`.

### ⑥ API Catalog (RFC 9727)
`/.well-known/api-catalog` formatted as `application/linkset+json`.

### ⑦ OAuth Protected Resource (RFC 9728)
`/.well-known/oauth-protected-resource`:

```json
{
  "resource": "https://mysite.com",
  "authorization_servers": ["https://mysite.com"],
  "scopes_supported": ["read", "write"],
  "bearer_methods_supported": ["header"]
}
```

### ⑧ OAuth/OIDC Discovery (RFC 8414 / OpenID Connect)
`/.well-known/openid-configuration` + `/.well-known/oauth-authorization-server` with issuer, endpoints, grant_types, scopes, signing algs.

### ⑨ Web Bot Auth (RFC 9421)
`/.well-known/http-message-signatures-directory` formatted as `application/http-message-signatures-directory+json`.

### ⑩ Markdown Negotiation
When an agent sends `Accept: text/markdown`, the HTML is converted to Markdown on the fly:

```bash
curl -s -H "Accept: text/markdown" https://mysite.com/
# → headings, links, lists, bold, italic, code, images
# Content-Type: text/markdown; charset=utf-8
# Vary: Accept
```

### ⑪ WebMCP (`navigator.modelContext`)
Script injected into `<head>` (with retry) that registers tools via `navigator.modelContext.provideContext()`. Default tools: `search`, `get_sitemap`, `get_homepage`. Extensible via `supup_webmcp_tools`.

---

## Installation

```bash
# Option A — direct copy
cp -r supersonique-agent-ready/ /path/to/wp-content/plugins/

# Option B — ZIP
cd .. && zip -r supersonique-agent-ready.zip supersonique-agent-ready/
# then upload via Plugins → Add New → Upload Plugin
```

Then: **Plugins → Activate → Settings → 🤖 Agent Ready**

On activation, the plugin:
1. Creates `ABSPATH/.well-known/` if needed
2. Writes all JSON files (Static mode by default)
3. Generates an `.htaccess` with the right Content-Types
4. Registers rewrite rules as a fallback

---

## 🌩️ Cloudflare configuration

If your site is behind Cloudflare, **a few Cloudflare-side settings are required** for the plugin's signals to reach scanners and agents. Without them, the score may stay stuck around 70–80 even with the plugin correctly configured.

### 1. Transform Rule for the Link header (mandatory)

Cloudflare caches the homepage HTML and serves a cached version **without** the `Link:` headers sent by WordPress. Fix: inject the header at the edge.

**Cloudflare dashboard → your domain → Rules → Transform Rules → Modify Response Header → Create rule**

| Field | Value |
|---|---|
| Rule name | `Agent-Ready Link header` |
| When incoming requests match | `Hostname` equals `your-domain.com` |
| Action | **Set static** |
| Header name | `Link` |
| Value | `</.well-known/api-catalog>; rel="api-catalog", </.well-known/agent-skills/index.json>; rel="agent-skills"` |

This rule applies to every response (cached or not) — no cache purge needed.

### 2. Markdown for Agents (recommended)

Cloudflare provides Markdown negotiation natively, faster than the PHP converter and with better rendering:

**Cloudflare dashboard → AI → AI Crawl Control → Markdown for Agents → ON**

> ⚠️ If you enable Cloudflare's Markdown for Agents: **disable** ④b Markdown Negotiation in the plugin admin to avoid double conversion.

If the feature isn't available on your Cloudflare plan:
* Keep the plugin handler enabled (④b)
* Add a **Cache Rule**: match `(http.host eq "your-domain.com" and http.request.headers["accept"][0] contains "text/markdown")` → action **Bypass cache**

### 3. Purge Everything after each change

**Caching → Configuration → Purge Everything**

Required after:
* First activation of the plugin
* Adding/modifying Transform Rules
* Switching Static ↔ Dynamic mode
* Every settings save if you want agents to see the new `.well-known/` files right away

Without a purge, the 404s Cloudflare cached on the old endpoints keep being served for hours.

### 4. Disable APO

Cloudflare APO aggressively caches HTML and strips some headers (including `Link:`).

**Speed → Optimization → Content Optimization → Cloudflare APO → OFF**

If you want to keep it, the Transform Rule from §1 works around the problem.

### 5. Page Rules or Cache Rules for `.well-known/`

Optional but clean — ensures the static JSON files are cached correctly:

**Caching → Cache Rules → Create rule**

| Field | Value |
|---|---|
| Name | `.well-known JSON cache` |
| Match | `URI Path` starts with `/.well-known/` |
| Action | Cache eligibility → **Eligible for cache** |
| Edge TTL | **2 hours** (override origin) |
| Browser TTL | **1 hour** |

### 6. Verification

```bash
# Link header should appear
curl -sI https://your-domain.com/ | grep -i link

# All .well-known endpoints should return 200
curl -sI https://your-domain.com/.well-known/api-catalog
curl -sI https://your-domain.com/.well-known/oauth-protected-resource
curl -sI https://your-domain.com/.well-known/openid-configuration
curl -sI https://your-domain.com/.well-known/http-message-signatures-directory
curl -sI https://your-domain.com/.well-known/mcp/server-card.json
curl -sI https://your-domain.com/.well-known/agent-skills/index.json

# Markdown negotiation
curl -sI -H "Accept: text/markdown" https://your-domain.com/ | grep -i content-type
```

If everything is ✅ and the scanner still fails, it's a cache issue: Purge Everything and wait 60 seconds before re-testing.

### Cloudflare checklist

- [ ] Transform Rule "Agent-Ready Link header" deployed
- [ ] Markdown for Agents enabled (or Cache Rule bypass for `Accept: text/markdown`)
- [ ] APO disabled (or Transform Rule in place)
- [ ] Purge Everything done after installing the plugin
- [ ] (Optional) Cache Rule for `.well-known/*`
- [ ] curl verification OK on the 6 endpoints

---

## Configuration

All settings live in **WP Admin → Settings → 🤖 Agent Ready**.

After every change:
1. Click **💾 Save settings** — static files are regenerated automatically
2. Purge the Cloudflare cache (**Purge Everything**)
3. Purge the WordPress cache plugin (WP Rocket, W3TC, LiteSpeed…)
4. Re-test on [isitagentready.com](https://isitagentready.com)

### Quick verification

```powershell
curl.exe -sI https://mysite.com/.well-known/oauth-protected-resource
curl.exe -sI https://mysite.com/.well-known/openid-configuration
curl.exe -sI https://mysite.com/.well-known/http-message-signatures-directory
curl.exe -sI -H "Accept: text/markdown" https://mysite.com/
```

All should return `200` with the right `Content-Type`.

---

## Extensibility

### Add custom Agent Skills

```php
add_filter( 'supup_agent_skills', function( array $skills ): array {
    $skills[] = [
        'name'        => 'contact',
        'type'        => 'action',
        'description' => 'Submit a project enquiry.',
        'url'         => home_url( '/contact' ),
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [
                'name'    => [ 'type' => 'string' ],
                'email'   => [ 'type' => 'string', 'format' => 'email' ],
                'message' => [ 'type' => 'string' ],
            ],
            'required'   => [ 'email', 'message' ],
        ],
        'sha256'      => hash( 'sha256', home_url( '/contact' ) ),
    ];
    return $skills;
} );
```

### Add custom WebMCP tools

```php
add_filter( 'supup_webmcp_tools', function( array $tools ): array {
    $tools[] = [
        'name'        => 'list_latest_posts',
        'description' => 'List the 10 most recent blog posts.',
        'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
    ];
    return $tools;
} );
```

### Supported skill types

| Type | Description |
|------|-------------|
| `search` / `query` | Search inside content |
| `navigation` | Navigation to resources |
| `action` | Triggers an action (form, API call) |
| `feed` | Data feed (RSS, JSON feed…) |

---

## Internationalization

Source code and admin UI are in English. A French translation ships in `/languages/supersonique-agent-ready-fr_FR.po` (compile to `.mo` with `msgfmt` or any WordPress translation tool).

To add another language:

```bash
cd languages/
# Update the POT file by re-running WP-CLI:
wp i18n make-pot ../ supersonique-agent-ready.pot
# Create a new locale, e.g. German:
msginit --locale=de_DE --input=supersonique-agent-ready.pot
# Translate de_DE.po, then:
msgfmt supersonique-agent-ready-de_DE.po -o supersonique-agent-ready-de_DE.mo
```

---

## Standards implemented

| Standard | Description | URL |
|---|---|---|
| RFC 8288 | Web Linking – Link headers | [rfc-editor.org](https://www.rfc-editor.org/rfc/rfc8288) |
| RFC 8414 | OAuth 2.0 Authorization Server Metadata | [rfc-editor.org](https://www.rfc-editor.org/rfc/rfc8414) |
| RFC 9421 | HTTP Message Signatures | [rfc-editor.org](https://www.rfc-editor.org/rfc/rfc9421) |
| RFC 9727 | API Catalog | [rfc-editor.org](https://www.rfc-editor.org/rfc/rfc9727) |
| RFC 9728 | OAuth Protected Resource Metadata | [rfc-editor.org](https://www.rfc-editor.org/rfc/rfc9728) |
| OpenID Connect Discovery | OIDC metadata | [openid.net](https://openid.net/specs/openid-connect-discovery-1_0.html) |
| MCP SEP-2127 | MCP Server Card | [GitHub PR](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127) |
| Agent Skills RFC v0.2.0 | Agent Skills Discovery | [GitHub](https://github.com/cloudflare/agent-skills-discovery-rfc) |
| Content Signals | AI content preferences | [contentsignals.org](https://contentsignals.org) |
| WebMCP | `navigator.modelContext` | [webmachinelearning.github.io](https://webmachinelearning.github.io) |

---

## FAQ

**Static vs Dynamic mode?**
Static writes real files served by Apache (fast, cache-friendly). Dynamic uses WordPress + PHP on every request. If disk writes fail, Dynamic kicks in automatically.

**Does the plugin work without Cloudflare?**
Yes. Every feature works on any Apache-backed WordPress host.

**`.well-known` endpoints return 404 in Static mode?**
Check the admin: `ABSPATH/.well-known/` must be writable. Otherwise `chmod 755 public_html/` or switch to Dynamic mode.

**`.well-known` endpoints return 404 in Dynamic mode?**
**Settings → Permalinks → Save Changes** to reload the rewrite rules.

**Does the plugin modify the physical `robots.txt`?**
No. It uses the WordPress `robots_txt` filter — no file is touched.

**Cloudflare doesn't show the Link headers on the homepage?**
Cloudflare caches the HTML without the header. See the [🌩️ Cloudflare configuration](#-cloudflare-configuration) section above — the Transform Rule fixes it in 30 seconds.

**The isitagentready.com scanner still shows 404 on `.well-known/oauth-protected-resource` while `curl` returns 200?**
Cloudflare cache. **Caching → Configuration → Purge Everything**, wait 60 s, re-scan.

---

## Development

```
supersonique-agent-ready/
├── supersonique-agent-ready.php   # Main plugin file
├── readme.txt                     # WordPress.org format
├── README.md                      # This file
├── CHANGELOG.md                   # Version history
├── LICENSE                        # GPL-2.0-or-later
├── uninstall.php                  # Cleanup on uninstall
├── languages/                     # Translations
│   ├── supersonique-agent-ready.pot
│   └── supersonique-agent-ready-fr_FR.po
└── assets/
    ├── banner-1544x500.png
    └── icon-256x256.png
```

### Hooks and filters

| Hook | Type | Description |
|---|---|---|
| `supup_agent_skills` | filter | Modify the Agent Skills served |
| `supup_webmcp_tools` | filter | Modify the WebMCP tools |
| `option_sar_settings` | filter | Auto-merge defaults (internal — don't override) |
| `update_option_sar_settings` | action | Regenerates the static files |

### Internal helpers (Static mode)

| Function | Role |
|---|---|
| `supup_static_root()` | Returns `ABSPATH/.well-known/` |
| `supup_well_known_files()` | List of managed files |
| `supup_build_payloads()` | Builds all the JSON payloads |
| `supup_write_static_files()` | Writes the files + `.htaccess` |
| `supup_delete_static_files()` | Removes files + empty subdirs |

---

## License

[GPL-2.0-or-later](LICENSE) © 2026 [Supersonique Studio](https://supersonique-studio.com)
