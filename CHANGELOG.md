# Changelog

All notable changes to this package are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Each version below also has a [GitHub release](../../releases) with the fuller write-up.

## [Unreleased]

### Documented

- OpenSearch 3.5 compatibility: the staged `analysis` block and the live `_analyze` calls behind the
  config preview use only tokenizer/filter/char-filter primitives shared across both engine lineages;
  verified end-to-end on a demoshop upgraded from 1.3.4, no code change needed. See
  `docs/opensearch-3.x-migration.md`.

## [1.0.1] - 2026-08-27

### Fixed

- Declared phantom composer dependencies directly, dropped 2 unused, moved `spryker/transfer` to
  `require-dev` (full dependency audit).

## [1.0.0] - 2026-08-23

First stable, API-committed release. Requires `spryker-community/search-index-alias ^2.0.0` (a hard
`composer require` dependency — this package has nothing to materialize a staged config into without
it).

### Added

- Per-(source identifier, store) analyzer config (stemmer language, normalization filter, stopwords
  mode, decompound word list, synonyms, do-not-decompound brand list), staged with full revision
  history and materialized into a `search-index-alias` rebuild via
  `SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin`.
- Copy-between-scopes support (`SearchAnalyzerConfigFacade::copy()`).
- Term-level validation: the do-not-decompound list is rendered as an inlined Painless script literal,
  so terms are restricted to a conservative allow-list pattern before they can reach script source.
- Zed GUI: Overview (scope picker + summary + Apply), Edit, Copy (confirm screen), Preview
  (before/after token diff against a throwaway index), and History (revision list + Restore),
  registered under Search Toolbox → Search Analyzer Config.
- `SearchAnalyzerConfigPreviewer`: runs a sample search string through the live analyzer chain and,
  via a throwaway index created and deleted within one request, the chain as it would be with the
  staged config applied.
- Revision history read/restore — restore re-saves an earlier snapshot as a new revision, never a
  rewind.
- Console commands: `check-installation`, `show`, `apply`, `copy`, `export-schema` (greenfield
  installs with no live index to clone from), and `prune-preview-indices` (cleanup for a crashed
  Preview request).
- `SearchAnalyzerConfigRenderer` never adds a filter to an analyzer's `filter` chain and never
  reorders one — it only overwrites the DATA inside an already-referenced, well-known filter slot
  (`sac_normalization`, `sac_synonyms`, `sac_stopwords`, `sac_keyword_marker`, `sac_stemmer`,
  `sac_decompound`). A field with an active value whose slot isn't referenced throws
  `SearchAnalyzerConfigMissingFilterSlotException` rather than silently inventing or reordering.
- `SearchAnalyzerConfigFacade::save()` runs a real create-and-delete probe against the live cluster
  before persisting, catching a wrong filter-chain order loudly on Save with OpenSearch's own error
  message rather than silently at the next rebuild.

### Fixed

- A staged synonym list was silently mispositioned/rejected when the target analyzer already carried
  an n-gram filter (`Token filter [...] cannot be used to parse synonyms`). The earlier auto-insertion
  workaround was replaced by the schema-declares-everything design above — it could only fix this
  package's own filters, not a second synonym filter from another package hitting the same rejection.
- `SearchAnalyzerConfigFacade::markApplied()` is now actually called (from `requestRebuild()`), so the
  Overview page's "applied" badge and "Last applied" line stop being permanently stale.
- The rebuild-target plugin's `renderIntoSettings()` no longer crashes the async rebuild worker when a
  staged config no longer renders — it fails safe and returns the settings unchanged for that scope.
- `search-analyzer-config:copy` now rejects a source or target scope that isn't a search-index-alias
  managed scope; the GUI Copy page now also checks the copy *source*, not just the target.
- Save and Restore in the Zed GUI show a clean error message instead of an unhandled 500 when
  live-cluster validation throws.
- A cleanup failure deleting a Preview/probe throwaway index can no longer clobber the real validation
  error underneath it.
- Save-time live-cluster validation no longer fails open on a genuine internal bug — only a real
  Elastica/cluster exception is treated as "cluster hiccup, don't block the save."
- The Preview page shows the same itemized "missing filter slot" messages Save already did, not one
  flattened string.
- A blank/whitespace-only term in any of the four term lists is rejected at save time instead of being
  silently dropped only by the renderer.
- The "is this scope managed by search-index-alias" lookup, previously reimplemented in four places,
  is now one shared `SearchIndexManagedScopeMatcher`.

### Known gaps

- No GUI Presentation (browser-automation) regression suite yet for the pages — verified manually.
- No automated coverage yet for `SearchAnalyzerConfigPreviewer`'s live-cluster-facing methods or the
  Communication-layer try/catch additions — deferred until the Preview page has been exercised by
  hand.

## [0.1.0] - 2026-08-21

### Added

- First pre-release: the staging / edit / copy / revision-history / apply-into-rebuild pipeline and
  Zed GUI, feature-complete and covered by Business/Persistence tests. CR and manual testing on the
  in-progress per-analyzer opt-out work not yet finished.

[Unreleased]: ../../compare/v1.0.1...HEAD
[1.0.1]: ../../releases/tag/v1.0.1
[1.0.0]: ../../releases/tag/v1.0.0
[0.1.0]: ../../releases/tag/v0.1.0
