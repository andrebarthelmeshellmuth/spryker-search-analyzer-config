<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Renderer;

use ArrayObject;
use Generated\Shared\Transfer\SearchAnalyzerConfigTransfer;
use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigInvalidTermException;
use SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigMissingFilterSlotException;

/**
 * Renders one scope's SearchAnalyzerConfigTransfer into real OpenSearch/Elasticsearch `analysis`
 * settings.
 *
 * THIS PACKAGE NEVER ADDS A FILTER TO AN ANALYZER'S OWN `filter` CHAIN, AND NEVER CHANGES ITS ORDER.
 * That's a project-schema decision, not this package's to make -- so every field this package manages
 * corresponds to a fixed, well-known filter NAME (`sac_normalization`, `sac_decompound`, `sac_synonyms`,
 * `sac_stopwords`, `sac_keyword_marker`, `sac_stemmer`) that the PROJECT'S OWN schema must already
 * declare under `analysis.filter` and already reference from the target analyzer's own `filter` array,
 * positioned wherever the project needs (e.g. ahead of an n-gram filter, to avoid the OpenSearch
 * constraint documented in the README's "Limitations"). This package's only job is to overwrite the DATA
 * inside an already-referenced slot -- never to decide whether it exists or where it sits.
 *
 * A field with an ACTIVE value (e.g. a non-empty synonym list) whose slot is NOT referenced by the target
 * analyzer is a real configuration problem, not something to silently skip -- SearchAnalyzerConfigMissingFilterSlotException
 * is thrown, and `SearchAnalyzerConfigPreviewer::validateAgainstLiveCluster()` is what turns that into a
 * clean save-time error instead of a rebuild failing later.
 *
 * A field left at its default/inactive value (e.g. no custom stopwords) still gets a definitive, empty
 * body written into its slot WHEN THAT SLOT ALREADY EXISTS -- `type: synonym, synonyms: []` and friends
 * are valid, inert OpenSearch filter bodies (verified live), so a scope that had a value and had it
 * cleared doesn't leave stale data behind. When the slot doesn't exist AND the field is inactive, nothing
 * is required and nothing is written -- there's nothing to blank.
 *
 * The decompound word list and the do-not-decompound/brand list share ONE chain-visible slot,
 * `sac_decompound` -- always a `condition` filter wrapping a second, non-chain-visible inner filter
 * (`sac_decompound_words`, freely written whenever the outer slot is referenced, exactly like the old
 * design's `sac_decompound` was always registered regardless of chain membership). An empty
 * do-not-decompound list degrades the condition script to "always true" -- i.e. behaves identically to
 * an unconditional decompounder -- so there is no separate "plain vs. condition-wrapped" filter identity
 * to choose between anymore; verified live that this degrades correctly.
 */
class SearchAnalyzerConfigRenderer implements SearchAnalyzerConfigRendererInterface
{
    /**
     * @var string
     */
    protected const SLOT_NORMALIZATION = 'sac_normalization';

    /**
     * @var string
     */
    protected const SLOT_DECOMPOUND = 'sac_decompound';

    /**
     * Not chain-visible -- only ever referenced from SLOT_DECOMPOUND's own `filter` array, never from an
     * analyzer's `filter` list directly, so it needs no project pre-declaration of its own.
     *
     * @var string
     */
    protected const SLOT_DECOMPOUND_INNER = 'sac_decompound_words';

    /**
     * @var string
     */
    protected const SLOT_SYNONYMS = 'sac_synonyms';

    /**
     * @var string
     */
    protected const SLOT_STOPWORDS = 'sac_stopwords';

    /**
     * @var string
     */
    protected const SLOT_KEYWORD_MARKER = 'sac_keyword_marker';

    /**
     * @var string
     */
    protected const SLOT_STEMMER = 'sac_stemmer';

    /**
     * Every chain-visible slot name this package understands, in a sensible default pipeline order
     * (`SLOT_DECOMPOUND_INNER` excluded -- see its own doc block for why it's never chain-visible).
     * Public so a GREENFIELD project (no live index to clone settings from yet, see
     * `SearchAnalyzerConfigExportSchemaConsole`'s own doc block) has a documented starting chain to declare
     * in its own schema JSON before this package can render anything into it.
     *
     * SLOT_DECOMPOUND comes LAST, deliberately: it's a `condition` filter wrapping a `dictionary_decompounder`,
     * which -- like an n-gram/edge-ngram/shingle filter -- can emit more than one token per input token, and
     * OpenSearch's SynonymMap builder needs a strict 1:1 pre-filter chain to re-analyze each synonym rule's
     * text (see the README's "Limitations"). So SLOT_DECOMPOUND has EXACTLY the same ordering constraint
     * relative to SLOT_SYNONYMS that an n-gram filter does -- verified live: putting it before SLOT_SYNONYMS
     * fails index creation with "failed to build synonyms", identical to the n-gram case.
     *
     * @var array<string>
     */
    public const CHAIN_VISIBLE_SLOT_NAMES_IN_RECOMMENDED_ORDER = [
        self::SLOT_NORMALIZATION,
        self::SLOT_SYNONYMS,
        self::SLOT_STOPWORDS,
        self::SLOT_KEYWORD_MARKER,
        self::SLOT_STEMMER,
        self::SLOT_DECOMPOUND,
    ];

