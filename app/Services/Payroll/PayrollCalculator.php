<?php

namespace App\Services\Payroll;

class PayrollCalculator
{
    /** @param array<string, int|float|string|null> $input
     * @return array{gross: string, total_deductions: string, net: string, weekly: array<int, string>}
     */
    public function regular(array $input): array
    {
        $gross = $this->money((float) ($input['basic_salary'] ?? 0) + (float) ($input['pera'] ?? 0) + (float) ($input['rata'] ?? 0) + (float) ($input['other_compensation'] ?? 0));
        $deductions = $this->money((float) ($input['statutory_deductions'] ?? 0) + (float) ($input['other_deductions'] ?? 0));
        $net = $this->money((float) $gross - (float) $deductions);
        $base = $this->money((float) $net - (float) ($input['pera'] ?? 0) - (float) ($input['rata'] ?? 0));
        $weekly = floor(((float) $base / 4) * 100) / 100;
        $remainder = $this->money((float) $base - ($weekly * 4));

        return ['gross' => $gross, 'total_deductions' => $deductions, 'net' => $net, 'weekly' => [
            $this->money($weekly + (float) ($input['rata'] ?? 0)), $this->money($weekly), $this->money($weekly),
            $this->money($weekly + (float) ($input['pera'] ?? 0) + (float) $remainder),
        ]];
    }

    private function money(float|int|string $amount): string
    {
        return number_format(round((float) $amount, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }
}
