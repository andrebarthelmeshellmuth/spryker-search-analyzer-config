<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SprykerCommunity\Zed\SearchAnalyzerConfig\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Two hidden fields (sourceIdentifier, storeName) carrying which scope a row/page action button applies
 * to -- the Apply-rebuild button on the Overview page. Symfony's default CSRF extension attaches the
 * `_token` field automatically; the controller's own `isValid()` check is what actually enforces it.
 */
class SearchAnalyzerConfigScopeForm extends AbstractType
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
    public const FIELD_REDIRECT_TO = 'redirectTo';

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
        $builder->add(static::FIELD_REDIRECT_TO, HiddenType::class, [
            'required' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'search_analyzer_config_scope';
    }
}