    /**
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string, mixed> $baseSettings
     * @param array<string> $targetAnalyzerNames
     *
     * @throws \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigMissingFilterSlotException
     *
     * @return array<string, mixed>
     */
    public function render(
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array $baseSettings,
        array $targetAnalyzerNames,
    ): array {
        $doNotDecompoundTerms = $this->extractTerms($searchAnalyzerConfigTransfer->getDoNotDecompoundTerms());
        $missingSlotMessages = [];

        foreach ($targetAnalyzerNames as $targetAnalyzerName) {
            if (!isset($baseSettings['analysis']['analyzer'][$targetAnalyzerName]['filter']) || !is_array($baseSettings['analysis']['analyzer'][$targetAnalyzerName]['filter'])) {
                if ($this->hasAnyActiveField($searchAnalyzerConfigTransfer)) {
                    $missingSlotMessages[] = sprintf(
                        'Target analyzer "%s" was not found in the current settings, but this scope has an active config. Check getTargetAnalyzerNames() for a typo, or that this analyzer is really declared there.',
                        $targetAnalyzerName,
                    );
                }

                continue;
            }

            $this->applyNormalizationSlot($baseSettings, $targetAnalyzerName, $searchAnalyzerConfigTransfer, $missingSlotMessages);
            $this->applyDecompoundSlot($baseSettings, $targetAnalyzerName, $searchAnalyzerConfigTransfer, $doNotDecompoundTerms, $missingSlotMessages);
            $this->applySynonymsSlot($baseSettings, $targetAnalyzerName, $searchAnalyzerConfigTransfer, $missingSlotMessages);
            $this->applyStopwordsSlot($baseSettings, $targetAnalyzerName, $searchAnalyzerConfigTransfer, $missingSlotMessages);
            $this->applyKeywordMarkerSlot($baseSettings, $targetAnalyzerName, $searchAnalyzerConfigTransfer, $doNotDecompoundTerms, $missingSlotMessages);
            $this->applyStemmerSlot($baseSettings, $targetAnalyzerName, $searchAnalyzerConfigTransfer, $missingSlotMessages);
        }

        if ($missingSlotMessages !== []) {
            throw new SearchAnalyzerConfigMissingFilterSlotException($missingSlotMessages);
        }

        return $baseSettings;
    }

    /**
     * Mirrors the per-slot "is this field active" conditions used throughout apply*Slot() below -- used
     * only to decide whether a target analyzer that isn't in $baseSettings at all is worth an error (a
     * scope with nothing active has nothing that could go silently unapplied).
     *
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     */
    protected function hasAnyActiveField(SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer): bool
    {
        return $searchAnalyzerConfigTransfer->getNormalizationFilter() !== null
            || $searchAnalyzerConfigTransfer->getStemmerLanguage() !== null
            || (bool)$searchAnalyzerConfigTransfer->getDecompoundEnabled()
            || $this->extractTerms($searchAnalyzerConfigTransfer->getSynonyms()) !== []
            || $this->extractTerms($searchAnalyzerConfigTransfer->getDoNotDecompoundTerms()) !== []
            || ($searchAnalyzerConfigTransfer->getStopwordsMode() !== null && $searchAnalyzerConfigTransfer->getStopwordsMode() !== SearchAnalyzerConfigConfig::STOPWORDS_MODE_NONE);
    }

