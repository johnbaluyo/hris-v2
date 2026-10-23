<?php

namespace App\Services\Certificates;

class CertificateAmounts
{
    public const FIELDS = ['basic_pay', 'pera', 'mid_year', 'year_end', 'cna', 'sri', 'clothing', 'medical', 'pei', 'cash_gift'];

    /**
     * @param  array<string, string|int|float>  $amounts
     * @return array<string, mixed>
     */
    public function calculate(array $amounts): array
    {
        $cents = [];
        foreach (self::FIELDS as $field) {
            $parts = explode('.', (string) ($amounts[$field] ?? '0'));
            $cents[$field] = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
        }
        $annual = $cents['basic_pay'] + $cents['pera'];
        $benefits = array_sum($cents) - $annual;

        return ['compensation' => $cents, 'annual_total_cents' => $annual, 'benefit_total_cents' => $benefits, 'grand_total_cents' => $annual + $benefits];
    }
}
