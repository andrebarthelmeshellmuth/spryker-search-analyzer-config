<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Shared\SearchAnalyzerConfig;

use Spryker\Shared\Kernel\AbstractSharedConfig;

class SearchAnalyzerConfigConfig extends AbstractSharedConfig
{
    /**
     * @var string
     */
    public const STOPWORDS_MODE_NONE = 'none';

    /**
     * @var string
     */
    public const STOPWORDS_MODE_BUILTIN = 'builtin';

    /**
     * @var string
     */
    public const STOPWORDS_MODE_CUSTOM = 'custom';

    /**
     * @var array<string>
     */
    public const STOPWORDS_MODES = [
        self::STOPWORDS_MODE_NONE,
        self::STOPWORDS_MODE_BUILTIN,
        self::STOPWORDS_MODE_CUSTOM,
    ];

    /**
     * @var string
     */
    public const LIST_TYPE_DECOMPOUND_WORD = 'decompound_word';

    /**
     * @var string
     */
    public const LIST_TYPE_SYNONYM = 'synonym';

    /**
     * @var string
     */
    public const LIST_TYPE_STOPWORD = 'stopword';

    /**
     * @var string
     */
    public const LIST_TYPE_DO_NOT_DECOMPOUND = 'do_not_decompound';

    /**
     * @var array<string>
     */
    public const LIST_TYPES = [
        self::LIST_TYPE_DECOMPOUND_WORD,
        self::LIST_TYPE_SYNONYM,
        self::LIST_TYPE_STOPWORD,
        self::LIST_TYPE_DO_NOT_DECOMPOUND,
    ];

    /**
     * @var string
     */
    public const CHANGE_SOURCE_MANUAL = 'manual';

    /**
     * @var string
     */
    public const CHANGE_SOURCE_COPY = 'copy';

    /**
     * @var string
     */
    public const CHANGE_SOURCE_RESTORE = 'restore';

    /**
     * @var string
     */
    public const CHANGE_SOURCE_IMPORT = 'import';

    /**
     * @var array<string>
     */
    public const CHANGE_SOURCES = [
        self::CHANGE_SOURCE_MANUAL,
        self::CHANGE_SOURCE_COPY,
        self::CHANGE_SOURCE_RESTORE,
        self::CHANGE_SOURCE_IMPORT,
    ];

    /**
     * @var string
     *
     * A conservative allow-list for a brand/do-not-decompound term that will be inlined as a Painless
     * list literal in a `condition` token filter's `script.source` (the `analysis` script context does
     * not support `script.params`, verified live — see [[search_analyzer_config_plan]]). Every persisted
     * term is validated against this pattern before it can ever reach script source, not just at render
     * time — string-into-script-source is a security boundary, not a nicety.
     */
    public const TERM_PATTERN = '/^[\p{L}\p{N}][\p{L}\p{N}_\-\.]*$/u';
}
