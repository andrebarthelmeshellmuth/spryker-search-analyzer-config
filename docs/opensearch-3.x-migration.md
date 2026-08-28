# Migrating to OpenSearch 3.x

Verified live end-to-end: a Spryker demoshop upgraded from **OpenSearch 1.3.4 to 3.5.0** (Lucene 10.3.2),
full re-export/reindex (via `search-index-alias`), a config preview re-rendered against the live 3.5
cluster, `search-analyzer-config:check-installation` re-run.

**This package needs no code change for OpenSearch 3.x.**

## Why it carries across unchanged

Two engine touchpoints, both on long-stable surfaces:

- **The staged `analysis` block** this package materializes into a target index (analyzers, tokenizers,
  token filters, char filters, normalizers) uses only primitives that have existed identically on both
  engine lineages since well before the Apache-2.0 fork. A staged config that installed on 1.3.4 installs
  byte-for-byte on 3.5.
- **The live `_analyze` calls** behind the "what would this config actually do to this text" preview return
  the same `{ "tokens": [ { "token", "start_offset", "end_offset", "type", "position" }, … ] }` shape on
  3.5 as on 1.3.4.

## One interaction to watch (not this package's code)

Materializing a staged config goes through a `search-index-alias` rebuild, which **creates a fresh index**.
OpenSearch 3.x's bundled neural-search `SemanticMappingTransformer` runs on every index create and rejects
a target schema that declares `"some-field": { "type": "object", "properties": {} }` with
`class java.util.ArrayList cannot be cast to class java.util.Map` (PHP's `json_decode` turns the empty
`{}` into `[]`, which Spryker then PUTs). This is independent of the analyzer config itself — it is a
property of the merged schema being rebuilt. Spryker Cloud Commerce fixed it in five core packages
(SC-25160); a project schema override that makes `properties` non-empty (one inert
`{ "type": "boolean", "index": false }` field) covers any third-party schema still carrying it. See
`spryker-community/search-ranking`'s migration guide for the fuller write-up and the 1.3.x → 3.5 capability
delta.
