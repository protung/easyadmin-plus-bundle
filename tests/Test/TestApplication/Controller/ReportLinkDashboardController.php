<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

/**
 * A second dashboard that only has routes for the report links, so their links to the reports go through another dashboard.
 */
#[AdminDashboard(routePath: '/report-links', routeName: 'report_links', allowedControllers: [ReportLinkCrudController::class])]
final class ReportLinkDashboardController extends AbstractDashboardController
{
}
