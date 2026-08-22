# Contributing to search-analyzer-config

Thanks for considering a contribution — issues and PRs are welcome. This is a single-maintainer
open-source project, so response times may vary.

## Getting started

```
composer install
```

Requires PHP 8.3+ (CI also runs against 8.4). This package is a Spryker module: it only makes full
sense wired into a real Spryker shop (Zed layer, plus a `spryker-community/search-index-alias`
installation to actually materialize a staged config into a live index). If you're working on a
change that needs to be exercised end-to-end, you'll need a Spryker demo shop with both packages
installed as local path repositories — see the README's Installation and Testing sections.

## Before opening a PR

These are the checks CI runs; running them locally first saves a review round-trip:

```
composer validate --no-check-publish
vendor/bin/phpcs
vendor/bin/phpmd src text phpmd.xml
vendor/bin/phpmd src text phpmd-public-methods.xml
composer rector-dry-run
composer check-floors
```

`check-floors` re-resolves dependencies to the lowest versions allowed by `composer.json` and
asserts every vendor symbol used in `src/` still exists at that floor — it's the check most likely
to catch an accidental "works on my shop" dependency bump.

`composer phpstan` and the host-shop Codeception suite (`tests/SprykerCommunityTest/Zed/SearchAnalyzerConfig`)
both need to run from inside a host Spryker shop — they use the shop's generated Locator and
`Generated\Shared\Transfer\*` classes, neither of which this package can produce standalone. The
Copier/Renderer/Validator/Mapper tests (`@group Portable`) are pure logic and run standalone via
`composer test-portable`. If you can't spin up a host shop, open the PR anyway — CI covers
style/rector/dependency-floor/portable-test checks, and the static-analysis/DB-backed passes will
be run before merging.

## Making a change

- Keep PRs focused — one change per PR.
- Branch from and target `main`; branches are merged via squash, so intermediate commit messages
  don't need to be polished.
- Match the existing code style — `phpcs` and `rector-dry-run` above catch most deviations.
- Every term destined for the do-not-decompound list is inlined as a Painless script literal at
  render time (see `SearchAnalyzerConfigRenderer::buildDoNotDecompoundScriptSource()`), so
  `SearchAnalyzerConfigValidator`'s term pattern is a security boundary, not input hygiene — a
  change that loosens it, or that adds a new place a raw term reaches script source, needs the same
  validation applied there too.
- Update `README.md` when behavior changes.

## Reporting bugs or requesting features

Use the issue templates — they ask for the information needed to reproduce a bug or evaluate a
request. For security issues, see [SECURITY.md](SECURITY.md) instead of opening a public issue.

## License

By contributing, you agree your contribution is licensed under this project's [MIT license](LICENSE).
