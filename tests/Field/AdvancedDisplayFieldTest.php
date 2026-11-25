<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Protung\EasyAdminPlusBundle\Field\AdvancedDisplayField;
use Protung\EasyAdminPlusBundle\Field\EntityField;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[CoversTrait(AdvancedDisplayField::class)]
final class AdvancedDisplayFieldTest extends TestCase
{
    public function testStringIsAPropertyPathEvenIfItMatchesTheNameOfAPhpFunction(): void
    {
        $entity = new class {
            public string $date = '2026-11-25';
        };

        self::assertSame('2026-11-25', $this->format(EntityField::new('report')->setEntityDisplayField('date'), $entity));
    }

    public function testClosureIsCalledWithTheEntity(): void
    {
        $report     = new Report();
        $report->id = 1;

        self::assertSame(
            'Report number 1',
            $this->format(EntityField::new('report')->setEntityDisplayField(static fn (Report $report): string => 'Report number ' . $report->id), $report),
        );
    }

    private function format(EntityField $field, object $entity): string|null
    {
        return EntityField::formatAsString(
            $entity,
            $field->getAsDto(),
            self::createStub(TranslatorInterface::class),
            self::createStub(Environment::class),
            self::createStub(AdminContextInterface::class),
        );
    }
}
