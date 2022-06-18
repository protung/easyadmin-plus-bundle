<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Translation;

use PHPUnit\Framework\TestCase;
use Protung\EasyAdminPlusBundle\Translation\EasyAdminTranslationMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EasyAdminTranslationMessageTest extends TestCase
{
    public function testTranslate(): void
    {
        $translatorMock = $this->createMock(TranslatorInterface::class);
        $translatorMock->expects(self::once())
            ->method('trans')
            ->with('test', ['param' => true])
            ->willReturn('translated message');

        $message = EasyAdminTranslationMessage::for('test', ['param' => true]);
        $actual  = $message->trans($translatorMock, 'ro');

        self::assertSame('translated message', $actual);
    }
}
