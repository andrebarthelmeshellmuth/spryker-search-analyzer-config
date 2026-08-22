<!-- markdownlint-disable -->
# Search Analyzer Config

## Contents

- [What does this do?](#what-does-this-do)
- [Status](#status)
- [Requirements](#requirements)
- [Installation](#installation)
- [The Zed GUI](#the-zed-gui)
- [Configuration](#configuration)
- [How it works](#how-it-works)
  - [Per-analyzer opt-out: not every target analyzer needs every slot](#per-analyzer-opt-out-not-every-target-analyzer-needs-every-slot)
  - [Filter chain order is your project's responsibility — and why it matters](#filter-chain-order-is-your-projects-responsibility-and-why-it-matters)
- [Data model](#data-model)
- [Managing config without a GUI](#managing-config-without-a-gui)
- [Supported languages](#supported-languages)
- [Limitations](#limitations)
- [Testing and CI](#testing-and-ci)
- [License](#license)

## What does this do?

Lets a project stage, per `(sourceIdentifier, storeName)` scope, the analyzer-level knobs that decide how
search terms get tokenized and matched: a stemmer language, a normalization filter, a stopwords list
(none / a built-in language / a custom list), a decompound word list (for German-style compound-word
splitting), and a do-not-decompound / keyword-marker list (e.g. brand names that must survive both the
decompounder and the stemmer unchanged).

Staged config isn't applied directly — it's materialized into a live index the next time
[`spryker-community/search-index-alias`](https://github.com/andrebarthelmeshellmuth/spryker-search-index-alias)
runs a blue-green rebuild for that scope. This package registers a `TargetIndexSettingsExpanderPlugin`
that overwrites the DATA inside a fixed set of named filter slots (`sac_normalization`, `sac_synonyms`,
`sac_stopwords`, `sac_keyword_marker`, `sac_stemmer`, `sac_decompound`) — it never adds a filter to an
analyzer's own `filter` chain, and never reorders it. Declaring those slot names, and where they sit in
the chain, is entirely your project schema's job — see [How it works](#how-it-works) and, importantly,
[why chain order matters](#filter-chain-order-is-your-projects-responsibility-and-why-it-matters).

## Status

Feature-complete: a full Zed GUI (Overview/Edit/Copy/Preview/History pages, live-verified in a browser)
sits on top of the staging/edit/copy/revision-history/apply-into-rebuild pipeline, which is also tested at
the Business/Persistence layer. The Preview page runs a sample search string through both the live
analyzer chain and, via a throwaway index created and torn down within the same request, the chain as it
would be with the currently staged config applied — a real before/after diff, not a simulation. See [The
Zed GUI](#the-zed-gui).

`SearchAnalyzerConfigFacade` also remains fully usable headlessly from a project-level console command or
script — see [Managing config without a GUI](#managing-config-without-a-gui) — and five console commands
(`show`/`apply`/`copy`/`export-schema`/`prune-preview-indices`) cover the same operations from the CLI.

## Requirements

- PHP 8.3+
- A Spryker shop on `spryker/search-elasticsearch` ^1.23.0
- `spryker-community/search-index-alias` installed and adopted for the scope(s) you want to configure —
  this package has nothing to materialize a staged config into without it

## Installation

### 1. Install the package

```
composer require spryker-community/search-analyzer-config
```

### 2. Register the `SprykerCommunity` core namespace

In `config/Shared/config_default.php`:

```php
$config[KernelConstants::CORE_NAMESPACES] = array_merge(
    $config[KernelConstants::CORE_NAMESPACES] ?? [],
    ['SprykerCommunity'],
);
```

Already done if you have any other `spryker-community/*` package installed.

### 3. Generate transfers and build the schema

```
vendor/bin/console transfer:generate
vendor/bin/console propel:diff
vendor/bin/console propel:migrate
vendor/bin/console propel:model:build
```

This creates `spy_search_analyzer_config`, `spy_search_analyzer_config_term`, and
`spy_search_analyzer_config_revision` — see [Data model](#data-model). Use `propel:diff` +
`propel:migrate`, never `propel:sql:insert` (that applies the shop's *entire* schema dump, not just this
package's new tables).

### 4. Register the rebuild-target plugin

In your project's own `SearchIndexAliasDependencyProvider` (extending this package's sibling,
`spryker-community/search-index-alias`):

```php
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Plugin\SearchIndexAlias\SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin;

protected function getTargetIndexSettingsExpanderPlugins(): array
{
    return [
        new SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin(),
    ];
}
```

A forgotten registration fails **silently**: rebuilds keep succeeding, they just never pick up any staged
config.

### 5. Register the check-installation console command

In your project's own `ConsoleDependencyProvider`:

```php
use SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Console\SearchAnalyzerConfigCheckInstallationConsole;

$commands[] = new SearchAnalyzerConfigCheckInstallationConsole();
```

### 6. Register the Zed navigation entry

Copy this package's own `Communication/navigation.xml` wrapper entry (`<search-analyzer-config-gui>`)
into your project's `config/Zed/navigation.xml`, then rebuild the nav cache:

```
vendor/bin/console navigation:build-cache
```

No back-office ACL setup is needed beyond that — Spryker's own Acl module gates the new module exactly
like every other Zed module; an unrestricted (root-style) role reaches it immediately, and a restricted
role needs an explicit rule the same way it would for any other module.

### 7. Declare this package's filter slots in your project's own schema JSON

There is nothing to configure to point this package at your analyzer(s) — which analyzers it manages is
auto-discovered directly from the live cluster: any analyzer whose own `filter` chain references at least
one of this package's well-known slot names is, by definition, a target (nobody names a filter
`sac_stemmer` by accident). A fresh install with no such reference anywhere renders no-op settings —
nothing breaks, nothing applies either.

This package **never** adds a filter to an analyzer, and never reorders one — that is your project
schema's job, not something a rebuild can do for you. For each analyzer you want managed, declare
whichever of these named filters you intend to use under `analysis.filter`, and reference them from that
analyzer's own `filter` array, in the right position (see [why order
matters](#filter-chain-order-is-your-projects-responsibility-and-why-it-matters)):

- `sac_normalization`, `sac_synonyms`, `sac_stopwords`, `sac_keyword_marker`, `sac_stemmer` — plain filter
  bodies; this package only ever overwrites their contents.
- `sac_decompound` — always a `condition` filter wrapping a second, non-chain-visible filter named
  `sac_decompound_words` (declare `sac_decompound_words` under `analysis.filter` too, but never reference
  it from an analyzer's own `filter` array directly).

`search-analyzer-config:export-schema` renders your currently-staged config as a ready-to-paste `analysis`
JSON fragment, pre-populated with a starting chain in
`SearchAnalyzerConfigRenderer::CHAIN_VISIBLE_SLOT_NAMES_IN_RECOMMENDED_ORDER` — the fastest way to get a
correctly-ordered starting point instead of hand-writing one. A field left active whose slot isn't
referenced by any analyzer is simply skipped when rendered (see [Per-analyzer
opt-out](#per-analyzer-opt-out-not-every-target-analyzer-needs-every-slot)) — never a hard failure.

### 8. Verify the installation

```
vendor/bin/console search-analyzer-config:check-installation
```

Checks the core namespace, that all three Propel tables are reachable, that
`spryker-community/search-index-alias` is installed, that the rebuild-target plugin is loadable and
correctly typed, that the navigation entry is registered, and reports how many scopes already have a
staged config. It cannot verify from the CLI that your project's `SearchIndexAliasDependencyProvider`
override actually returns this package's plugin — confirm that by hand.

## The Zed GUI

Under **Search Toolbox → Search Analyzer Config** in the back office:

- **Overview** (`/search-analyzer-config/index?source=...&store=...`) — pick a source/store, see the
  staged config's current revision (applied vs. not-yet-applied — "applied" means Apply was requested
  for that revision, not that the rebuild has finished; see [Data model](#data-model)), and reach every
  other action: Edit,
  Copy to another scope, Preview, History, and Apply (the explicit rebuild trigger — see [How it
  works](#how-it-works)'s two-step Apply design).
- **Edit** — the five SCALAR fields (stemmer language, normalization filter, stopwords mode, its built-in
  language, decompounding on/off), each shared by every analyzer that references its slot (same
  index-level `analysis.filter` data, like editing a shared setting/mapping). A matrix at the top of the
  page (rows = the six well-known filter slots, columns = every analyzer the live cluster shows
  referencing at least one of them) makes that sharing explicit — each ✓ cell links straight to the one
  field below it, and each field states which analyzers it currently applies to; see [Per-analyzer
  opt-out](#per-analyzer-opt-out-not-every-target-analyzer-needs-every-slot). Saving only stages the config;
  it never touches a live index. If an active field's slot isn't referenced by one of the target analyzers,
  Save shows a one-time warning banner instead of saving immediately; "Save anyway" confirms and saves
  as-is.
- **Edit list** (reached from a "N terms staged — Edit store/locale specific list" link under each of the
  four list-shaped fields on the Edit page: synonyms, decompound words, custom stopwords, do-not-decompound)
  — a dedicated one-textarea screen per list, saving into the same scope/revision as Edit. Deliberately
  separate from the Edit page: a term list belongs to the whole store/locale scope, not to whichever
  analyzer happens to be selected there, so embedding it inline on a page framed per-analyzer would
  misleadingly suggest otherwise. Saving a list here always saves directly (no warning-gate/confirm step —
  that lives only on the main Edit page, since the Edit page's per-analyzer badges already give the
  necessary visibility before you get here).
- **Copy** — a confirm screen naming the target scope before overwriting it; see
  [`SearchAnalyzerConfigCopier`](#data-model)'s hard-override semantics.
- **Preview** — pick a target analyzer and enter a sample search string; see a stage-by-stage token
  breakdown (tokenizer → each filter in the chain) for both the live analyzer and, via a throwaway index
  created and deleted within the same request, the staged config applied. This is the tool that would have
  caught this package's own origin bug live (`Brennenstuhl` → `stuhl` → `sessel`) before it ever reached a
  real rebuild.
- **History** — every revision for a scope (newest first), with a one-click Restore that re-saves an
  earlier revision's full snapshot as a brand-new revision — an append, never a rewind.

## Configuration

Override these in your project's own `Pyz\Zed\SearchAnalyzerConfig\SearchAnalyzerConfigConfig` (extends
`SprykerCommunity\Zed\SearchAnalyzerConfig\SearchAnalyzerConfigConfig`):

| Method | Default | Purpose |
| --- | --- | --- |
| `getAllowedStemmerLanguages()` | `light_german`, `minimal_english`, `light_french` | Stemmer `language` filter values a project may pick from. A fixed allow-list, not freeform — every entry is verified to exist in your OpenSearch/Elasticsearch version. Extend it if your project verifies another language works. |
| `getAllowedNormalizationFilters()` | `german_normalization` | Same allow-list pattern, for normalization filters. |
| `getAllowedBuiltinStopwordsLanguages()` | German, English, French | Built-in Lucene stopword set identifiers selectable when `stopwordsMode = builtin`. |

## How it works

`SearchAnalyzerConfigTargetIndexSettingsExpanderPlugin::expand()` is called once per scope during a
search-index-alias rebuild, with that scope's live-cloned `settings.index` fragment. It delegates straight
to `SearchAnalyzerConfigFacade::renderIntoSettings()` → `SearchAnalyzerConfigRenderer::render()`, which:

1. Looks up the scope's staged `SearchAnalyzerConfigTransfer` — a scope with no staged config returns
   `$settings` completely unchanged, per the plugin interface's own contract.
2. Auto-discovers which analyzers in `$settings` to manage: any analyzer whose own `filter` array already
   references at least one of this package's slot names (`sac_normalization`, `sac_synonyms`,
   `sac_stopwords`, `sac_keyword_marker`, `sac_stemmer`, `sac_decompound`) is a target — see
   `SearchAnalyzerConfigRendererInterface::resolveTargetAnalyzerNames()`. **It never adds a name to that
   array, and never changes its order** — whether a slot exists, and where it sits relative to every other
   filter on that analyzer, is entirely your project schema's decision (see [Installation step
   7](#7-declare-this-packages-filter-slots-in-your-projects-own-schema-json) and [why order
   matters](#filter-chain-order-is-your-projects-responsibility-and-why-it-matters)).
3. For each slot that IS referenced, overwrites its full body under `analysis.filter` with fresh data —
   `word_list` for the decompounder, `synonyms` for the synonym filter, `stopwords`/`language` for
   stopwords/stemmer, `keywords` for the keyword marker, and the do-not-decompound list inlined as a
   Painless script literal for the condition filter (see below). A field left at its default/inactive
   value (e.g. no custom stopwords) still gets a definitive, empty body written — `{"type": "synonym",
   "synonyms": []}` and friends are valid, inert filter bodies — so a scope that had a value and had it
   cleared doesn't leave stale data behind.
4. For a field with an ACTIVE value (e.g. a non-empty synonym list) whose slot is **not** referenced by
   ONE of several target analyzers, that field is simply skipped for that analyzer — a deliberate
   per-analyzer opt-out, not an error. This is what lets a project wire decompounding/synonyms/etc. into
   SOME of its target analyzers (e.g. an index-time analyzer) and leave them out of others (e.g. a
   search-time one), using the exact same staged list either way — see [Per-analyzer opt-out: not every
   target analyzer needs every slot](#per-analyzer-opt-out-not-every-target-analyzer-needs-every-slot).

`sac_decompound` is always a `condition` filter wrapping a second, non-chain-visible inner filter,
`sac_decompound_words` — never referenced from an analyzer's own `filter` array directly, only from
`sac_decompound`'s own `filter` key, so it needs no chain position of its own. An empty do-not-decompound
list degrades the condition script to "always true" (i.e. behaves identically to an unconditional
decompounder), so there's no separate "plain vs. condition-wrapped" filter identity to choose between.

### Per-analyzer opt-out: not every target analyzer needs every slot

When more than one analyzer is auto-discovered as a target (e.g. this shop's own
`fulltext_index_analyzer` and `fulltext_search_analyzer`), they don't all have to reference the same
slots. Declaring `sac_decompound` in one analyzer's chain and leaving it out of another's is a legitimate,
deliberate choice — that analyzer just doesn't get decompounding, using the exact same staged word list
the other analyzer gets (the slot's body is shared, index-level `analysis.filter` data; only whether an
analyzer *references* the name differs). This applies uniformly to all six slots (`sac_normalization`,
`sac_decompound`, `sac_synonyms`, `sac_stopwords`, `sac_keyword_marker`, `sac_stemmer`), not just
decompound.

Because "this analyzer intentionally doesn't reference the slot" and "someone forgot to declare it" look
identical from this package's side, the Zed Edit page surfaces it two ways:

- **Up front, as a matrix** — `SearchAnalyzerConfigFacade::describeSlotAvailability()` is a pure structural
  read (independent of any field's current value) that the Edit page renders as a slot × analyzer grid at
  the top of the page: a ✓ plus an "Edit" link in every cell where that analyzer's own chain references
  the slot, a blank cell where it doesn't. Every field below still applies to the whole scope regardless of
  which column you clicked through from — editing it changes it for every analyzer whose column shows a
  checkmark, same as editing a shared setting/mapping.
- **Before saving, on the main Edit page only** — submitting it runs a read-only check
  (`SearchAnalyzerConfigFacade::collectMissingSlotWarnings()`) and, if any active field's slot isn't
  referenced by one of the target analyzers, shows a one-time warning banner listing exactly which
  field/analyzer combinations are affected, instead of saving immediately. Reviewing it and clicking
  **"Save anyway"** confirms and saves as-is; nothing is persisted until then. This warning is advisory
  only and never reappears once confirmed for that submit — it is NOT recorded into the scope's revision
  history. An Edit-list screen saves directly without this gate, since the Edit page's badges already give
  that visibility before you ever get there.

### Filter chain order is your project's responsibility — and why it matters

This package does not, and cannot, safely guess the right position for its own filters — only your
project's schema knows what else is in the chain (a search-debug synonym filter, an n-gram filter for
prefix matching, a custom char filter, …). But getting the order wrong doesn't just produce mediocre
search results — it can make an index **fail to build at all**, so it's worth understanding the mechanism:

- OpenSearch/Elasticsearch builds a synonym filter's `SynonymMap` by re-analyzing each synonym rule's own
  text through every filter positioned BEFORE that synonym filter in the chain. That re-analysis needs a
  strict 1:1 mapping from input token to output token.
- A filter that turns one token into more than one — an n-gram/edge-ngram/shingle filter, or this
  package's `sac_decompound` (a `dictionary_decompounder`, whose whole purpose is turning one compound
  word into several) — breaks that 1:1 mapping. OpenSearch hard-rejects a synonym filter positioned
  downstream of one, at index-creation time, with an error like `"failed to build synonyms"` (or, if the
  synonym filter is asked to parse against the offending filter directly rather than merely sitting after
  it in the same analyzer, `"Token filter [...] cannot be used to parse synonyms"`).
- **The rule applies to EVERY synonym-type filter in the analyzer, not just this package's own
  `sac_synonyms`.** A demoshop rebuild failure was traced to exactly this: `fulltext_search_analyzer` also
  carries `search-debug`'s own `search_debug_synonyms` filter — with `sac_synonyms` correctly repositioned
  ahead of `sac_decompound` the rebuild *still* failed, because `search_debug_synonyms` was also
  positioned after `sac_decompound`. If your schema declares more than one synonym filter on an analyzer,
  every one of them needs to precede every token-multiplying filter, not just this package's own.

**Recommended order**, matching `SearchAnalyzerConfigRenderer::CHAIN_VISIBLE_SLOT_NAMES_IN_RECOMMENDED_ORDER`
(also what `search-analyzer-config:export-schema` pre-populates): normalization → **all synonym filters**
→ stopwords → keyword_marker → stemmer → `sac_decompound`, with any n-gram/edge-ngram/shingle filter
(e.g. a prefix-matching filter on an index-time analyzer) placed after `sac_decompound` too. Stemming and
stopword removal don't multiply tokens, so their exact position relative to `sac_decompound` isn't
constrained by this mechanism the way synonym filters are — the recommended order is just a sensible
linguistic default, not itself an OpenSearch requirement.

This constraint is entirely enforced by OpenSearch itself, not by this package — there is no code here
that validates or auto-corrects your schema's chain order. The one automated backstop is
`SearchAnalyzerConfigFacade::save()`, which runs a real create-and-delete probe against the live cluster
(`SearchAnalyzerConfigPreviewer::validateAgainstLiveCluster()`) before persisting any config — so a bad
chain order already declared in your schema fails loudly on Save with OpenSearch's own error message, not
silently until the next rebuild.

### The do-not-decompound list is a security boundary, not a nicety

OpenSearch/Elasticsearch's `condition` token filter's `analysis` script context does **not** support
`script.params` (verified empirically) — so protecting a do-not-decompound term from the decompounder
means inlining it as a literal into the filter's `script.source`:

```
def sacBrands = ["brennenstuhl", "contorion-24.7"]; return !sacBrands.contains(token.getTerm().toString().toLowerCase());
```

Every term reaching that point has already passed `SearchAnalyzerConfigValidator`, which rejects anything
outside `SearchAnalyzerConfigConfig::TERM_PATTERN` (letters, digits, `_`, `-`, `.` only) for exactly this
list — a validator bypass that reaches a live cluster is a security issue (script injection via a
persisted term), not a bug. `SearchAnalyzerConfigRenderer` re-checks the same pattern at render time and
throws `SearchAnalyzerConfigInvalidTermException` rather than ever inlining an unvalidated term, as a
defensive second gate.

The **same** do-not-decompound list also feeds a plain `keyword_marker` filter placed immediately before
the stemmer — proven to protect a brand term from the stemmer (the decompounder needs the `condition`
wrapper above; `keyword_marker` alone does not protect against decompounding).

## Data model

Three tables, one row per `(sourceIdentifier, storeName)` scope for the first:

- **`spy_search_analyzer_config`** — the scope's editable columns (stemmer language, normalization
  filter, stopwords mode/language, decompound toggle) plus `revision` (bumped on every save) and
  `applied_revision`/`applied_index_name`/`applied_at` (the last revision a rebuild was **requested**
  for — `revision > applied_revision` means there are staged, not-yet-requested edits). This records
  "Apply was triggered for this revision", not "the rollout finished/flipped" — search-index-alias
  exposes no rollout-completion hook this package could listen to instead.
- **`spy_search_analyzer_config_term`** — one polymorphic table for all four term lists (decompound
  words, synonyms, stopwords, do-not-decompound), disambiguated by `list_type`. A save **fully replaces**
  a scope's term rows rather than diffing them.
- **`spy_search_analyzer_config_revision`** — append-only audit/restore trail: every save writes one new
  row holding a full JSON snapshot of the parent row's editable columns plus its complete term list at
  that point, so "restore to revision N" is a single row read, not a reconstruction.

No foreign key to search-index-alias's own `spy_search_index_rollout` — this package stays independently
installable without a hard schema dependency on that sibling; `applied_index_name`/`applied_at` are
enough to correlate manually.

## Managing config without a GUI

For scripted/headless use (imports, CI, a project-level console command) instead of the [Zed GUI](#the-zed-gui):

```php
use Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\SearchAnalyzerConfigFacade;

$errors = (new SearchAnalyzerConfigFacade())->save(
    (new SearchAnalyzerConfigTransfer())
        ->setSourceIdentifier('page')
        ->setStoreName('DE')
        ->setStemmerLanguage('light_german')
        ->setDecompoundEnabled(true)
        ->setDecompoundWords(new ArrayObject([
            (new SearchAnalyzerConfigTermTransfer())->setTerm('kabel'),
            (new SearchAnalyzerConfigTermTransfer())->setTerm('trommel'),
        ]))
        ->setDoNotDecompoundTerms(new ArrayObject([
            (new SearchAnalyzerConfigTermTransfer())->setTerm('Brennenstuhl'),
        ])),
    SearchAnalyzerConfigConfig::CHANGE_SOURCE_MANUAL,
    'your-username',
);

if ($errors !== []) {
    // validation failed — nothing was persisted
}
```

`save()` validates before persisting and returns the validation errors (empty array on success — nothing
is persisted otherwise). `copy()` duplicates a scope's staged config into another `(sourceIdentifier,
storeName)` scope. `findByScope()` reads one back. `renderIntoSettings()` is the same call the rebuild
plugin makes — useful for previewing what a rebuild would produce against a throwaway settings array,
without going through a real search-index-alias rebuild.

## Supported languages

The stemmer/normalization/stopwords allow-lists ship with a conservative default (German, with English
and French stemming/stopwords also available) — extend `getAllowedStemmerLanguages()` /
`getAllowedNormalizationFilters()` / `getAllowedBuiltinStopwordsLanguages()` for any other language your
own OpenSearch/Elasticsearch version supports, after verifying it live (a value not actually recognized
by your cluster fails the rebuild, not silently).

## Limitations

- Filter chain order (and whether a slot exists on an analyzer at all) is fixed by your project's own
  schema, not user-configurable per scope from the GUI or CLI — see [Installation step
  8](#8-declare-this-packages-filter-slots-in-your-projects-own-schema-json) and [why order
  matters](#filter-chain-order-is-your-projects-responsibility-and-why-it-matters). An earlier version of
  this package used to insert/reorder filters into an analyzer's chain automatically on the project's
  behalf; that was dropped in favor of the current schema-declares-everything design, because auto-
  inserting silently hides exactly the kind of chain-order mistake described above, and it can never know
  about a filter it didn't ship itself (like `search-debug`'s own `search_debug_synonyms`) — the automated
  fix worked for this package's own filters and left other synonym filters just as broken.
- A rebuild clones settings from the currently-**live** index, never re-reads your project's schema JSON —
  so fixing chain order in your schema file alone does nothing for an already-installed, already-adopted
  scope. The live index itself needs correcting (e.g. `POST _close` → `PUT _settings` with the corrected
  `analysis.analyzer.*.filter` array → `POST _open` — no reindex needed, since only the filter chain's
  order is changing, not the definitions), and only then does a fresh rebuild successfully clone the fix
  forward.
- The Preview page's throwaway index only carries `settings.analysis` (no mappings/documents) — it proves
  the filter chain's token output, not full-document relevance scoring.
- No GUI Presentation (browser-automation) test suite yet for the new pages — verified manually in a real
  browser session instead; see [Testing and CI](#testing-and-ci).

## Testing and CI

Three suites, run at different layers:

- **Portable** (`@group Portable`, `composer test-portable`) — Copier, Renderer, Validator: pure logic
  against Transfer objects only, no Locator/DB/search engine. Runs standalone, including in CI with no
  host shop.
- **`@group NeedsDatabase`** — Mapper, EntityManager, Repository, Facade, and the rebuild-target plugin:
  real database rows, never mocked (revision-increment and full-replace term-list behavior are exactly
  what a mocked query builder couldn't confirm). Needs a host shop's generated Locator and
  `Generated\Shared\Transfer\*`/`Orm\Zed\SearchAnalyzerConfig\Persistence\*` classes.
- `composer phpstan` (host shop) vs. `composer phpstan-ci` (standalone, see `phpstan.ci.neon` for exactly
  which host-shop-only classes are excluded and why).

```
composer validate --no-check-publish
vendor/bin/phpcs
vendor/bin/phpmd src text phpmd.xml
vendor/bin/phpmd src text phpmd-public-methods.xml
composer rector-dry-run
composer check-floors
composer test-portable
```

## License

MIT, see [LICENSE](LICENSE).
