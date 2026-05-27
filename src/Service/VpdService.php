<?php

declare(strict_types=1);

namespace App\Service;

/**
 * VpdService — real-time Vapour Pressure Deficit calculation.
 *
 * Commercial differentiator for CultivaTrace: no competitor integrates this natively.
 *
 * Formula:
 *   SVP(T) = 0.6108 × e^(17.27 × T / (T + 237.3))  [kPa]
 *   VPD = SVP(T) × (1 - RH / 100)                   [kPa]
 *
 * Optimal ranges by stage:
 *   Germination / Clone : 0.4 – 0.8 kPa
 *   Vegetation          : 0.8 – 1.2 kPa
 *   Flowering           : 1.0 – 1.5 kPa
 *
 * Reference: "The VPD Chart" — Cultivators Handbook
 */
class VpdService
{
    /**
     * Computes the saturated vapour pressure at a given temperature.
     *
     * @param float $temperatureCelsius Temperature in °C
     * @return float SVP in kPa
     */
    public function computeSvp(float $temperatureCelsius): float
    {
        return 0.6108 * exp(17.27 * $temperatureCelsius / ($temperatureCelsius + 237.3));
    }

    /**
     * Computes the VPD from temperature and relative humidity.
     *
     * @param float $temperatureCelsius Ambient temperature in °C
     * @param float $humidityPercent    Relative humidity in %
     * @return float VPD in kPa (rounded to 2 decimal places)
     */
    public function compute(float $temperatureCelsius, float $humidityPercent): float
    {
        $svp = $this->computeSvp($temperatureCelsius);
        $vpd = $svp * (1 - $humidityPercent / 100);
        return round($vpd, 2);
    }

    /**
     * Evaluates VPD status against optimal ranges.
     *
     * @param float  $vpd   VPD value in kPa
     * @param string $stage Growth stage: germination|vegetation|flowering
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
                'VPD too low (%.2f kPa) — Risk of excess humidity, damping off, botrytis',
                $vpd
            );
        } elseif ($vpd > $max) {
            $status  = 'too_high';
            $message = sprintf(
                'VPD too high (%.2f kPa) — Water stress, stomatal closure, growth slowdown',
                $vpd
            );
        } else {
            $status  = 'optimal';
            $message = sprintf('Optimal VPD (%.2f kPa) — Optimal transpiration and growth', $vpd);
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
     * Computes and evaluates VPD in a single call.
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
