<?php

declare(strict_types=1);

namespace LiebAbstractSite\Model;

use LesValueObject\Enum\EnumValueObject;
use LesValueObject\Enum\Helper\EnumValueHelper;

/**
 * @psalm-immutable
 */
enum Platform: string implements EnumValueObject
{
    use EnumValueHelper;

    case BoekBord = 'BoekBord';
    case GeleefdVerhaal = 'GeleefdVerhaal';

    /**
     * @psalm-mutation-free
     */
    public function getBaseUrl(): string
    {
        return match ($this) {
            self::BoekBord => 'boekbord.nl',
            self::GeleefdVerhaal => 'geleefdverhaal.nl',
        };
    }
}
