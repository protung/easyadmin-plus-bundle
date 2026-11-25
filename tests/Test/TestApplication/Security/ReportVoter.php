<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Security;

use Override;
use Protung\EasyAdminPlusBundle\Tests\Test\TestApplication\Entity\Report;
use Psl\Iter;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Grants access to a report, so not to no report at all.
 */
final class ReportVoter implements VoterInterface
{
    public const string ACCESS = 'REPORT_ACCESS';

    /** @param array<mixed> $attributes */
    #[Override]
    public function vote(#[SensitiveParameter]
    TokenInterface $token, mixed $subject, array $attributes, mixed $vote = null,): int
    {
        if (! Iter\contains($attributes, self::ACCESS)) {
            return self::ACCESS_ABSTAIN;
        }

        return $subject instanceof Report ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
    }
}
