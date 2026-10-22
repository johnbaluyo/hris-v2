<?php

namespace App\Services\Attendance;

use App\Models\CtoDate;
use App\Models\LeaveDate;
use App\Models\OfficialBusiness;

class RequestExemptions
{
    /** @return array{late: bool, undertime: bool, sources: array<int, string>} */
    public function forDate(int $userId, string $date): array
    {
        $late = false;
        $undertime = false;
        $sources = [];
        $businesses = OfficialBusiness::where('user_id', $userId)->whereDate('business_date', $date)
            ->whereRaw("(select status from request_statuses where request_type = 'OB' and request_id = official_businesses.id order by id desc limit 1) = 'Approved'")->get();
        foreach ($businesses as $business) {
            $late = $late || $business->go_directly;
            $undertime = $undertime || ! $business->return_to_office;
            $sources[] = 'OB:'.$business->id;
        }
        $ctoDates = CtoDate::whereDate('cto_date', $date)->whereHas('request', fn ($q) => $q->where('user_id', $userId)
            ->whereRaw("(select status from request_statuses where request_type = 'CTO' and request_id = cto_requests.id order by id desc limit 1) = 'Approved'"))->get();
        foreach ($ctoDates as $ctoDate) {
            $late = $late || in_array($ctoDate->portion, ['WHOLE', 'AM'], true);
            $undertime = $undertime || in_array($ctoDate->portion, ['WHOLE', 'PM'], true);
            $sources[] = 'CTO:'.$ctoDate->cto_request_id;
        }
        $leaveDates = LeaveDate::whereDate('leave_date', $date)->where('day_fraction', 1)
            ->whereHas('request', fn ($q) => $q->where('user_id', $userId)->whereRaw("(select status from request_statuses where request_type = 'LEAVE' and request_id = leave_requests.id order by id desc limit 1) = 'Approved'"))->get();
        foreach ($leaveDates as $leaveDate) {
            $late = $undertime = true;
            $sources[] = 'LEAVE:'.$leaveDate->leave_request_id;
        }

        return compact('late', 'undertime', 'sources');
    }
}
