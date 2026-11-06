<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Resources\Views\Crud\Field;

use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Protung\EasyAdminPlusBundle\Field\JsonField;
use Speicher210\FunctionalTestBundle\Test\Twig\TemplateTestCase;

use function version_compare;

final class JsonFieldTest extends TemplateTestCase
{
    /**
     * @return Generator<string, array<array<mixed>>>
     */
    public static function dataProviderTestOutput(): Generator
    {
        // EasyAdmin 5.8 changed the CSS classes of the badge that the null label renders.
        if (version_compare(EasyAdminBundle::VERSION, '5.8.0', '<')) {
            yield 'no-data' => [[]];
        } else {
            yield 'no-data-since-easyadmin-5.8' => [[]];
        }

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
     */
    #[DataProvider('dataProviderTestOutput')]
    public function testOutput(array $context): void
    {
        $actualTemplate = JsonField::new('test')->getAsDto()->getTemplatePath();

        self::assertNotNull($actualTemplate);

        $this->mockTwigFunction(
            'ea',
            static fn () => new class {
                public function templatePath(): string
                {
                    return '@EasyAdmin/label/null.html.twig';
                }
            },
        );

        $this->assertTwigTemplateEqualsHtmlFile(
            $actualTemplate,
            $context,
        );
    }
}
