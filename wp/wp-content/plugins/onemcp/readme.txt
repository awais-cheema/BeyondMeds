=== OneMCP ===
Contributors: softaculous
Tags: mcp, model-context-protocol, ai, claude, cursor
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to any AI assistant (Claude, Cursor, Grok, Windsurf, VS Code, Antigravity) via Model Context Protocol (MCP).

== Description ==

Turn your WordPress site into an intelligent, AI-operable workspace with **OneMCP**. 

Connect any AI assistant, coding agent, or autonomous LLM via the **Model Context Protocol (MCP)** to WordPress. **Claude**, **Cursor**, **Windsurf**, **VS Code**, **Grok CLI**, **OpenCode**, and **Antigravity** connect directly to the site.

OneMCP implements native **Streamable HTTP JSON-RPC** over standard WordPress REST API routes (`onemcp/v1/mcp`) authenticated securely via native **WordPress Application Passwords**.

---

### 🌟 Key Highlights & Features

* **Built-in Native MCP Server**: Runs directly inside your WordPress installation. Zero external SaaS dependencies, zero API gateway subscriptions, zero data leaks.
* **Streamable HTTP JSON-RPC Transport**: Fast, modern, and reliable HTTP transport supporting protocol versions `2025-11-25`, `2025-06-18`, `2025-03-26`, and `2024-11-05`.
* **Universal AI Client Support**: Ready-to-use configuration generator and 1-click credential setup for:
  * **Claude Desktop** (`claude_desktop_config.json`)
  * **Cursor** (`.cursor/mcp.json`)
  * **Codex** (`~/.codex/config.toml`)
  * **VS Code & GitHub Copilot** (`.vscode/mcp.json`)
  * **Windsurf** (`~/.codeium/windsurf/mcp_config.json`)
  * **Grok** (`grok mcp add`)
  * **OpenCode** (`opencode.json`)
  * **Antigravity & Gemini CLI**
  * Any standard HTTP-compatible MCP client.
* **WordPress Core Tools**: Posts, pages, media, comments, terms, menus, plugins, themes, and users.
* **OneMCP Pro**: Page builders, SEO, SMTP, cache, WooCommerce, Easy Digital Downloads, and backups load from the Pro add-on.
* **WordPress Abilities API First-Class Support**: Fully integrated with the official WordPress Abilities API standard (`wp_abilities_api_init`, `wp_abilities_api_categories_init`).
* **Granular Security & Permission Controls**:
  * Safe defaults: WordPress core abilities are enabled by default. On SitePad, SitePad Core and Pagelayer abilities are enabled by default. Other write abilities stay off until an administrator turns them on.
  * Switch individual tools on or off with a single click.
  * Bulk enable or disable by category or action type.
  * User-level capability validation (`edit_posts`, `manage_options`, `manage_woocommerce`, etc.).
* **Audit & Activity Log**:
  * Complete on-site audit trail recording tool execution status, timestamp, and invoking user.
  * Automatic log rotation to keep database footprint small and fast.
* **One-Click Application Password Generator**: Generate authenticated connection strings and JSON snippets in seconds directly in the admin dashboard.

---

### 🧩 Integrations & Abilities Catalog

The free plugin exposes WordPress core tools. Page builders, SEO, SMTP, cache, stores, and backups are registered by OneMCP Pro when that plugin is active.

OneMCP automatically detects active plugins on your website and exposes tailored tools:

#### 1. Core WordPress (`onemcp-wordpress`)
Manage all standard WordPress operations:
* `get-site-info`: Inspect site address, versions, active theme, detected integrations, and endpoint status.
* `list-content`: Filter and paginate posts, pages, and custom post types by status, title search, or author.
* `get-content`: Retrieve full post details, excerpt, and rendered/raw content.
* `create-content` & `update-content`: Draft, publish, or modify pages and posts.
* `trash-content`: Safely trash or permanently remove content.
* `list-media`, `get-media`, `update-media`, & `add-media-from-url`: Manage media library and upload assets directly from URLs.
* `list-taxonomies`, `list-terms`, `create-term`, `update-term`, & `set-content-terms`: Organize categories and tags.
* `list-comments` & `moderate-comment`: Monitor and moderate comments (approve, spam, trash).
* `list-menus`, `list-menu-items`, `create-menu`, & `add-menu-item`: Manage navigation menus and menu links.
* `get-content-meta` & `update-content-meta`: Read and update custom post meta fields.
* `list-plugins`, `list-themes`, & `list-users`: Audit installed software and user accounts.

