<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Resources\Views\Crud\Field;

use Generator;
use Protung\EasyAdminPlusBundle\Tests\Test\Resources\Views\TwigTemplateTestCase;

final class JsonFieldTest extends TwigTemplateTestCase
{
    /**
     * @return Generator<string, array<array<mixed>>>
     */
    public static function dataProviderTestOutput(): Generator
    {
        yield 'no-data' => [[]];
        yield 'with-data' => [
            [
                'field' => [
                    'value' => [
                        123456,
                        'string',
                        true,
                        ['array' => 'test'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $context
     *
     * @dataProvider dataProviderTestOutput
     */
    public function testOutput(array $context): void
    {
        $this->assertTwigTemplateEqualsFile(
            $this->getExpectedContentFile('html'),
            '@ProtungEasyAdminPlus/crud/field/json.html.twig',
            $context
        );
    }
}
