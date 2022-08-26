<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\Resources\Views;

use Psl\Type;
use Speicher210\FunctionalTestBundle\Test\KernelTestCase;
use Twig\Environment;

abstract class TwigTemplateTestCase extends KernelTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->twig = Type\instance_of(Environment::class)->coerce($this->getContainerService('twig'));
    }

    /**
     * @param array<mixed> $context
     */
    protected function assertTwigTemplateEqualsString(
        string $expectedOutput,
        string $template,
        array $context = [],
    ): void {
        $template = $this->twig->load($template);

        self::assertEquals(
            $expectedOutput,
            $template->render($context),
        );
    }

    /**
     * @param array<mixed> $context
     */
    protected function assertTwigTemplateEqualsFile(
        string $expectedOutputFile,
        string $template,
        array $context = [],
    ): void {
        $template = $this->twig->load($template);

        self::assertStringEqualsFile(
            $expectedOutputFile,
            $template->render($context),
        );
    }
}
