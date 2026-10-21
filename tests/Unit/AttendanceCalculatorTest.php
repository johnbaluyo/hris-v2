<?php

namespace Tests\Unit;

use App\Services\Attendance\AttendanceCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    /** @return array<string, array{string, string, int, bool, int, int, int}> */
    public static function boundaries(): array
    {
        return [
            'eight hour office' => ['08:00:59', '17:00:59', 5, false, 480, 0, 0],
            'flex arrival' => ['09:00:00', '18:00:00', 5, false, 480, 0, 0],
            'late office' => ['09:30:00', '18:00:00', 5, false, 450, 30, 0],
            'late and short exit' => ['10:00:00', '17:00:00', 5, false, 360, 60, 60],
            'early clock clamps' => ['06:00:00', '16:00:00', 5, false, 480, 0, 0],
            'ten hour office' => ['07:00:00', '18:00:00', 4, false, 600, 0, 0],
            'eight hour wfh' => ['07:00:00', '16:00:00', 5, true, 480, 0, 0],
            'late wfh' => ['08:00:00', '16:00:00', 5, true, 420, 60, 60],
            'ten hour wfh' => ['07:00:00', '18:00:00', 4, true, 600, 0, 0],
            'noon boundary' => ['12:00:00', '18:00:00', 5, false, 300, 180, 0],
            'lunch arrival excludes lunch' => ['12:30:00', '18:00:00', 5, false, 300, 180, 0],
            'afternoon boundary' => ['13:00:00', '18:00:00', 5, false, 300, 180, 0],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_documented_minute_boundaries(string $start, string $end, int $week, bool $wfh, int $worked, int $late, int $undertime): void
    {
        $result = (new AttendanceCalculator)->calculate(['time_in' => $start, 'time_out' => $end], $week, $wfh);
        $this->assertSame($worked, $result['worked_minutes']);
        $this->assertSame($late, $result['late_minutes']);
        $this->assertSame($undertime, $result['undertime_minutes']);
    }

    public function test_incomplete_and_negative_intervals_do_not_invent_work_or_credits(): void
    {
        $calculator = new AttendanceCalculator;
        foreach ([['time_in' => '08:00'], ['time_in' => '17:00', 'time_out' => '08:00'], []] as $times) {
            $result = $calculator->calculate($times, null);
            $this->assertNull($result['worked_minutes']);
            $this->assertTrue($result['needs_review']);
        }
    }

    public function test_suspension_and_explicit_overtime_use_minutes(): void
    {
        $calculator = new AttendanceCalculator;
        $result = $calculator->calculate(['time_in' => '09:00', 'time_out' => '14:00', 'ot_in' => '18:00', 'ot_out' => '21:00'], 5, false, '14:00');
        $this->assertSame(240, $result['worked_minutes']);
        $this->assertSame(0, $result['undertime_minutes']);
        $this->assertSame(120, $result['overtime_minutes']);
        $result = $calculator->calculate(['time_in' => '10:00', 'time_out' => '11:00'], 5, false, '08:00');
        $this->assertSame(0, $result['late_minutes']);
    }

    public function test_explicit_lunch_gaps_do_not_count_as_rendered_work(): void
    {
        $result = (new AttendanceCalculator)->calculate(['time_in' => '08:00', 'lunch_out' => '11:30', 'lunch_in' => '13:30', 'time_out' => '17:00'], 5);
        $this->assertSame(420, $result['worked_minutes']);
        $this->assertSame(60, $result['undertime_minutes']);
        $afternoon = (new AttendanceCalculator)->calculate(['lunch_in' => '13:00', 'time_out' => '18:00'], 5);
        $this->assertSame(300, $afternoon['worked_minutes']);
        $this->assertTrue($afternoon['needs_review']);
    }
}
