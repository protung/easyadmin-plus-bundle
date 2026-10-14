<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Action;

use Override;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Passed to `askConfirmation()`, it gives an action's modal a title and a body, like the delete confirmation's.
 * {@see ConfirmationModalActionsExtension} sends the two to EasyAdmin, which only takes a single message.
 */
final readonly class ConfirmationModal implements TranslatableInterface
{
    public function __construct(
        public TranslatableInterface $title,
        public TranslatableInterface $content,
    ) {
    }

    /** Only shown if the extension did not run (before EasyAdmin 5.7, or for a delete action), as EasyAdmin's single message. */
    #[Override]
    public function trans(TranslatorInterface $translator, string|null $locale = null): string
    {
        return $this->title->trans($translator, $locale) . ' ' . $this->content->trans($translator, $locale);
    }
}
