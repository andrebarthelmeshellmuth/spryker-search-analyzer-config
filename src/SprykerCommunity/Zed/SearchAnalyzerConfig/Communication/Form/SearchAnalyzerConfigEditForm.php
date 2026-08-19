<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form;

use SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The five independently-editable fields (see [[search_analyzer_config_plan]]'s locked-in "five
 * independent fields, not a single Language selector" decision), plus the four term lists. Term lists are
 * plain multi-line textareas here (one term per line) rather than the transfer's own
 * `ArrayObject<SearchAnalyzerConfigTermTransfer>` shape -- the controller converts between the two on
 * load/submit, which keeps this form free of a custom data transformer for what is, in the GUI, just a
 * list of strings.
 *
 * `stemmerLanguageChoices`/`normalizationFilterChoices`/`stopwordsBuiltinLanguageChoices` are threaded in
 * as form options (built from `SearchAnalyzerConfigConfig::getAllowed*()` by the CommunicationFactory)
 * rather than read from a hardcoded list here, so a project's own config override is reflected without
 * touching this class.
 */
class SearchAnalyzerConfigEditForm extends AbstractType
{
    /**
     * @var string
     */
    public const FIELD_SOURCE_IDENTIFIER = 'sourceIdentifier';

    /**
     * @var string
     */
    public const FIELD_STORE_NAME = 'storeName';

    /**
     * @var string
     */
    public const FIELD_STEMMER_LANGUAGE = 'stemmerLanguage';

    /**
     * @var string
     */
    public const FIELD_NORMALIZATION_FILTER = 'normalizationFilter';

    /**
     * @var string
     */
    public const FIELD_STOPWORDS_MODE = 'stopwordsMode';

    /**
     * @var string
     */
    public const FIELD_STOPWORDS_BUILTIN_LANGUAGE = 'stopwordsBuiltinLanguage';

    /**
     * @var string
     */
    public const FIELD_STOPWORDS_CUSTOM_TEXT = 'stopwordsCustomText';

    /**
     * @var string
     */
    public const FIELD_DECOMPOUND_ENABLED = 'decompoundEnabled';

    /**
     * @var string
     */
    public const FIELD_DECOMPOUND_WORDS_TEXT = 'decompoundWordsText';

    /**
     * @var string
     */
    public const FIELD_DO_NOT_DECOMPOUND_TERMS_TEXT = 'doNotDecompoundTermsText';

    /**
     * @var string
     */
    public const FIELD_SYNONYMS_TEXT = 'synonymsText';

    /**
     * @var string
     */
    public const OPTION_STEMMER_LANGUAGE_CHOICES = 'stemmerLanguageChoices';

    /**
     * @var string
     */
    public const OPTION_NORMALIZATION_FILTER_CHOICES = 'normalizationFilterChoices';

    /**
     * @var string
     */
    public const OPTION_STOPWORDS_BUILTIN_LANGUAGE_CHOICES = 'stopwordsBuiltinLanguageChoices';

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefined([
            static::OPTION_STEMMER_LANGUAGE_CHOICES,
            static::OPTION_NORMALIZATION_FILTER_CHOICES,
            static::OPTION_STOPWORDS_BUILTIN_LANGUAGE_CHOICES,
        ]);
        $resolver->setDefaults([
            static::OPTION_STEMMER_LANGUAGE_CHOICES => [],
            static::OPTION_NORMALIZATION_FILTER_CHOICES => [],
            static::OPTION_STOPWORDS_BUILTIN_LANGUAGE_CHOICES => [],
        ]);
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add(static::FIELD_SOURCE_IDENTIFIER, HiddenType::class, [
            'constraints' => [new NotBlank()],
        ]);
        $builder->add(static::FIELD_STORE_NAME, HiddenType::class, [
            'constraints' => [new NotBlank()],
        ]);

        $builder->add(static::FIELD_STEMMER_LANGUAGE, ChoiceType::class, [
            'label' => 'Stemmer language',
            'choices' => array_flip((array)$options[static::OPTION_STEMMER_LANGUAGE_CHOICES]),
            'required' => false,
        ]);
        $builder->add(static::FIELD_NORMALIZATION_FILTER, ChoiceType::class, [
            'label' => 'Normalization filter',
            'choices' => array_flip((array)$options[static::OPTION_NORMALIZATION_FILTER_CHOICES]),
            'required' => false,
        ]);

        $builder->add(static::FIELD_STOPWORDS_MODE, ChoiceType::class, [
            'label' => 'Stopwords mode',
            'choices' => [
                'None' => SearchAnalyzerConfigConfig::STOPWORDS_MODE_NONE,
                'Built-in language' => SearchAnalyzerConfigConfig::STOPWORDS_MODE_BUILTIN,
                'Custom list' => SearchAnalyzerConfigConfig::STOPWORDS_MODE_CUSTOM,
            ],
            'expanded' => true,
        ]);
        $builder->add(static::FIELD_STOPWORDS_BUILTIN_LANGUAGE, ChoiceType::class, [
            'label' => 'Built-in stopwords language',
            'choices' => array_flip((array)$options[static::OPTION_STOPWORDS_BUILTIN_LANGUAGE_CHOICES]),
            'required' => false,
        ]);
        $builder->add(static::FIELD_STOPWORDS_CUSTOM_TEXT, TextareaType::class, [
            'label' => 'Custom stopwords (one per line)',
            'required' => false,
        ]);

        $builder->add(static::FIELD_DECOMPOUND_ENABLED, CheckboxType::class, [
            'label' => 'Enable decompounding',
            'required' => false,
        ]);
        $builder->add(static::FIELD_DECOMPOUND_WORDS_TEXT, TextareaType::class, [
            'label' => 'Decompound word list (one per line)',
            'required' => false,
        ]);
        $builder->add(static::FIELD_DO_NOT_DECOMPOUND_TERMS_TEXT, TextareaType::class, [
            'label' => 'Do-not-decompound / brand terms (one per line)',
            'required' => false,
        ]);
        $builder->add(static::FIELD_SYNONYMS_TEXT, TextareaType::class, [
            'label' => 'Synonyms, Solr-style, one rule per line (e.g. "sofa, couch")',
            'required' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'search_analyzer_config_edit';
    }
}
