# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial implementation: per-(source identifier, store) analyzer config (stemmer language,
  normalization filter, stopwords mode, decompound word list, synonyms, do-not-decompound brand
  list), staged with full revision history and materialized into a `search-index-alias` rebuild via
  `SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin`.
- Copy-between-scopes support (`SearchAnalyzerConfigFacade::copy()`).
- Term-level validation: the do-not-decompound list is rendered as an inlined Painless script
  literal, so terms are restricted to a conservative allow-list pattern before they can ever reach
  script source.
- Zed GUI: Overview (scope picker + summary + Apply), Edit, Copy (confirm screen), Preview
  (before/after token diff against a throwaway index), and History (revision list + Restore) pages,
  registered under Search Toolbox → Search Analyzer Config.
- `SearchAnalyzerConfigPreviewer`: runs a sample search string through the live analyzer chain and,
  via a throwaway index created and deleted within one request, the chain as it would be with the
  currently staged config applied.
- Revision history read/restore (`SearchAnalyzerConfigFacade::getRevisionHistory()` /
  `restoreRevision()`) — restore re-saves an earlier snapshot as a new revision, never a rewind.
- Console commands: `check-installation`, `show`, `apply` (triggers a search-index-alias rebuild —
  the explicit "Apply" step in this package's two-step stage/apply design), `copy`,
  `export-schema` (for greenfield installs with no live index to clone settings from), and
  `prune-preview-indices` (cleanup for a Preview request that crashed before its own cleanup ran).
- `SearchAnalyzerConfigRenderer` never adds a filter to an analyzer's own `filter` chain, and never
  reorders one — every field it manages corresponds to a fixed, well-known filter name
  (`sac_normalization`, `sac_synonyms`, `sac_stopwords`, `sac_keyword_marker`, `sac_stemmer`,
  `sac_decompound`) that the project's own schema must already declare and reference from the
  target analyzer's own `filter` array, positioned wherever the project needs. This package's job is
  only to overwrite the DATA inside an already-referenced slot. A field with an active value whose
  slot isn't referenced throws `SearchAnalyzerConfigMissingFilterSlotException` naming the missing
  slot and analyzer, rather than silently inventing or reordering anything. See the README's
  ["Filter chain order is your project's responsibility"](README.md#filter-chain-order-is-your-projects-responsibility-and-why-it-matters)
  for the OpenSearch mechanics behind why chain order matters, especially for synonym filters.
- `SearchAnalyzerConfigFacade::save()` runs a real create-and-delete probe against the live cluster
  (`SearchAnalyzerConfigPreviewer::validateAgainstLiveCluster()`) before persisting any config,
  catching a schema declared with the wrong chain order loudly on Save with OpenSearch's own error
  message, not silently until the next Apply/rebuild.

### Fixed

- A staged synonym list used to be silently mispositioned/rejected when the target analyzer already
  carried an n-gram filter — found live: this demoshop's own `fulltext_index_analyzer` (used for
  prefix matching) caused every rebuild with synonyms configured to fail with
  `Token filter [fulltext_index_ngram_filter] cannot be used to parse synonyms`. An earlier version
  of this package auto-inserted its own filters ahead of a known token-multiplying filter to work
  around this; that auto-insertion was replaced by the schema-declares-everything design above,
  because it could only ever fix this package's own filters — a second synonym filter from another
  package (e.g. `search-debug`'s own `search_debug_synonyms`) positioned after a token-multiplying
  filter hit the identical OpenSearch rejection and was invisible to the auto-fix. Both this
  demoshop's project schema and its live indices have since been corrected to declare every synonym
  filter ahead of `sac_decompound`.

- `SearchAnalyzerConfigFacade::markApplied()` is now actually called (from `requestRebuild()`, after a
  successful async rebuild request) — the Overview page's "applied"/"not yet applied" badge and "Last
  applied" line used to be permanently stale since nothing in the codebase ever called it.
- The rebuild-target plugin's `renderIntoSettings()` no longer crashes the whole async rebuild worker
  when a staged config no longer renders (e.g. the project schema dropped a slot after the config was
  saved) — it now fails safe and returns the settings unchanged for that one scope.
- `search-analyzer-config:copy` (the CLI console) now rejects a source or target scope that isn't a
  search-index-alias managed scope, matching the Zed GUI's own Copy page guard; the GUI's Copy page
  itself now also checks the copy *source*'s managed-scope status, not just the target's.
- Save and Restore in the Zed GUI now show a clean error message instead of an unhandled 500 if the
  live-cluster validation they perform throws.
- A cleanup failure while deleting a Preview/probe throwaway index can no longer clobber a real
  validation error message underneath it.
- Save-time live-cluster validation no longer fails open on a genuine internal bug — only a real
  Elastica/cluster exception is treated as "cluster hiccup, don't block the save."
- The Preview page now shows the same itemized "missing filter slot" messages Save already did for the
  same underlying problem, instead of one flattened string.
- A blank/whitespace-only term in any of the four term lists is now rejected at save time instead of
  being silently dropped only by the renderer while still counting as an active term everywhere else
  (GUI term count, `show` console output, revision history).
- The "is this scope managed by search-index-alias" lookup, previously reimplemented independently in
  four places, is now one shared `SearchIndexManagedScopeMatcher`.

### Known gaps

- No GUI Presentation (browser-automation) regression suite yet for the new pages — verified
  manually in a real browser session instead.
- No automated test coverage yet for `SearchAnalyzerConfigPreviewer`'s live-cluster-facing methods, or
  for the Communication-layer try/catch additions above — deferred at the user's own request until the
  Preview page has been exercised by hand; see [Testing and CI](#testing-and-ci).
