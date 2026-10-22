<?php

namespace App\Services\Performance;

use App\Models\Ipcr;

class IpcrRatingCalculator
{
    /** @return array{overall: float, categories: array<int, float>, description: string} */
    public function calculate(Ipcr $ipcr): array
    {
        $ipcr->loadMissing('outputs.indicators.accomplishments');
        $categories = [];
        foreach ([1, 2, 3] as $category) {
            $scores = [];
            foreach ($ipcr->outputs->where('category', $category) as $output) {
                foreach ($output->indicators as $indicator) {
                    foreach ($indicator->accomplishments as $accomplishment) {
                        $components = collect([$accomplishment->quality, $accomplishment->efficiency, $accomplishment->timeliness])->filter(fn ($value) => $value !== null);
                        if ($components->isNotEmpty()) {
                            $scores[] = $components->avg();
                        }
                    }
                }
            }
            $categories[$category] = $scores === [] ? 0.0 : round(array_sum($scores) / count($scores), 2);
        }

        $overall = round($categories[1] * .4 + $categories[2] * .4 + $categories[3] * .2, 2);

        return ['overall' => $overall, 'categories' => $categories, 'description' => $this->description($overall)];
    }

    public function description(float $rating): string
    {
        return match (true) {
            $rating === 5.0 => 'Outstanding',
            $rating >= 4 => 'Very Satisfactory',
            $rating >= 3 => 'Satisfactory',
            $rating >= 2 => 'Unsatisfactory',
            default => 'Poor',
        };
    }
}
