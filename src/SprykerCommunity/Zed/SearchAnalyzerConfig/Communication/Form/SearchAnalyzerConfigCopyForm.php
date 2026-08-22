<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * A hard override of the target scope, no merge semantics -- see `SearchAnalyzerConfigCopier`'s own doc
 * block. The confirm-screen template surfaces exactly what the copy would overwrite before this ever
 * submits.
 */
class SearchAnalyzerConfigCopyForm extends AbstractType
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
    public const FIELD_TARGET_SOURCE_IDENTIFIER = 'targetSourceIdentifier';

    /**
     * @var string
     */
    public const FIELD_TARGET_STORE_NAME = 'targetStoreName';

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
        $builder->add(static::FIELD_TARGET_SOURCE_IDENTIFIER, TextType::class, [
            'label' => 'Target sourceIdentifier',
            'constraints' => [new NotBlank()],
        ]);
        $builder->add(static::FIELD_TARGET_STORE_NAME, TextType::class, [
            'label' => 'Target store',
            'constraints' => [new NotBlank()],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'search_analyzer_config_copy';
    }
}
