<?php

namespace App\Http\Services;

use Phpml\Classification\KNearestNeighbors;
use Phpml\Clustering\KMeans;
use Throwable;

class AdvancedMLService
{
    /**
     * @return array{label:string, risk_score:float}
     */
    public function classifySpendingProfile(float $foodRatio, float $entertainmentRatio, float $savingsRate): array
    {
        $foodRatio = $this->clamp01($foodRatio);
        $entertainmentRatio = $this->clamp01($entertainmentRatio);
        $savingsRate = $this->clamp01($savingsRate);

        $samples = [
            [0.12, 0.08, 0.42],
            [0.16, 0.09, 0.38],
            [0.24, 0.14, 0.30],
            [0.30, 0.19, 0.24],
            [0.36, 0.24, 0.16],
            [0.44, 0.28, 0.10],
        ];
        $labels = [
            'low-risk',
            'low-risk',
            'medium-risk',
            'medium-risk',
            'high-risk',
            'high-risk',
        ];

        $label = 'medium-risk';

        try {
            $classifier = new KNearestNeighbors(3);
            $classifier->train($samples, $labels);
            $prediction = $classifier->predict([$foodRatio, $entertainmentRatio, $savingsRate]);
            $label = is_string($prediction) && $prediction !== '' ? $prediction : $label;
        } catch (Throwable $exception) {
            $label = $this->fallbackLabel($foodRatio, $entertainmentRatio, $savingsRate);
        }

        $riskScore = round(($foodRatio * 0.45) + ($entertainmentRatio * 0.35) + ((1 - $savingsRate) * 0.20), 3);

        return [
            'label' => $label,
            'risk_score' => $this->clamp01($riskScore),
        ];
    }

    /**
     * @param array<int, float|int> $amounts
     * @return array<int, array{size:int, avg:float, min:float, max:float}>
     */
    public function clusterAmounts(array $amounts, int $clusters = 3): array
    {
        $samples = [];
        foreach ($amounts as $amount) {
            $numeric = (float) $amount;
            if ($numeric <= 0) {
                continue;
            }
            $samples[] = [$numeric];
        }

        if (count($samples) < 3) {
            return [];
        }

        $clusterCount = max(2, min($clusters, 5));

        try {
            $kmeans = new KMeans($clusterCount);
            $result = $kmeans->cluster($samples);
        } catch (Throwable $exception) {
            return [];
        }

        $summary = [];

        foreach ($result as $clusterRows) {
            $values = array_map(
                fn (array $row) => (float) ($row[0] ?? 0),
                $clusterRows
            );

            if (empty($values)) {
                continue;
            }

            sort($values);

            $summary[] = [
                'size' => count($values),
                'avg' => round(array_sum($values) / count($values), 2),
                'min' => (float) $values[0],
                'max' => (float) $values[count($values) - 1],
            ];
        }

        return $summary;
    }

    private function fallbackLabel(float $foodRatio, float $entertainmentRatio, float $savingsRate): string
    {
        if ($foodRatio >= 0.35 || $entertainmentRatio >= 0.22 || $savingsRate < 0.18) {
            return 'high-risk';
        }

        if ($foodRatio <= 0.18 && $entertainmentRatio <= 0.12 && $savingsRate >= 0.32) {
            return 'low-risk';
        }

        return 'medium-risk';
    }

    private function clamp01(float $value): float
    {
        return max(0, min(1, $value));
    }
}
