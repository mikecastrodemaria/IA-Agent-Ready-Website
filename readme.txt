=== IA Agent Ready Website ===
Contributors: supersoniquestudio
Tags: ai, agents, robots-txt, mcp, agent-skills, link-headers, cloudflare, llm, openai, claude, oauth, webmcp, markdown
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Improve your Agent-Readiness score: Link headers, robots.txt for AI, MCP, Agent Skills, OAuth/OIDC, Markdown negotiation, WebMCP and more.

== Description ==

**Supersonique – Agent Ready** prepares your WordPress site for the era of AI agents. It implements the emerging standards that let agents (ChatGPT, Claude, Perplexity, Copilot…) discover, understand and interact with your content.

The plugin fixes, in a single install, most of the issues identified by [isitagentready.com](https://isitagentready.com), Cloudflare's Agent-Readiness audit tool.

= Static mode (v1.3.0+) =

By default the plugin writes **real JSON files** into `/.well-known/` directly on disk. They are served by Apache without involving PHP — much faster and compatible with any caching strategy (Cloudflare, WP Rocket, etc.). A **Dynamic** mode (rewrite rules + PHP) remains available if disk writes are not possible.

= What the plugin does =

**① Link Response Headers (RFC 8288)**
Adds `Link:` HTTP headers + `<link>` tags in `<head>` so agents can discover your endpoints (api-catalog, agent-skills, service-doc, custom).

**② robots.txt – AI bot rules**
`User-agent` directives for 12 AI crawlers (GPTBot, Claude-Web, anthropic-ai, PerplexityBot, CCBot, Google-Extended, Bytespider, FacebookBot, Applebot-Extended, ImagesiftBot, Omgilibot, Omgili). Allow or block them with a single click.

**③ robots.txt – Content Signals**
`Content-Signal` directive (contentsignals.org / IETF): allow or deny AI training, AI-powered search, agent context use.

**④ MCP Server Card**
`/.well-known/mcp/server-card.json` compliant with the Model Context Protocol specification (SEP-2127).

**⑤ Agent Skills Index**
`/.well-known/agent-skills/index.json` (Agent Skills Discovery RFC v0.2.0). Default skills: search, sitemap. Extensible via `supup_agent_skills`.

**⑥ API Catalog (RFC 9727)**
`/.well-known/api-catalog` formatted as `application/linkset+json`.

**⑦ OAuth Protected Resource (RFC 9728)**
`/.well-known/oauth-protected-resource` with `resource`, `authorization_servers`, `scopes_supported`, `bearer_methods_supported`.

**⑧ OAuth/OIDC Discovery (RFC 8414 / OpenID Connect)**
`/.well-known/openid-configuration` and `/.well-known/oauth-authorization-server` — minimal metadata to pass the check.

**⑨ Web Bot Auth (HTTP Message Signatures)**
`/.well-known/http-message-signatures-directory` formatted as `application/http-message-signatures-directory+json`.

**⑩ Markdown Negotiation**
When an agent sends `Accept: text/markdown`, the plugin converts HTML to Markdown on the fly (headings, links, lists, bold, italic, images, code) and replies in `text/markdown` with `Vary: Accept`. HTML is still served to browsers.

**⑪ WebMCP**
Script injected into `<head>` (with a retry loop waiting for `navigator.modelContext`) that registers search, get_sitemap, get_homepage tools via `navigator.modelContext.provideContext()`. Extensible via `supup_webmcp_tools`.

= Typical score gains =

* Discoverability: 67 → 100
* Content (Markdown): 0 → 100
* Bot Access Control: 50 → 100
* API/Auth/MCP & Skill Discovery: 0 → 100 (6/6)
* **Overall score: 25 → 92–100 (Level 5 – Agent-Native)**

= Cloudflare configuration (if applicable) =

If your site sits behind Cloudflare, two settings are **strongly recommended** so that the isitagentready.com scanner sees all the signals correctly. Without them the score may stay stuck around 70–80 even with the plugin correctly configured.

**1. Transform Rule for the Link header (mandatory)**

Cloudflare caches the homepage HTML and may serve a cached version without the `Link:` headers sent by WordPress. Fix: inject the header at the edge.

* Cloudflare dashboard → your domain → **Rules → Transform Rules → Modify Response Header → Create rule**
* Name: `Agent-Ready Link header`
* Match: `Hostname` equals `your-domain.com`
* Action: **Set static**, Header name: `Link`, Value:
* `</.well-known/api-catalog>; rel="api-catalog", </.well-known/agent-skills/index.json>; rel="agent-skills"`
* Deploy

**2. Markdown for Agents (recommended)**

Cloudflare natively provides Markdown negotiation. If it's available on your plan, it's faster than the PHP converter in this plugin.

* Cloudflare dashboard → **AI → AI Crawl Control → Markdown for Agents** → toggle ON
* If enabled: disable **④b Markdown Negotiation** in the plugin admin to avoid double conversion
* If not available on your plan: keep the plugin feature enabled + add a Cache Rule that bypasses cache for `Accept: text/markdown` requests

**3. Purge Everything after each save**

* Cloudflare → **Caching → Configuration → Purge Everything**
* Otherwise the older cached versions (without Link header, prior 404s on `.well-known/`) keep being served for hours.

**4. Disable APO if possible**

Cloudflare APO aggressively caches HTML and strips some headers (including `Link:`). If enabled:

* Cloudflare dashboard → **Speed → Optimization → Content Optimization → Cloudflare APO** → OFF
* If you want to keep it, the Transform Rule above works around the problem.

**5. Quick check from PowerShell or bash**

`
curl -sI https://your-domain.com/ | grep -i link
curl -sI https://your-domain.com/.well-known/oauth-protected-resource
curl -sI -H "Accept: text/markdown" https://your-domain.com/
`

You should see: `link: </.well-known/api-catalog>...`, a `200` JSON for OAuth, and `content-type: text/markdown` for Markdown negotiation.

= Going further =

The `<link>` tags injected into `<head>` by the plugin work as an automatic DOM fallback for agents that parse HTML, even when the `Link:` HTTP headers are stripped by a proxy.

= Extensibility =

**Custom Agent Skills:**

`
add_filter( 'supup_agent_skills', function( $skills ) {
    $skills[] = [
        'name'        => 'contact',
        'type'        => 'action',
        'description' => 'Send a message to the team.',
        'url'         => home_url('/contact'),
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [ 'message' => [ 'type' => 'string' ] ],
            'required'   => [ 'message' ],
        ],
        'sha256'      => hash('sha256', home_url('/contact')),
    ];
    return $skills;
});
`

**Custom WebMCP tools:**

`
add_filter( 'supup_webmcp_tools', function( $tools ) {
    $tools[] = [
        'name'        => 'list_posts',
        'description' => 'List the latest blog posts.',
        'inputSchema' => [ 'type' => 'object', 'properties' => (object) [] ],
    ];
    return $tools;
});
`

= Standards implemented =

* [RFC 8288](https://www.rfc-editor.org/rfc/rfc8288) – Web Linking
* [RFC 8414](https://www.rfc-editor.org/rfc/rfc8414) – OAuth 2.0 Authorization Server Metadata
* [RFC 9727](https://www.rfc-editor.org/rfc/rfc9727) – API Catalog
* [RFC 9728](https://www.rfc-editor.org/rfc/rfc9728) – OAuth Protected Resource Metadata
* [OpenID Connect Discovery](https://openid.net/specs/openid-connect-discovery-1_0.html)
* [MCP SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127) – MCP Server Card
* [Agent Skills Discovery RFC v0.2.0](https://github.com/cloudflare/agent-skills-discovery-rfc)
* [Content Signals](https://contentsignals.org/) / IETF Draft
* [WebMCP / navigator.modelContext](https://webmachinelearning.github.io/)
* [HTTP Message Signatures (RFC 9421)](https://www.rfc-editor.org/rfc/rfc9421)

== Installation ==

= Automatic install =

1. Go to **Plugins → Add New**
2. Search for "Supersonique Agent Ready"
3. Click **Install Now**, then **Activate**

= Manual install =

1. Download the plugin `.zip`
2. Go to **Plugins → Add New → Upload Plugin**
3. Select the `.zip` and click **Install Now**
4. Click **Activate**

= Configuration =

1. Go to **Settings → 🤖 Agent Ready**
2. Choose the serve mode (**Static** recommended, **Dynamic** as fallback)
3. Toggle features on/off as needed
4. Click **Save settings** — `.well-known/` files are (re)generated automatically
5. Purge your caches (Cloudflare Purge Everything + any WordPress caching plugin)
6. Re-test on [isitagentready.com](https://isitagentready.com)

== Frequently Asked Questions ==

= What's the difference between Static and Dynamic mode? =

**Static** (default) writes real JSON files into `ABSPATH/.well-known/`. Apache serves them directly without booting WordPress — much faster, and Cloudflare caches the 200 (not a 404). **Dynamic** uses WordPress rewrite rules and runs PHP on every request. Use it only if `.well-known/` isn't writable. If file writes fail, Dynamic mode is automatically used as a fallback.

= Does the plugin work without Cloudflare? =

Yes. All features (Link headers, robots.txt, MCP, Agent Skills, API Catalog, OAuth, Web Bot Auth, Markdown Negotiation, WebMCP) work on any WordPress host. Cloudflare is not required for any feature.

= I'm using Cloudflare — what settings do I need to configure on Cloudflare? =

See the "Cloudflare configuration" section in the description. In short: (1) Transform Rule to inject the Link header at the edge, (2) enable Markdown for Agents OR bypass cache for `Accept: text/markdown`, (3) Purge Everything after each change, (4) disable APO or work around it via the Transform Rule. Without these settings the score may stay stuck around 70–80 even with the plugin correctly configured.

= Does the plugin modify my physical robots.txt file? =

No. The plugin uses the WordPress `robots_txt` filter to add directives dynamically. Your physical `robots.txt` file (if any) is not modified.

= How do I verify the endpoints are working? =

After activation, visit directly:
* `https://yoursite.com/robots.txt`
* `https://yoursite.com/.well-known/mcp/server-card.json`
* `https://yoursite.com/.well-known/agent-skills/index.json`
* `https://yoursite.com/.well-known/api-catalog`
* `https://yoursite.com/.well-known/oauth-protected-resource`
* `https://yoursite.com/.well-known/openid-configuration`
* `https://yoursite.com/.well-known/http-message-signatures-directory`

Quick test from a terminal: `curl -sI https://yoursite.com/.well-known/oauth-protected-resource`

= The `.well-known` endpoints return 404 =

In **Static** mode: check that `ABSPATH/.well-known/` is writable (chmod 755). The "Serve mode" block in the admin tells you the write status.
In **Dynamic** mode: go to **Settings → Permalinks** and click **Save Changes** to reload the rewrite rules.

= The isitagentready.com scanner returns 404 on an endpoint but curl returns 200 =

Cloudflare cache problem — it's still serving the old 404. Go to Cloudflare → Caching → Configuration → Purge Everything, wait 60 seconds and re-scan.

= How do I test Markdown negotiation? =

`curl -s -H "Accept: text/markdown" https://yoursite.com/` — the response should be `text/markdown` with a Markdown body.

= How do I block all AI bots instead of allowing them? =

In **Settings → 🤖 Agent Ready**, in the "robots.txt" section, choose **🚫 Block (Disallow: /)**.

= Does this affect classic SEO? =

No. The plugin only adds extra rules for AI crawlers. Googlebot and other classic search engines are not modified.

= What happens if I deactivate the plugin? =

All headers, robots.txt rules, PHP endpoints and generated static files in `.well-known/` are automatically removed. No permanent changes are kept. The options stored in the database are deleted by `uninstall.php` when you permanently delete the plugin.

= Can I add my own skills or WebMCP tools? =

Yes — filters `supup_agent_skills` and `supup_webmcp_tools`. See the "Extensibility" section.

== Screenshots ==

1. Main admin page with the endpoint status table
2. Serve mode selection (Static / Dynamic)
3. Link Headers section — RFC 8288 configuration
4. robots.txt section — AI bot rules and Content Signals
5. MCP Server Card section — MCP server configuration
6. OAuth / OIDC / Web Bot Auth sections
7. Example of generated `/.well-known/agent-skills/index.json`
8. Before/after score on isitagentready.com

== Changelog ==

= 1.3.0 =
* **New: Static mode** — writes real JSON files in `ABSPATH/.well-known/` served directly by Apache (zero PHP, cache-friendly)
* Auto-generated `.htaccess` inside `.well-known/` to enforce correct Content-Type (`application/json`, `application/linkset+json`, `application/http-message-signatures-directory+json`)
* Automatic regeneration of static files on every options save (`update_option_sar_settings`)
* Automatic removal of static files on plugin deactivation
* Mode selector (Static / Dynamic) in admin with writability indicator and list of generated files
* `option_sar_settings` filter that merges defaults — new features activate automatically after an upgrade
* Dynamic mode kept as a fallback when Apache cannot find the static file
* Full internationalization (`load_plugin_textdomain`); French translation shipped under `/languages/`

= 1.2.0 =
* **New: OAuth/OIDC Discovery** — `/.well-known/openid-configuration` + `/.well-known/oauth-authorization-server` (RFC 8414 / OpenID Connect)
* **New: Web Bot Auth** — `/.well-known/http-message-signatures-directory` (RFC 9421)
* **New: Markdown Negotiation** — on-the-fly HTML → Markdown conversion on `Accept: text/markdown` (headings, links, lists, bold, italic, images, code, blockquote). Adds `Vary: Accept`
* OAuth Protected Resource enabled by default, body enriched with `resource`, `authorization_servers`, `scopes_supported`
* WebMCP moved from `wp_footer:99` to `wp_head:1` with a 50× retry loop waiting for `navigator.modelContext`
* Admin toggles for each new feature

= 1.1.0 =
* OAuth Protected Resource (RFC 9728) — `/.well-known/oauth-protected-resource` endpoint
* WebMCP — JS injection of `navigator.modelContext.provideContext()` with search, get_sitemap, get_homepage tools
* `supup_webmcp_tools` filter for custom tools
* `<link>` tags injected in `<head>` as a DOM fallback for the Link headers
* Cloudflare warning in the admin when Link headers are enabled

= 1.0.0 =
* Initial release
* Link response headers RFC 8288 (api-catalog, agent-skills, service-doc, custom)
* robots.txt: User-agent rules for 12 AI crawlers
* robots.txt: Content Signals (ai-train, search, ai-input)
* MCP Server Card endpoint (/.well-known/mcp/server-card.json)
* Agent Skills Index endpoint (/.well-known/agent-skills/index.json)
* API Catalog endpoint (/.well-known/api-catalog)
* Admin page with live status table
* Extensible `supup_agent_skills` filter

== Upgrade Notice ==

= 1.3.0 =
Static mode is the new default: `.well-known/` files are now written to disk for better performance. Deactivate and reactivate the plugin once to generate the files, then purge Cloudflare. Typical isitagentready.com score: 92→100. Source code is now in English and ships with a French translation.

= 1.2.0 =
Adds OAuth/OIDC Discovery, Web Bot Auth and Markdown Negotiation. After updating, go to Settings → 🤖 Agent Ready, check the new toggles and save. Remember to purge Cloudflare.

= 1.1.0 =
Adds OAuth Protected Resource and WebMCP. Go to Settings → Permalinks → Save to activate the new endpoint.

= 1.0.0 =
First stable release. After activation, purge your caches and re-test on isitagentready.com.
