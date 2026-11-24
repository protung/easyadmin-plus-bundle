<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use Override;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Dto\NoteDto;
use Symfony\Component\Form\FormInterface;

/**
 * Notes whose new form ignores the DTO and has no data after submitting it.
 */
final class EmptyFormNoteCrudController extends NoteCrudController
{
    #[Override]
    public function createNewForm(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormInterface
    {
        return $this->createFormBuilder(null, ['data_class' => NoteDto::class, 'empty_data' => null, 'csrf_protection' => false])
            ->add('title')
            ->getForm();
    }
}
