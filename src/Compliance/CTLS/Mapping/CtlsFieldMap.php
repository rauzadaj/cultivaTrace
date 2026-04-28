<?php

namespace App\Compliance\CTLS\Mapping;

final class CtlsFieldMap
{
    /**
     * @return string[]
     */
    public function getExpectedSourceHeaders(): array
    {
        return [
            'Report Period',
            'Plant ID',
            'Strain',
            'Cannabis Type',
            'Stage',
            'Status',
            'Germinated At',
            'Room',
            'Harvested At',
            'Gross Weight (g)',
            'Net Weight (g)',
            'Destroyed At',
            'Destruction Reason',
            'Quarantined',
            'Notes',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function getDirectMappings(): array
    {
        return [
            'Report Period' => [
                'Reporting Period Year (####)',
                'Reporting Period Month (##)',
            ],
        ];
    }

    /**
     * Source fields that must never be injected directly into the CTLS file.
     *
     * @return array<string, string>
     */
    public function getUnsupportedSourceFieldReasons(): array
    {
        return [
            'Plant ID' => 'CTLS monthly reports are aggregate reports; an internal plant identifier cannot be exported as a regulatory identifier.',
            'Strain' => 'The official CTLS template has no direct strain column; product-class aggregation and regulatory mapping are required.',
            'Cannabis Type' => 'The official CTLS template expects aggregated product classes and movements, not a free-form plant cannabis type.',
            'Stage' => 'Internal cultivation stages do not map deterministically to CTLS inventory classes without a formal regulatory crosswalk.',
            'Status' => 'Internal statuses do not map deterministically to CTLS inventory movement columns.',
            'Germinated At' => 'Plant germination dates do not directly populate CTLS aggregate movement columns without classification rules.',
            'Room' => 'The official CTLS template has no free-text room column.',
            'Harvested At' => 'Harvest dates must feed aggregate movement quantities, not a plant-level date field.',
            'Gross Weight (g)' => 'Gross weight cannot be injected directly; CTLS expects class-specific aggregate quantities and units.',
            'Net Weight (g)' => 'Net weight cannot be injected directly; CTLS expects class-specific aggregate quantities and units.',
            'Destroyed At' => 'Destruction dates do not map to a CTLS date column in the official template.',
            'Destruction Reason' => 'The official CTLS template has no free-text destruction reason column.',
            'Quarantined' => 'The official CTLS template has no direct quarantine flag column.',
            'Notes' => 'The official CTLS template has no free-text notes column.',
        ];
    }
}
