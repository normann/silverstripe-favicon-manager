# SilverStripe Favicon Manager

Lets a CMS user upload the ZIP produced by a favicon generator tool (such as
[realfavicongenerator.net](https://realfavicongenerator.net/)) once, in Settings, and
automatically extracts, stores, and versions every favicon/manifest file it contains —
no manual file uploads, no hand-editing `manifest.json` paths.

## Requirements

| This module | SilverStripe framework | PHP  |
|-------------|------------------------|------|
| ^1.0        | ^5.0                    | ^8.1 |

For SilverStripe 4 projects, use a `^4.0`-constrained fork/branch with
`silverstripe/framework: ^4.0` and PHP `^7.4 \|\| ^8.0` instead — the extension code
itself has no PHP 8-only syntax; only the composer constraints above assume SS5.

**Optional:** if [`silverstripe/subsites`](https://github.com/silverstripe/silverstripe-subsites)
is installed, favicon files are automatically namespaced per-subsite. No extra
configuration needed — it's detected automatically.

## Installation

```bash
composer require normann/silverstripe-favicon-manager
vendor/bin/sake dev/build flush=1
```

## Usage

1. Go to **Settings → Favicon** in the CMS.
2. Generate a favicon package at [realfavicongenerator.net](https://realfavicongenerator.net/)
   (or any generator producing the same file set — see below). Leave the "path relative to
   webroot" field blank when the generator asks for it.
3. Upload the resulting ZIP via the **Favicon ZIP file** field and save.
4. All six icon files plus `manifest.json` are extracted, stored under
   `/assets/favicons/site<ID>/`, and versioned like any other SilverStripe asset.

Re-uploading a new ZIP always replaces the complete previous set — there's no
partial/incremental update.

### Expected ZIP contents

The uploaded ZIP must contain files with exactly these names:

```
favicon.ico
favicon-96x96.png
favicon.svg
apple-touch-icon.png
web-app-manifest-192x192.png
web-app-manifest-512x512.png
site.webmanifest
```

Any other files in the ZIP are silently ignored.

### Rendering in your template

Include the bundled partial wherever your layout renders `<head>` tags:

```html
<!-- Favicons -->
<% include Favicons %>
```

This renders as:

```html
<link rel="icon" type="image/png" href="/assets/favicons/site1/favicon-96x96.png?v=9_2" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/assets/favicons/site1/favicon.svg?v=8_2" />
<link rel="shortcut icon" href="/assets/favicons/site1/favicon.ico?v=10_2" />
<link rel="apple-touch-icon" sizes="180x180" href="/assets/favicons/site1/apple-touch-icon.png?v=11_2" />
<meta name="apple-mobile-web-app-title" content="Your Site Name" />
<link rel="manifest" href="/assets/favicons/site1/manifest.json?v=14_2" />
```

The partial is internally cache-fragmented and invalidates automatically whenever the
favicon set or any other SiteConfig field changes — no manual cache-clearing needed.

## Configuration

Both storage folder names can be overridden via YAML, in case they clash with an
existing folder structure in your project:

```yaml
SilverStripe\SiteConfig\SiteConfig:
  icons_folder_name: 'my-favicons'
  archives_folder_name: 'zip-archives'
```

## How it works

- The uploaded ZIP is stored (protected, non-public) under
  `/assets/<icons_folder_name>/site<ID>/<archives_folder_name>/`.
- On save, each recognised file inside the ZIP is extracted and re-uploaded through
  SilverStripe's normal `Upload` pipeline, so it gets proper hashing, versioning, and
  permissions just like a file uploaded directly through the CMS.
- `site.webmanifest` is treated specially: its `icons[].src` paths (which point at
  wherever the generator's web UI told it files would live) are rewritten to the
  site's real, subsite-aware asset path, then saved as `manifest.json`.
- Every step that can fail (a corrupt ZIP, a missing file inside it, an unreadable
  upload stream) is logged via the standard PSR-3 logger rather than failing silently.

## Running tests

```bash
vendor/bin/phpunit tests/
```

> The included tests are written for a standard `SapphireTest` environment and have
> not been run against a live SilverStripe install as part of authoring this module —
> review them against your SilverStripe version's asset-testing helpers before relying
> on them in CI.

## Versioning

This module follows [Semantic Versioning](https://semver.org/). See
[CHANGELOG.md](CHANGELOG.md) for release history. Breaking changes (e.g., renamed
config keys, changed `has_one` relation names) will always bump the major version.

## License

BSD-3-Clause. See [LICENSE](LICENSE).
