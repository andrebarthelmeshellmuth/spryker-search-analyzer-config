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
 * The five independently-editable SCALAR fields (a deliberate design decision: five independent fields,
 * not a single combined Language selector), plus FIELD_LIST_TEXT for the ONE term
 * list that belongs to whichever scalar filter this form instance is built for (stopwords/decompound only
 * -- synonyms and do-not-decompound have no scalar counterpart at all, so they stay on
 * SearchAnalyzerConfigEditListForm's own dedicated screen instead).
 *
 * Each scalar field gets its own small edit page (ConfigController::editFilterAction(), one per
 * `SearchAnalyzerConfigConfig::FILTER_TYPES` entry) rather than one combined page -- OPTION_FIELDS lets a
 * single reusable form class build with only the field(s) relevant to one filter row (stemmer:
 * FIELD_STEMMER_LANGUAGE alone; stopwords: FIELD_STOPWORDS_MODE + FIELD_STOPWORDS_BUILTIN_LANGUAGE +
 * FIELD_LIST_TEXT together, since a user edits all three as one decision), instead of four near-identical
 * form classes.
 *
 * `stemmerLanguageChoices`/`normalizationFilterChoices`/`stopwordsBuiltinLanguageChoices`/`listTextLabel`
 * are threaded in as form options (built from `SearchAnalyzerConfigConfig::getAllowed*()` by the
 * CommunicationFactory) rather than read from a hardcoded list here, so a project's own config override is
 * reflected without touching this class.
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
    public const FIELD_DECOMPOUND_ENABLED = 'decompoundEnabled';

    /**
     * The one term list belonging to whichever filter this form instance is built for (stopwords ->
     * custom stopword list, decompound -> decompound word list) -- added only when that filter's own
     * OPTION_FIELDS includes it, so its own small page can save the scalar field AND its list together in
     * one submit, exactly like the synonym/do-not-decompound rows already are one page with nothing else
     * to combine it with.
     *
     * @var string
     */
    public const FIELD_LIST_TEXT = 'listText';

    /**
     * Set only by the "Save anyway" button rendered alongside a missing-slot warning banner -- tells
     * the controller the user has already seen and confirmed past those warnings once, so this exact
     * submit shouldn't re-trigger the warning gate. See ConfigController::editAction().
     *
     * @var string
     */
    public const FIELD_CONFIRMED = 'confirmed';

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
     * Label for FIELD_LIST_TEXT -- differs per filter (custom stopwords vs. decompound words), so it's
     * threaded in rather than hardcoded here, same reasoning as the *_CHOICES options above.
     *
     * @var string
     */
    public const OPTION_LIST_TEXT_LABEL = 'listTextLabel';

    /**
     * Which of the FIELD_* constants above to actually add -- see this class's own doc block.
     *
     * @var string
     */
    public const OPTION_FIELDS = 'fields';

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
            static::OPTION_LIST_TEXT_LABEL,
            static::OPTION_FIELDS,
        ]);
        $resolver->setDefaults([
            static::OPTION_STEMMER_LANGUAGE_CHOICES => [],
            static::OPTION_NORMALIZATION_FILTER_CHOICES => [],
            static::OPTION_STOPWORDS_BUILTIN_LANGUAGE_CHOICES => [],
            static::OPTION_LIST_TEXT_LABEL => 'Terms (one per line)',
            static::OPTION_FIELDS => [
                static::FIELD_STEMMER_LANGUAGE,
                static::FIELD_NORMALIZATION_FILTER,
                static::FIELD_STOPWORDS_MODE,
                static::FIELD_STOPWORDS_BUILTIN_LANGUAGE,
                static::FIELD_DECOMPOUND_ENABLED,
            ],
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
        $builder->add(static::FIELD_CONFIRMED, HiddenType::class, [
            'data' => '',
        ]);

        /** @var array<string> $fields */
        $fields = $options[static::OPTION_FIELDS];

        if (in_array(static::FIELD_STEMMER_LANGUAGE, $fields, true)) {
            $builder->add(static::FIELD_STEMMER_LANGUAGE, ChoiceType::class, [
                'label' => 'Stemmer language',
                'choices' => array_flip((array)$options[static::OPTION_STEMMER_LANGUAGE_CHOICES]),
                'required' => false,
            ]);
        }

        if (in_array(static::FIELD_NORMALIZATION_FILTER, $fields, true)) {
            $builder->add(static::FIELD_NORMALIZATION_FILTER, ChoiceType::class, [
                'label' => 'Normalization filter',
                'choices' => array_flip((array)$options[static::OPTION_NORMALIZATION_FILTER_CHOICES]),
                'required' => false,
            ]);
        }

        if (in_array(static::FIELD_STOPWORDS_MODE, $fields, true)) {
            $builder->add(static::FIELD_STOPWORDS_MODE, ChoiceType::class, [
                'label' => 'Stopwords mode',
                'choices' => [
                    'None' => SearchAnalyzerConfigConfig::STOPWORDS_MODE_NONE,
                    'Built-in language' => SearchAnalyzerConfigConfig::STOPWORDS_MODE_BUILTIN,
                    'Custom list' => SearchAnalyzerConfigConfig::STOPWORDS_MODE_CUSTOM,
                ],
                'expanded' => true,
            ]);
        }

        if (in_array(static::FIELD_STOPWORDS_BUILTIN_LANGUAGE, $fields, true)) {
            $builder->add(static::FIELD_STOPWORDS_BUILTIN_LANGUAGE, ChoiceType::class, [
                'label' => 'Built-in stopwords language',
                'choices' => array_flip((array)$options[static::OPTION_STOPWORDS_BUILTIN_LANGUAGE_CHOICES]),
                'required' => false,
            ]);
        }

        if (in_array(static::FIELD_DECOMPOUND_ENABLED, $fields, true)) {
            $builder->add(static::FIELD_DECOMPOUND_ENABLED, CheckboxType::class, [
                'label' => 'Enable decompounding',
                'required' => false,
            ]);
        }

        if (!in_array(static::FIELD_LIST_TEXT, $fields, true)) {
            return;
        }

        $builder->add(static::FIELD_LIST_TEXT, TextareaType::class, [
            'label' => (string)$options[static::OPTION_LIST_TEXT_LABEL],
            'required' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'search_analyzer_config_edit';
    }
}
