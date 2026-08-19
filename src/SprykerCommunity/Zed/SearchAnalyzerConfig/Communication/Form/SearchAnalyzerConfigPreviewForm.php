<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class SearchAnalyzerConfigPreviewForm extends AbstractType
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
    public const FIELD_TARGET_ANALYZER_NAME = 'targetAnalyzerName';

    /**
     * @var string
     */
    public const FIELD_INPUT_TEXT = 'inputText';

    /**
     * @var string
     */
    public const OPTION_TARGET_ANALYZER_NAME_CHOICES = 'targetAnalyzerNameChoices';

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefined([static::OPTION_TARGET_ANALYZER_NAME_CHOICES]);
        $resolver->setDefaults([static::OPTION_TARGET_ANALYZER_NAME_CHOICES => []]);
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
        $builder->add(static::FIELD_TARGET_ANALYZER_NAME, ChoiceType::class, [
            'label' => 'Target analyzer',
            'choices' => array_combine((array)$options[static::OPTION_TARGET_ANALYZER_NAME_CHOICES], (array)$options[static::OPTION_TARGET_ANALYZER_NAME_CHOICES]),
            'constraints' => [new NotBlank()],
        ]);
        $builder->add(static::FIELD_INPUT_TEXT, TextType::class, [
            'label' => 'Sample search text',
            'constraints' => [new NotBlank()],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'search_analyzer_config_preview';
    }
}
