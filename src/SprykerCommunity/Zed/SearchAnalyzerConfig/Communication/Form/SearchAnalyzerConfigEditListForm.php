<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * One of this package's four term lists (synonyms, decompound words, custom stopwords, do-not-decompound)
 * -- which one is fixed for a given form instance via the `label` option threaded in by
 * `SearchAnalyzerConfigCommunicationFactory::createSearchAnalyzerConfigEditListForm()`, keyed off
 * `SprykerCommunity\Shared\SearchAnalyzerConfig\SearchAnalyzerConfigConfig::LIST_TYPES`. Deliberately a
 * SINGLE reusable form class rather than four near-identical ones -- the only thing that differs between
 * them is the label and which transfer field the controller reads/writes, not the shape.
 *
 * Lives on its own screen, separate from SearchAnalyzerConfigEditForm's scalar fields, because a term list
 * is shared across the whole store/locale scope, not tied to whichever target analyzer happens to be
 * selected in that page's analyzer picker -- see that form's own doc block.
 */
class SearchAnalyzerConfigEditListForm extends AbstractType
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
    public const FIELD_LIST_TYPE = 'listType';

    /**
     * @var string
     */
    public const FIELD_TEXT = 'text';

    /**
     * @var string
     */
    public const OPTION_LABEL = 'textLabel';

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefined([static::OPTION_LABEL]);
        $resolver->setDefaults([static::OPTION_LABEL => 'Terms (one per line)']);
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
        $builder->add(static::FIELD_LIST_TYPE, HiddenType::class, [
            'constraints' => [new NotBlank()],
        ]);
        $builder->add(static::FIELD_TEXT, TextareaType::class, [
            'label' => (string)$options[static::OPTION_LABEL],
            'required' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'search_analyzer_config_edit_list';
    }
}