    /**
     * @param array<string, mixed> $baseSettings
     * @param string $targetAnalyzerName
     * @param string $slotName
     * @param array<string, mixed>|null $body Null means "leave this slot alone entirely" -- used by fields with no natural empty/off representation (stemmer language, normalization type).
     * @param bool $required
     * @param string $fieldLabel For the missing-slot error message.
     * @param array<string> $missingSlotMessages
     */
    protected function applySlot(
        array &$baseSettings,
        string $targetAnalyzerName,
        string $slotName,
        ?array $body,
        bool $required,
        string $fieldLabel,
        array &$missingSlotMessages,
    ): void {
        if ($body === null) {
            return;
        }

        $chainFilterNames = $baseSettings['analysis']['analyzer'][$targetAnalyzerName]['filter'];

        if (!in_array($slotName, $chainFilterNames, true)) {
            if ($required) {
                $missingSlotMessages[] = sprintf(
                    'No "%s" filter is referenced by analyzer "%s", but %s has an active value for this scope. Declare "%s" under analysis.filter and add it to that analyzer\'s own filter chain first.',
                    $slotName,
                    $targetAnalyzerName,
                    $fieldLabel,
                    $slotName,
                );
            }

            return;
        }

        $baseSettings['analysis']['filter'][$slotName] = $body;
    }

    /**
     * @param array<string, mixed> $baseSettings
     * @param string $targetAnalyzerName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string> $missingSlotMessages
     */
    protected function applyNormalizationSlot(
        array &$baseSettings,
        string $targetAnalyzerName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array &$missingSlotMessages,
    ): void {
        $normalizationFilter = $searchAnalyzerConfigTransfer->getNormalizationFilter();

        // No natural "off" body for a type-defining filter (there is no such thing as a no-op
        // normalization type) -- an unset value means "don't touch this slot at all", not "blank it".
        $body = $normalizationFilter ? ['type' => $normalizationFilter] : null;

        $this->applySlot($baseSettings, $targetAnalyzerName, static::SLOT_NORMALIZATION, $body, (bool)$normalizationFilter, 'normalizationFilter', $missingSlotMessages);
    }

    /**
     * @param array<string, mixed> $baseSettings
     * @param string $targetAnalyzerName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string> $doNotDecompoundTerms Already-extracted.
     * @param array<string> $missingSlotMessages
     */
    protected function applyDecompoundSlot(
        array &$baseSettings,
        string $targetAnalyzerName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array $doNotDecompoundTerms,
        array &$missingSlotMessages,
    ): void {
        $decompoundEnabled = (bool)$searchAnalyzerConfigTransfer->getDecompoundEnabled();
        $chainFilterNames = $baseSettings['analysis']['analyzer'][$targetAnalyzerName]['filter'];
        $slotPresent = in_array(static::SLOT_DECOMPOUND, $chainFilterNames, true);

        if (!$slotPresent) {
            if ($decompoundEnabled) {
                $missingSlotMessages[] = sprintf(
                    'No "%s" filter is referenced by analyzer "%s", but decompounding is enabled for this scope. Declare "%s" (a `condition` filter, see README) under analysis.filter and add it to that analyzer\'s own filter chain first.',
                    static::SLOT_DECOMPOUND,
                    $targetAnalyzerName,
                    static::SLOT_DECOMPOUND,
                );
            }

            return;
        }

        $baseSettings['analysis']['filter'][static::SLOT_DECOMPOUND_INNER] = [
            'type' => 'dictionary_decompounder',
            'word_list' => $decompoundEnabled ? $this->extractTerms($searchAnalyzerConfigTransfer->getDecompoundWords()) : [],
        ];

        $baseSettings['analysis']['filter'][static::SLOT_DECOMPOUND] = [
            'type' => 'condition',
            'filter' => [static::SLOT_DECOMPOUND_INNER],
            'script' => ['source' => $this->buildDoNotDecompoundScriptSource($doNotDecompoundTerms)],
        ];
    }

    /**
     * @param array<string, mixed> $baseSettings
     * @param string $targetAnalyzerName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string> $missingSlotMessages
     */
    protected function applySynonymsSlot(
        array &$baseSettings,
        string $targetAnalyzerName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array &$missingSlotMessages,
    ): void {
        $synonyms = $this->extractTerms($searchAnalyzerConfigTransfer->getSynonyms());
        $body = ['type' => 'synonym', 'synonyms' => $synonyms];

        $this->applySlot($baseSettings, $targetAnalyzerName, static::SLOT_SYNONYMS, $body, $synonyms !== [], 'the synonym list', $missingSlotMessages);
    }

