<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Action;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionGroupDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Protung\EasyAdminPlusBundle\Action\ActionsWalker;

#[CoversClass(ActionsWalker::class)]
final class ActionsWalkerTest extends TestCase
{
    public function testApplyToAllActionsPassesEveryPagesTopLevelActions(): void
    {
        $action = Action::new('action')->linkToCrudAction('action');
        $group  = ActionGroup::new('group')->addAction(Action::new('grouped_action')->linkToCrudAction('groupedAction'));

        $actions = Actions::new()
            ->add(Crud::PAGE_DETAIL, $action)
            ->add(Crud::PAGE_INDEX, $group);

        $applied = [];
        $result  = ActionsWalker::applyToAllActions(
            $actions,
            static function (ActionDto|ActionGroupDto $actionDto) use (&$applied): void {
                $applied[] = $actionDto;
            },
        );

        self::assertSame($actions, $result);
        self::assertSame([$action->getAsDto(), $group->getAsDto()], $applied);
    }
}
