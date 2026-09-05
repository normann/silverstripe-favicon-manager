# Contributing

Thanks for considering a contribution to this module.

## Reporting bugs

Please use the [bug report template](.github/ISSUE_TEMPLATE/bug_report.md) and include:
- your SilverStripe framework version and PHP version
- whether `silverstripe/subsites` is installed
- the exact ZIP file structure you uploaded (filenames are matched exactly)
- any warnings logged (this module logs failures via PSR-3 rather than failing silently)

## Suggesting features

Please use the [feature request template](.github/ISSUE_TEMPLATE/feature_request.md) and
describe the use case, not just the desired implementation — there may be a simpler way
to solve the same problem within the existing config surface
(`icons_folder_name` / `archives_folder_name`).

## Development setup

```bash
git clone https://github.com/normann/silverstripe-favicon-manager.git
cd silverstripe-favicon-manager
composer install
```

This repo is a standalone module, not a full SilverStripe site — to test it against a
real CMS, require it as a path repository from a working SilverStripe 5 project:

```json
{
    "repositories": [
        { "type": "path", "url": "../silverstripe-favicon-manager" }
    ],
    "require": {
        "normann/silverstripe-favicon-manager": "@dev"
    }
}
```

## Running tests and coding standards

```bash
vendor/bin/phpunit
vendor/bin/phpcs
```

Both run automatically in CI on every pull request — please make sure they pass locally
first.

## Pull requests

- Keep PRs focused on one change; unrelated fixes should be separate PRs.
- Add or update a test for any behavioural change.
- Update `CHANGELOG.md` under an `[Unreleased]` heading.
- Match the existing 120-character line length convention (enforced by `phpcs.xml.dist`).

## Code of Conduct

Participation in this project is governed by the
[SilverStripe Community Code of Conduct](CODE_OF_CONDUCT.md).
