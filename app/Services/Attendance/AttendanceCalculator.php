<?php

namespace App\Services\Attendance;

class AttendanceCalculator
{
    public const VERSION = 'office-v1';

    /**
     * Deterministic minutes, Manila calendar day; no leave/credit side effects.
     *
     * @param  array<string, mixed>  $punches
     * @return array<string, int|bool|null>
     */
    public function calculate(array $punches, ?int $workweek, bool $wfh = false, ?string $suspension = null): array
    {
        $start = $this->minute($punches['time_in'] ?? $punches['lunch_in'] ?? null);
        $end = $this->minute($punches['time_out'] ?? $punches['lunch_out'] ?? null);
        $result = ['worked_minutes' => null, 'late_minutes' => null, 'undertime_minutes' => null,
            'overtime_minutes' => null, 'needs_review' => true];
        if ($start === null || $end === null || $end <= $start) {
            return $result;
        }
        $required = $workweek === 4 ? 600 : 480;
        $arrival = $wfh ? 420 : 540;
        $exit = $wfh ? ($workweek === 4 ? 1080 : 960) : ($workweek === 4 ? 1200 : 1080);
        $suspensionMinute = $this->minute($suspension);
        if ($suspensionMinute !== null) {
            $exit = min($exit, $suspensionMinute);
        }
        $start = max(420, $start);
        $end = min($end, $exit);
        $lunchOut = $this->minute($punches['lunch_out'] ?? null);
        $lunchIn = $this->minute($punches['lunch_in'] ?? null);
        $morningEnd = min($end, 720, $lunchOut ?? 720);
        $afternoonStart = max($start, 780, $lunchIn ?? 780);
        $worked = max(0, $morningEnd - $start) + max(0, $end - $afternoonStart);
        $late = $suspensionMinute !== null && $suspensionMinute <= $arrival ? 0 : $this->workingMinutes($arrival, $start);
        $undertime = $suspensionMinute !== null || (! $wfh && $start > $arrival)
            ? $this->workingMinutes($end, $exit) : max(0, $required - $worked);
        $otIn = $this->minute($punches['ot_in'] ?? null);
        $otOut = $this->minute($punches['ot_out'] ?? null);
        $complete = ! empty($punches['time_in']) && ! empty($punches['time_out']) && $workweek !== null;

        return ['worked_minutes' => $worked, 'late_minutes' => $workweek === null ? null : $late,
            'undertime_minutes' => $workweek === null ? null : $undertime,
            'overtime_minutes' => $otIn !== null && $otOut !== null ? min(120, max(0, $otOut - $otIn)) : null,
            'needs_review' => ! $complete];
    }

    private function minute(?string $time): ?int
    {
        if ($time === null || ! preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/D', $time, $match)) {
            return null;
        }

        return ((int) $match[1]) * 60 + (int) $match[2];
    }

    private function workingMinutes(int $start, int $end): int
    {
        if ($end <= $start) {
            return 0;
        }

        return max(0, $end - $start - max(0, min($end, 780) - max($start, 720)));
    }
}