#### 2. Page Builders (`onemcp-builders` & `onemcp-pagelayer-*`)
Work directly with modern page builders without breaking layout JSON or shortcode structures:
* **Universal Builders Engine**:
  * Supports: **Pagelayer**, **Elementor**, **SeedProd**, **Divi**, **Bricks**, **Beaver Builder**, **Breakdance**.
  * `list-[builder]-documents`: Search and list builder pages, landing pages, and templates.
  * `get-[builder]-structure`: Inspect the hierarchical tree outline of builder elements.
  * `update-[builder]-element`: Safely merge attributes, text content, styling, or settings into specific builder nodes.
* **Pagelayer Deep Integration**:
  * Exposes dedicated categories for Pagelayer Pages, Posts, Templates (Header, Footer, Archive, Single), Menus, Widgets, Theme Settings, Global Styles (Colors, Fonts, Presets), and Media.

#### 3. SEO Suites (`onemcp-seo`)
Optimize your site content dynamically with AI:
* Supports: **SiteSEO**, **Yoast SEO**, **Rank Math**, **All in One SEO (AIOSEO)**, and **SEOPress**.
* `get-[seo]`: Read SEO titles, meta descriptions, focus keywords/keyphrases, OpenGraph/Twitter social cards, canonical URLs, robots meta tags, and cornerstone status.
* `update-[seo]`: Update on-page SEO settings, social preview meta tags, and robots indexing directives.

#### 4. SMTP & Email Deliverability (`onemcp-smtp`)
Audit and manage transactional mail configuration:
* Supports: **GoSMTP**, **WP Mail SMTP**, **FluentSMTP**, **Easy WP SMTP**, **Post SMTP**, and **SMTP Mailer**.
* `get-[smtp]`: Inspect sender email, sender name, mailer type, SMTP host, port, and SSL/TLS encryption (passwords/API keys are masked for security).
* `update-[smtp]`: Adjust server settings, ports, and sender details.
* `send-smtp-test`: Send an immediate plain-text test email to verify SMTP deliverability.

#### 5. Cache (`onemcp-cache`)
SpeedyCache is first. Also supports LiteSpeed Cache, WP Rocket, WP Super Cache, W3 Total Cache, WP Fastest Cache, WP-Optimize, and SiteGround Optimizer.
* `get-cache`: Read every active cache plugin. SpeedyCache is listed first when it is active.
* `get-[cache]`: Read cache status, mobile cache, preload, and minify flags. CDN tokens are left out.
* `purge-cache`: Purge every active cache plugin. Send `post_id` or `url` to purge one page.
* `purge-[cache]`: Purge one plugin. SpeedyCache can also clear minified files, fonts, gravatars, and rebuild preload.

#### 6. WooCommerce & Stores (`onemcp-woocommerce`)
Let AI act as your autonomous eCommerce assistant:
* `list-products` & `get-product`: Search catalog items, SKU, prices, stock, and variations.
* `create-product` & `update-product`: Add or modify products, descriptions, prices, and stock statuses.
* `delete-product`: Remove products from catalog.
* `list-variations`, `create-variation`, & `update-variation`: Manage variable product options.
* `list-attributes`, `create-attribute`, `create-attribute-term`, & `set-product-attributes`: Product attribute management.
* `list-product-categories` & `create-product-category`: Manage product categories.
* `list-product-tags` & `create-product-tag`: Manage product tags.
* `list-orders`, `get-order`, & `update-order-status`: Track orders and change status (processing, completed, on-hold, etc.).
* `list-order-notes` & `add-order-note`: Append customer or private order notes.
* `refund-order`: Issue full or partial order refunds.
* `list-customers` & `get-customer`: Query registered shoppers and order histories.
* `list-coupons`, `create-coupon`, `update-coupon`, & `delete-coupon`: Create and manage discount codes.
* `list-product-reviews` & `moderate-product-review`: Inspect and moderate customer reviews.
* `get-sales-summary` & `get-store-settings`: Retrieve sales analytics, revenue reports, and currency settings.

#### 7. Easy Digital Downloads (`onemcp-edd`)
These abilities load from the OneMCP Pro plugin. Full management for digital product shops:
* `list-downloads`, `get-download`, `create-download`, & `update-download`: Manage digital files, prices, and download items.
* `list-edd-orders`, `get-edd-order`, `update-edd-order`, & `update-edd-order-status`: Order tracking and management.
* `add-edd-order-note`: Append order notes.
* `list-discounts`, `create-discount`, & `update-discount`: Coupon management.
* `list-edd-customers` & `get-edd-customer`: Customer records and purchase histories.
* `get-edd-sales-summary`: Earnings and sales totals.
* `list-download-logs` & `set-download-access`: Access limits and file download history.
* `update-download-link-expiration`: Set expiration for secure file download links.
* `list-licenses`, `get-license`, & `set-license-status`: Software licensing support.
* `list-subscriptions`, `get-subscription`, & `set-subscription-status`: Recurring subscription management.

