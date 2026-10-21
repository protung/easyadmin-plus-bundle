<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Override;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @template-extends AbstractCrudController<Report>
 */
final class ReportCrudController extends AbstractCrudController
{
    public const int LATEST_REPORT_ID = 7;

    public const string MAIN_TENANT = 'www';

    #[Override]
    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    /**
     * An action with a placeholder of its own in its route, next to the entity ID.
     */
    #[AdminRoute(path: '/{entityId}/download/{kind}', name: 'download')]
    public function download(string $kind): Response
    {
        return new Response($kind);
    }

    /**
     * An action without an entity that redirects to another action with route placeholders.
     */
    #[AdminRoute(path: '/latest/{kind}', name: 'latest')]
    public function latest(string $kind, Request $request): RedirectResponse
    {
        return $this->redirect(
            $this->generateUrl(
                'admin_report_download',
                [EA::ENTITY_ID => self::LATEST_REPORT_ID, 'kind' => $kind, ...$request->query->all()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        );
    }

    /**
     * An action that redirects to the admin of another tenant, which is served from another host in the tenant environment.
     */
    #[AdminRoute(path: '/main-tenant-latest/{kind}', name: 'main_tenant_latest')]
    public function mainTenantLatest(string $kind): RedirectResponse
    {
        return $this->redirect(
            $this->generateUrl(
                'admin_report_download',
                ['tenant' => self::MAIN_TENANT, EA::ENTITY_ID => self::LATEST_REPORT_ID, 'kind' => $kind],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        );
    }
}
