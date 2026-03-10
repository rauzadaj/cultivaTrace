<?php

namespace App\Domain\Cultivation\Enum;

enum JournalEntryType: string
{
    case Irrigation = 'irrigation';
    case Fertilization = 'fertilization';
    case EnvironmentCheck = 'environment_check';
    case StageTransition = 'stage_transition';
    case Observation = 'observation';
}
