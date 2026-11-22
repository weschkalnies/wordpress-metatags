=== Meta Tags ===
Contributors: weschkalnies
Donate link: https://buymeacoffee.com/weschkalnies
Tags: meta tags, open graph, seo, robots, twitter card
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Per-post meta title, description, robots, Open Graph and Twitter Card tags – lightweight, no tracking, no upsells.

== Description ==

Adds a "Meta Tags" box to posts, pages and all public custom post types:

* **Title** – replaces the document `<title>`.
* **Description** – `<meta name="description">`, falls back to the excerpt.
* **Canonical URL** – optional per-post override via WordPress's canonical API.
* **Robots** – noindex/nofollow toggles plus extra directives (max-snippet …) merged into the robots tag WordPress already outputs; noindex posts are excluded from the XML sitemap.
* **Open Graph** – og:title, og:description, og:image (media library picker, falls back to the featured image or a global default image in settings), og:type, og:url, og:site_name, og:locale, article times.
* **Twitter / X Card** – `summary_large_image` when an image is available, plus optional `twitter:site`.
* **Fediverse / Mastodon** – `fediverse:creator` tag (global default or per-author profile field).
* **Structured data (JSON-LD)** – minimal Schema.org `@graph` (`WebSite`, `Organization`, `WebPage`, `Article`/`BlogPosting`, archives).
* **Archives & taxonomies** – automatic tags and per-term meta fields (title, description, robots, OG image) plus optional archive noindex switches.
* **Block editor sidebar** – native sidebar with live placeholders and character counters (plus classic meta box fallback).
* **Post list column** – shows `noindex` and custom override badges directly in post overview tables.
* **Keywords** and **custom HTML** – optional, off by default.

Which tags and post types are used is configured under *Settings → Meta Tags*.

All post and term fields are registered meta and available via the REST API (except custom HTML).

= Filters =

* `metatags_post_types` – post types the plugin is active for.
* `metatags_taxonomies` – taxonomies with per-term meta fields.
* `metatags_tags` – the list of `[attribute, name, content]` tags before output.
* `metatags_schema` – Schema.org `@graph` nodes before JSON-LD output.

== Installation ==

1. Upload the `metatags` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Edit a post or page and fill in the "Meta Tags" box.

== Changelog ==

= 2.1.0 =
* New: Canonical URL override per post via WordPress core's `get_canonical_url` filter.
* New: Global default Open Graph image in Settings → Meta Tags (used as fallback for the front page, archives and posts without an image).
* New: Archive and taxonomy support – automatic tags, per-term meta fields (title, description, robots, OG image) and optional archive `noindex` switches (with XML sitemap exclusion).
* New: Improved Robots UX with dedicated `noindex` and `nofollow` checkboxes plus extra directives field.
* New: Native block editor sidebar with live placeholders, character counters and media library picker.
* New: `fediverse:creator` (global default + user profile field) and `twitter:site` settings.
* New: Selectable post types in Settings → Meta Tags and conflict warning if another SEO plugin is active.
* New: Meta Tags status column (`noindex` and override badges) in post list tables.
* New: Minimal Schema.org JSON-LD output (`WebSite`, `Organization`, `WebPage`, `BlogPosting`/`Article`, archive pages).
* Fixed: Late `init` meta registration for CPTs, unified `og:type` CPT fallback, `og:image:width`/`height`/`alt` for manually selected images, XML sitemap exclusion for `noindex` posts, front page meta description, independent Twitter Card output and consistent password protection.

= 2.0.1 =
* Security hardening: custom HTML from users without unfiltered_html is rebuilt from an attribute allowlist (no http-equiv redirects, no stylesheets, only safe link rels).
* Security hardening: the custom document title is escaped (WordPress prints pre_get_document_title results unescaped).
* Invalid tag data from the metatags_tags filter is skipped instead of causing warnings.

= 2.0.0 =
* Complete rewrite for current WordPress and PHP 8.
* Fixed: no tags were output after installation because every tag group was disabled by default.
* Fixed: Open Graph tags now use `property=` and `og:description` (Facebook & co. ignored the old tags).
* Fixed: all output is escaped; custom HTML requires the `unfiltered_html` capability.
* Fixed: fields that are switched off are no longer wiped on save.
* Fixed: invalid nested form in the meta box, PHP warnings on archive and 404 pages.
* New: custom title replaces the real document title instead of a useless `<meta name="title">`.
* New: robots directives use the core `wp_robots` API – no more duplicate robots tags.
* New: automatic fallbacks (excerpt, featured image, permalink, site name), Twitter Card, og:locale, article times.
* New: character counters, image preview, works for all public post types.
* Removed: Bootstrap 3, which was loaded on every admin page and broke the WordPress admin styles.
* Settings and post data from 1.x are migrated automatically.

= 1.3 =
* Added custom field
* Fixed meta panel CSS

= 1.2 =
* Added plugin settings to select which meta tags will be used

= 1.1 =
* Added bootstrap form, robots field

= 1.0 =
* First release