#### 8. Backups & Site Safety (`onemcp-backups`)
Perform snapshots and disaster recovery over MCP:
* **Backuply**:
  * `list-backuply-backups`: List all available site backups.
  * `get-backuply-status`: Check active backup progress.
  * `create-backuply-backup`: Trigger full, database, or file backups.
  * `stop-backuply-backup`: Halt a running backup process.
  * `delete-backuply-backup`: Delete stored backup archives.
  * `restore-backuply-backup`: Trigger site restoration from a backup.
* **Duplicator**:
  * `list-duplicator-backups`: View existing packages.
  * `create-duplicator-backup`: Build a new Duplicator backup package.
  * `delete-duplicator-backup`: Delete backup packages.

---

== Installation ==

### Method 1: WordPress Dashboard
1. Log in to your WordPress administrator dashboard.
2. Navigate to **Plugins → Add New**.
3. Search for **OneMCP** or click **Upload Plugin** and select the plugin ZIP file.
4. Click **Install Now**, then click **Activate**.
5. Navigate to the new **OneMCP** menu in your WordPress sidebar.

### Method 2: Manual FTP / SFTP
1. Upload the `onemcp` folder to your WordPress `/wp-content/plugins/` directory.
2. In your WordPress admin, go to **Plugins** and click **Activate** next to **OneMCP**.

---

== Quick Start & Setup ==

1. Open **OneMCP → Connection** in your WordPress admin.
2. Click **Generate Password** under the Application Password generator.
3. Select your AI client tab. Each client connects directly to this site.
4. Copy the generated configuration snippet or terminal command.
5. Paste it into your client configuration file or terminal, and restart/connect your AI client!

---

== Frequently Asked Questions ==

= What is MCP (Model Context Protocol)? =
Model Context Protocol (MCP) is an open standard created by Anthropic that allows AI assistants (like Claude, Cursor, and IDE coding agents) to securely discover and call tools on external services. OneMCP makes your WordPress site a fully compliant MCP server.

= Does this plugin send my data to external third-party servers? =
WordPress runs every tool on your site. Clients connect directly to the WordPress REST endpoint over HTTPS. OneMCP does not send those calls through a hosted service.

= How does authentication work? =
Authentication uses standard WordPress **Application Passwords** over HTTP Basic Authentication (`Authorization: Basic <base64>`). You can easily generate, inspect, and revoke application passwords at any time from your WordPress profile or directly inside the OneMCP admin screen.

= Can AI accidentally break my website? =
Safety is our top priority:
* WordPress core abilities are enabled by default. On SitePad, SitePad Core and Pagelayer abilities are enabled by default. Other write operations, including store and backup changes, stay off until an administrator turns them on in the **Abilities** tab. Any ability can be turned off.
* The plugin verifies standard WordPress user roles and capabilities (`manage_options`, `edit_posts`, `manage_woocommerce`, etc.) on every request.
* An **Activity Log** keeps a persistent record of all tool calls and their results.

= Does OneMCP support page builders? =
Page builders are part of OneMCP Pro. That add-on covers Pagelayer, Elementor, SeedProd, Divi, Bricks, Beaver Builder, and Breakdance. The free plugin keeps WordPress core tools.

= What is included in OneMCP Pro? =
Install the separate OneMCP Pro plugin next to this one. Pro adds page builders, SEO, SMTP, cache, WooCommerce, Easy Digital Downloads, Backuply, and Duplicator, plus a longer activity log and CSV export. The free plugin keeps the MCP server and WordPress core abilities.

= Can I use this on plain HTTP (local development)? =
Yes! While WordPress normally disables Application Passwords on non-SSL environments, OneMCP includes a compatibility filter so you can develop and test locally with tools like LocalWP, DDEV, or Docker. (For live production websites, HTTPS is strongly recommended.)

= Which PHP and WordPress versions are supported? =
* WordPress 6.0 or higher.
* PHP 7.4, 8.0, 8.1, 8.2, or 8.3+.

---

== Screenshots ==

1. **Connection Screen** - View server endpoint, test connectivity, and copy tailored configs for Claude, Cursor, VS Code, and more.
2. **Integrations Screen** - View detected and supported page builders, SEO suites, SMTP mailers, cache plugins, stores, and backup plugins.
3. **Abilities Management** - Fine-grained control to enable or disable individual read/write abilities or toggle whole categories.
4. **Activity & Audit Log** - Live audit log showing recent ability calls, user IDs, timestamps, and execution statuses.

---

== Changelog ==

= 1.0.0
Initial public release of OneMCP.
