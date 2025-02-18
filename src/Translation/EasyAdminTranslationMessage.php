<?php

declare(strict_types=1);

namespace Protung\EasyAdminPlusBundle\Translation;

use Override;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @psalm-immutable
 */
final class EasyAdminTranslationMessage implements TranslatableInterface
{
    private const EASY_ADMIN_TRANSLATION_DOMAIN = 'EasyAdminBundle';

    private TranslatableMessage $message;

    /**
     * @param array<string, mixed> $parameters
     */
    private function __construct(string $message, array $parameters)
    {
        $this->message = new TranslatableMessage($message, $parameters, self::EASY_ADMIN_TRANSLATION_DOMAIN);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function for(string $message, array $parameters = []): self
    {
        return new self($message, $parameters);
    }

    #[Override]
    public function trans(TranslatorInterface $translator, string|null $locale = null): string
    {
        return $this->message->trans($translator, $locale);
    }
}
