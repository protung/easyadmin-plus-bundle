<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Resources\Views\Crud\Field;

use Generator;
use Protung\EasyAdminPlusBundle\Field\JsonField;
use Speicher210\FunctionalTestBundle\Test\Twig\TemplateTestCase;

final class JsonFieldTest extends TemplateTestCase
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

        yield 'with-empty-array-value' => [
            [
                'field' => [
                    'value' => [],
                ],
            ],
        ];

        yield 'with-empty-array-formatted-value' => [
            [
                'field' => [
                    'formattedValue' => [],
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

        $this->assertTwigTemplateEqualsHtmlFile(
            $actualTemplate,
            $context,
        );
    }
}
