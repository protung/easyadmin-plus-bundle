<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Action;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Protung\EasyAdminPlusBundle\Action\ConfirmationModalClosureSubscriber;
use Protung\EasyAdminPlusBundle\Test\Controller\AdminControllerWebTestCase;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller\ConfirmationModalCrudController;
use Psl\Type;

use function version_compare;

/**
 * The confirmations as rendered by EasyAdmin, which calls the closures itself.
 *
 * @template-extends AdminControllerWebTestCase<ConfirmationModalCrudController>
 */
#[CoversClass(ConfirmationModalClosureSubscriber::class)]
final class ConfirmationModalClosureSubscriberTest extends AdminControllerWebTestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (version_compare(EasyAdminBundle::VERSION, '5.10.0', '<')) {
            self::markTestSkipped('askConfirmation() takes a closure since EasyAdmin 5.10.');
        }

        parent::setUp();
    }

    #[Override]
    protected static function usePrettyUrls(): bool
    {
        return true;
    }

    #[Override]
    protected function controllerUnderTest(): string
    {
        return ConfirmationModalCrudController::class;
    }

    #[Override]
    protected function actionName(): string
    {
        return Action::NEW;
    }

    public function testClosureReturningAConfirmationModalSendsItAsTitleAndContent(): void
    {
        $this->assertRequestGet();

        self::assertSame(
            [
                'data-action-confirmation' => 'true',
                'data-action-confirmation-title' => 'closure_modal.title',
                'data-action-confirmation-content' => 'closure_modal.content',
                'data-action-confirmation-message' => null,
            ],
            $this->renderedConfirmation('closure_modal'),
        );
        self::assertTrue($this->processedAction('closure_modal')->getConfirmationMessage());
    }

    public function testClosureReturningFalseAsksNoConfirmation(): void
    {
        $this->assertRequestGet();

        self::assertSame(
            [
                'data-action-confirmation' => null,
                'data-action-confirmation-title' => null,
                'data-action-confirmation-content' => null,
                'data-action-confirmation-message' => null,
            ],
            $this->renderedConfirmation('closure_false'),
        );
        self::assertFalse($this->processedAction('closure_false')->getConfirmationMessage());
    }

    public function testConfirmationModalPassedDirectlyIsStillSentAsTitleAndContent(): void
    {
        $this->assertRequestGet();

        self::assertSame(
            [
                'data-action-confirmation' => 'true',
                'data-action-confirmation-title' => 'direct_modal.title',
                'data-action-confirmation-content' => 'direct_modal.content',
                'data-action-confirmation-message' => null,
            ],
            $this->renderedConfirmation('direct_modal'),
        );
        self::assertTrue($this->processedAction('direct_modal')->getConfirmationMessage());
    }

    /** @return array<string, string|null> */
    private function renderedConfirmation(string $actionName): array
    {
        $action = $this->getClient()->getCrawler()->filter('[data-action-name="' . $actionName . '"]');

        self::assertCount(1, $action);

        $attributes = [];
        foreach (['data-action-confirmation', 'data-action-confirmation-title', 'data-action-confirmation-content', 'data-action-confirmation-message'] as $attribute) {
            $attributes[$attribute] = $action->attr($attribute);
        }

        return $attributes;
    }

    private function processedAction(string $actionName): ActionDto
    {
        return Type\instance_of(ActionDto::class)->coerce(
            $this->getAdminContextFromLastRequest()->getEntity()->getActions()->get($actionName),
        );
    }
}
