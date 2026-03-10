<?php

namespace App\Domain\Cultivation\Exception;

use App\Domain\Cultivation\Enum\CropStage;
use DomainException;

final class InvalidCropStageTransition extends DomainException
{
    public static function between(CropStage $from, CropStage $to): self
    {
        return new self(sprintf('Invalid crop stage transition from "%s" to "%s".', $from->value, $to->value));
    }
}