    /**
     * @param array<string, mixed> $baseSettings
     * @param string $targetAnalyzerName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string> $missingSlotMessages
     */
    protected function applyStopwordsSlot(
        array &$baseSettings,
        string $targetAnalyzerName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array &$missingSlotMessages,
    ): void {
        $stopwordsMode = $searchAnalyzerConfigTransfer->getStopwordsMode();

        $stopwords = match ($stopwordsMode) {
            SearchAnalyzerConfigConfig::STOPWORDS_MODE_BUILTIN => $searchAnalyzerConfigTransfer->getStopwordsBuiltinLanguage(),
            SearchAnalyzerConfigConfig::STOPWORDS_MODE_CUSTOM => $this->extractTerms($searchAnalyzerConfigTransfer->getStopwords()),
            default => [],
        };

        $body = ['type' => 'stop', 'stopwords' => $stopwords];
        $required = $stopwordsMode !== null && $stopwordsMode !== SearchAnalyzerConfigConfig::STOPWORDS_MODE_NONE;

        $this->applySlot($baseSettings, $targetAnalyzerName, static::SLOT_STOPWORDS, $body, $required, 'stopwordsMode', $missingSlotMessages);
    }

    /**
     * @param array<string, mixed> $baseSettings
     * @param string $targetAnalyzerName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string> $doNotDecompoundTerms Already-extracted.
     * @param array<string> $missingSlotMessages
     */
    protected function applyKeywordMarkerSlot(
        array &$baseSettings,
        string $targetAnalyzerName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array $doNotDecompoundTerms,
        array &$missingSlotMessages,
    ): void {
        $required = $doNotDecompoundTerms !== [] && (bool)$searchAnalyzerConfigTransfer->getStemmerLanguage();
        $body = ['type' => 'keyword_marker', 'keywords' => $required ? $doNotDecompoundTerms : []];

        $this->applySlot($baseSettings, $targetAnalyzerName, static::SLOT_KEYWORD_MARKER, $body, $required, 'the do-not-decompound list (with a stemmer language set)', $missingSlotMessages);
    }

    /**
     * @param array<string, mixed> $baseSettings
     * @param string $targetAnalyzerName
     * @param \Generated\Shared\Transfer\SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer
     * @param array<string> $missingSlotMessages
     */
    protected function applyStemmerSlot(
        array &$baseSettings,
        string $targetAnalyzerName,
        SearchAnalyzerConfigTransfer $searchAnalyzerConfigTransfer,
        array &$missingSlotMessages,
    ): void {
        $stemmerLanguage = $searchAnalyzerConfigTransfer->getStemmerLanguage();

        // Same reasoning as normalization: a stemmer filter has no "off" state, so unset means
        // "don't touch this slot", not "blank it".
        $body = $stemmerLanguage ? ['type' => 'stemmer', 'language' => $stemmerLanguage] : null;

        $this->applySlot($baseSettings, $targetAnalyzerName, static::SLOT_STEMMER, $body, (bool)$stemmerLanguage, 'stemmerLanguage', $missingSlotMessages);
    }

    /**
     * @param array<string> $doNotDecompoundTerms Already-validated terms (SearchAnalyzerConfigConfig::TERM_PATTERN).
     *
     * @throws \SprykerCommunity\Zed\SearchAnalyzerConfig\Business\Exception\SearchAnalyzerConfigInvalidTermException
     */
    protected function buildDoNotDecompoundScriptSource(array $doNotDecompoundTerms): string
    {
        $literals = [];

        foreach ($doNotDecompoundTerms as $doNotDecompoundTerm) {
            if (preg_match(SearchAnalyzerConfigConfig::TERM_PATTERN, $doNotDecompoundTerm) !== 1) {
                throw new SearchAnalyzerConfigInvalidTermException(sprintf(
                    'Refusing to render do-not-decompound term "%s" into Painless script source: it does not match the required pattern.',
                    $doNotDecompoundTerm,
                ));
            }

            $literals[] = json_encode(mb_strtolower($doNotDecompoundTerm), JSON_THROW_ON_ERROR);
        }

        // An empty list degrades to an unconditional "always true" -- i.e. behaves identically to no
        // condition wrapper at all -- see the class doc block for why that means SLOT_DECOMPOUND no
        // longer needs two alternate filter identities to choose between.
        return sprintf(
            'def sacBrands = [%s]; return !sacBrands.contains(token.getTerm().toString().toLowerCase());',
            implode(', ', $literals),
        );
    }

    /**
     * @param \ArrayObject<int, \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer> $searchAnalyzerConfigTermTransfers
     *
     * @return array<string>
     */
    protected function extractTerms(ArrayObject $searchAnalyzerConfigTermTransfers): array
    {
        $terms = [];

        foreach ($searchAnalyzerConfigTermTransfers as $searchAnalyzerConfigTermTransfer) {
            /** @var \Generated\Shared\Transfer\SearchAnalyzerConfigTermTransfer $searchAnalyzerConfigTermTransfer */
            $term = trim((string)$searchAnalyzerConfigTermTransfer->getTerm());

            if ($term === '') {
                continue;
            }

            $terms[] = $term;
        }

        return $terms;
    }
}
