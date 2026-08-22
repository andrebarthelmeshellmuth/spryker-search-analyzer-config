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

class SearchAnalyzerConfigRestoreForm extends AbstractType
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
    public const FIELD_REVISION = 'revision';

    /**
     * Empty (the default) means "redirect back to History" -- History's own Restore buttons never set
     * this. The Edit page's "Reset changes" button (same form, same restoreAction(), just aimed at
     * `appliedRevision` instead of a user-picked row) sets it back to the Edit page instead, via the same
     * AbstractScopeController::resolveRedirectUrl() safety check SearchAnalyzerConfigScopeForm's own
     * redirectTo already uses.
     *
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
        $builder->add(static::FIELD_REVISION, HiddenType::class, [
            'constraints' => [new NotBlank()],
        ]);
        $builder->add(static::FIELD_REDIRECT_TO, HiddenType::class, [
            'required' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'search_analyzer_config_restore';
    }
}
