# Meta Tags for WordPress

Lightweight per-post meta tags for WordPress: title, description, robots, Open Graph and Twitter / X Card.
No upsells, no tracking, no dashboard widgets – one meta box and one settings page.

[![Buy me a coffee](https://img.shields.io/badge/Buy%20me%20a%20coffee-ffdd00?logo=buymeacoffee&logoColor=000)](https://buymeacoffee.com/weschkalnies)

## Features

- **Title** – replaces the document `<title>` (via `pre_get_document_title`).
- **Description** – `<meta name="description">`, falls back to the excerpt.
- **Robots** – `noindex`, `nofollow`, `max-snippet:N` … merged into the robots tag WordPress already prints (`wp_robots`), so there is never a second robots tag.
- **Open Graph** – `og:title`, `og:description`, `og:image` (media library picker, falls back to the featured image incl. width/height/alt), `og:type`, `og:url`, `og:site_name`, `og:locale`, `article:published_time` / `modified_time`.
- **Twitter / X Card** – `summary_large_image` when an image is available.
- **Keywords** and **custom head HTML** – optional, off by default. Raw HTML needs the `unfiltered_html` capability; other roles may only add `<meta>` and `<link>` tags.
- Works for posts, pages and all public custom post types, classic and block editor.
- Character counters, image preview, placeholders that show the automatic fallback values.
- All fields are registered post meta and readable/writable via the REST API (except custom HTML) – handy for scripts or AI/MCP tools.
- German translation included (`de_DE`, `de_DE_formal`).

Requires WordPress 6.5+ and PHP 7.4+ (tested with WordPress 7.1 and PHP 8.4).

## Installation

1. Download `metatags-x.y.z.zip` from the [latest release](https://github.com/weschkalnies/wordpress-metatags/releases/latest).
2. In WordPress go to *Plugins → Add New → Upload Plugin* and upload the zip.
3. Configure the tags under *Settings → Meta Tags* and fill in the "Meta Tags" box when editing a post.

> Use the release zip, not GitHub's "Download ZIP" button – the plugin folder has to be named `metatags`.

### Upgrading from 1.x

Upload the new version over the old one (WordPress offers "Replace current with uploaded") or replace the `wp-content/plugins/metatags` folder via FTP/SSH.
All post data is kept; the old settings are migrated automatically.
Do **not** delete the old version in wp-admin first – deleting runs the uninstall routine, which removes all plugin data.

## Filters

```php
// Post types the plugin is active for (default: all public post types except attachments).
add_filter( 'metatags_post_types', function ( $types ) {
	return array_diff( $types, array( 'product' ) );
} );

// Change or add tags before output. Each tag is [ 'name'|'property', name, content ].
add_filter( 'metatags_tags', function ( $tags, $post ) {
	$tags[] = array( 'name', 'twitter:site', '@example' );
	return $tags;
}, 10, 2 );
```

## Post meta keys

| Field | Meta key |
|---|---|
| Title | `_metaTitle` |
| Description | `_metaDesc` |
| Keywords | `_metaKeywords` |
| Robots | `_metaRobots` |
| Custom HTML | `_metaCustom` (not in REST) |
| OG title / description | `_metaOGTitle`, `_metaOGDesc` |
| OG URL / site name / type | `_metaOGURL`, `_metaOGSiteName`, `_metaOGType` |
| OG image (URL) | `_metaOGImage` |

## Building a release

```sh
bin/build-zip.sh   # creates metatags-<version>.zip from the current commit
```

Attach the zip to a GitHub release. Repository-only files (`README.md`, `.github/`, `bin/` …) are excluded via `.gitattributes`.

## License

GPL-2.0-or-later, see [LICENSE.txt](LICENSE.txt).

If the plugin saves you time: [buy me a coffee](https://buymeacoffee.com/weschkalnies) ☕
