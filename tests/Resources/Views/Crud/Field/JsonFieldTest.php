<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Resources\Views\Crud\Field;

use Generator;
use Protung\EasyAdminPlusBundle\Field\JsonField;
use Protung\EasyAdminPlusBundle\Tests\Test\Resources\Views\TwigTemplateTestCase;

final class JsonFieldTest extends TwigTemplateTestCase
{
    /**
     * @return Generator<string, array<array<mixed>>>
     */
    public static function dataProviderTestOutput(): Generator
    {
        yield 'no-data' => [[]];

        yield 'with-value' => [
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

        yield 'with-formatted-value' => [
            [
                'field' => [
                    'formattedValue' => [
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
        $actualTemplate = JsonField::new('test')->getAsDto()->getTemplatePath();

        self::assertNotNull($actualTemplate);

        $this->assertTwigTemplateEqualsFile(
            $this->getExpectedContentFile('html'),
            $actualTemplate,
            $context
        );
    }
}
