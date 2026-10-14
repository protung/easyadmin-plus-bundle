<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Protung\EasyAdminPlusBundle\Action\ConfirmationModal;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatableMessage;

#[CoversClass(ConfirmationModal::class)]
final class ConfirmationModalTest extends TestCase
{
    public function testTransJoinsTitleAndContent(): void
    {
        $confirmationModal = new ConfirmationModal(
            new TranslatableMessage('The title.'),
            new TranslatableMessage('The content.'),
        );

        self::assertSame('The title. The content.', $confirmationModal->trans(new IdentityTranslator()));
    }
}
