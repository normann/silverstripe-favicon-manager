# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **Breaking:** now requires SilverStripe framework `^6.0` (and matching `^3.0`/`^6.0`
  majors of `assets`, `asset-admin`, `versioned`, `siteconfig`) and PHP `^8.3`.
  SilverStripe 5 / PHP 8.1-8.2 support continues on the `1.x` line (`main`).
- `FaviconSiteConfigExtension` now extends `SilverStripe\Core\Extension` instead of
  the removed `SilverStripe\ORM\DataExtension`.
- Updated the `SilverStripe\ORM\ValidationException` import to its new location,
  `SilverStripe\Core\Validation\ValidationException`.
- `updateCMSFields()` and `onBeforeWrite()` are now `protected`, per the SilverStripe
  6 convention for extension hook methods (they still worked as `public`, but this
  is what core itself now does).
- Bumped `phpunit/phpunit` to `^11.3` and rewrote `phpunit.xml.dist` for its config
  schema (`<coverage><include>` → top-level `<source><include>`).
- CI now runs against PHP 8.3/8.4 only (SilverStripe 6's supported PHP versions),
  dropping 8.1 and the 8.4 `continue-on-error` allowance now that both versions are
  fully supported rather than one being bleeding-edge.

## [1.0.1] - 2026-09-13

### Removed
- Removed the unused `tests/Stub/Page.php` stub (and its `phpcs.xml.dist`
  exclusion). Nothing in this module's actual dependency graph (framework,
  assets, asset-admin, siteconfig, versioned — none of which pull in
  `silverstripe/cms`) or in its own tests ever references `SiteTree`/`Page`,
  so the stub, and the `silverstripe/errorpage` rationale recorded for it,
  were dead weight — most likely a leftover from an earlier, unverified fix.

### Fixed
- Fixed a broken relative link in `CONTRIBUTING.md` pointing at
  `CODE_OF_CONDUCT.md` — the file in this repo is `code-of-conduct.md`.

## [1.0.0] - 2026-09-05

### Added
- Initial extraction from a client codebase into a standalone module.
- `FaviconSiteConfigExtension` — extracts favicon/manifest files from an uploaded
  generator ZIP and stores them as versioned `File`/`Image` records on `SiteConfig`.
- `Favicons.ss` template partial with built-in fragment caching via
  `getFaviconsCacheKey()`.
- Optional `silverstripe/subsites` support — favicon storage is automatically
  namespaced per-subsite when the module is installed.
- Configurable storage folder names (`icons_folder_name`, `archives_folder_name`).
