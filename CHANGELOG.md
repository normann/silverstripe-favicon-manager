# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial extraction from a client codebase into a standalone module.
- `FaviconSiteConfigExtension` — extracts favicon/manifest files from an uploaded
  generator ZIP and stores them as versioned `File`/`Image` records on `SiteConfig`.
- `Favicons.ss` template partial with built-in fragment caching via
  `getFaviconsCacheKey()`.
- Optional `silverstripe/subsites` support — favicon storage is automatically
  namespaced per-subsite when the module is installed.
- Configurable storage folder names (`icons_folder_name`, `archives_folder_name`).
