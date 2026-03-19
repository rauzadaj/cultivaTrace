<?php

namespace App\Service;

/**
 * VpdService — calcul du Vapour Pressure Deficit en temps réel.
 *
 * Différenciateur commercial CannaSaaS : aucun concurrent ne l'intègre nativement.
 *
 * Formule :
 *   SVP(T) = 0.6108 × e^(17.27 × T / (T + 237.3))  [kPa]
 *   VPD = SVP(T) × (1 - HR / 100)                   [kPa]
 *
 * Plages optimales par stade :
 *   Germination / Bouture : 0.4 – 0.8 kPa
 *   Végétation            : 0.8 – 1.2 kPa
 *   Floraison             : 1.0 – 1.5 kPa
 *
 * Référence : "The VPD Chart" — Cultivators Handbook
 */
class VpdService
{
    /**
     * Calcule la pression de vapeur saturante à une température donnée.
     *
     * @param float $temperatureCelsius Température en °C
     * @return float SVP en kPa
     */
    public function computeSvp(float $temperatureCelsius): float
    {
        return 0.6108 * exp(17.27 * $temperatureCelsius / ($temperatureCelsius + 237.3));
    }

    /**
     * Calcule le VPD à partir de la température et de l'humidité relative.
     *
     * @param float $temperatureCelsius Température ambiante en °C
     * @param float $humidityPercent    Humidité relative en %
     * @return float VPD en kPa (arrondi à 2 décimales)
     */
    public function compute(float $temperatureCelsius, float $humidityPercent): float
    {
        $svp = $this->computeSvp($temperatureCelsius);
        $vpd = $svp * (1 - $humidityPercent / 100);
        return round($vpd, 2);
    }

    /**
     * Évalue le statut du VPD par rapport aux plages optimales.
     *
     * @param float  $vpd   Valeur VPD en kPa
     * @param string $stage Stade de croissance : germination|vegetation|flowering
     * @return array{status: string, message: string, optimal_min: float, optimal_max: float}
     */
    public function evaluate(float $vpd, string $stage = 'vegetation'): array
    {
        [$min, $max] = match ($stage) {
            'germination' => [0.4, 0.8],
            'vegetation'  => [0.8, 1.2],
            'flowering'   => [1.0, 1.5],
            default       => [0.8, 1.2],
        };

        if ($vpd < $min) {
            $status  = 'too_low';
            $message = sprintf(
                'VPD trop faible (%.2f kPa) — Risque d\'excès d\'humidité, fonte des semis, botrytis',
                $vpd
            );
        } elseif ($vpd > $max) {
            $status  = 'too_high';
            $message = sprintf(
                'VPD trop élevé (%.2f kPa) — Stress hydrique, fermeture des stomates, ralentissement de croissance',
                $vpd
            );
        } else {
            $status  = 'optimal';
            $message = sprintf('VPD optimal (%.2f kPa) — Transpiration et croissance optimales', $vpd);
        }

        return [
            'status'      => $status,
            'message'     => $message,
            'vpd'         => $vpd,
            'optimal_min' => $min,
            'optimal_max' => $max,
            'stage'       => $stage,
        ];
    }

    /**
     * Calcule et évalue le VPD en une seule appel.
     */
    public function computeAndEvaluate(
        float  $temperatureCelsius,
        float  $humidityPercent,
        string $stage = 'vegetation'
    ): array {
        $vpd = $this->compute($temperatureCelsius, $humidityPercent);
        return $this->evaluate($vpd, $stage);
    }
}
