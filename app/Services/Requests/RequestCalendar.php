<?php

namespace App\Services\Requests;

use App\Models\CtoDate;
use App\Models\LeaveDate;
use App\Models\OfficialBusiness;
use App\Models\OvertimeDate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RequestCalendar
{
    /** @return Collection<int, array<string, mixed>> */
    public function events(User $actor, string $calendar, string $from, string $to): Collection
    {
        if ($calendar === 'ed') {
            return collect();
        }
        $personal = $calendar !== 'office';
        $events = collect();
        OfficialBusiness::with('employee:id,name')->whereBetween('business_date', [$from, $to])
            ->when($personal, fn ($q) => $q->where('user_id', $actor->id))->whereRaw("(select status from request_statuses where request_type = 'OB' and request_id = official_businesses.id order by id desc limit 1) = 'Approved'")->get()
            ->each(function (OfficialBusiness $request) use ($events, $personal) {
                $events->push($this->event('ob-'.$request->id, ($personal ? '' : $request->employee->name.' · ').'Official business: '.$request->destination, CarbonImmutable::parse($request->business_date)->toDateString(), 'Official business'));
            });
        CtoDate::with('request.employee:id,name')->whereBetween('cto_date', [$from, $to])->whereHas('request', fn ($q) => $q
            ->when($personal, fn ($x) => $x->where('user_id', $actor->id))->whereRaw("(select status from request_statuses where request_type = 'CTO' and request_id = cto_requests.id order by id desc limit 1) = 'Approved'"))->get()
            ->each(function (CtoDate $date) use ($events, $personal) {
                $events->push($this->event('cto-'.$date->cto_request_id.'-'.$date->id, ($personal ? '' : $date->request->employee->name.' · ').'CTO ('.$date->portion.')', CarbonImmutable::parse($date->cto_date)->toDateString(), 'CTO'));
            });
        LeaveDate::with('request.employee:id,name', 'request.type:id,name')->whereBetween('leave_date', [$from, $to])->whereHas('request', fn ($q) => $q
            ->when($personal, fn ($x) => $x->where('user_id', $actor->id))->whereRaw("(select status from request_statuses where request_type = 'LEAVE' and request_id = leave_requests.id order by id desc limit 1) = 'Approved'"))->get()
            ->each(function (LeaveDate $date) use ($events, $personal) {
                $events->push($this->event('leave-'.$date->leave_request_id.'-'.$date->id, ($personal ? '' : $date->request->employee->name.' · ').$date->request->type->name, CarbonImmutable::parse($date->leave_date)->toDateString(), 'Leave'));
            });
        OvertimeDate::with('request.employee:id,name')->whereBetween('overtime_date', [$from, $to])->whereHas('request', fn ($q) => $q
            ->when($personal, fn ($x) => $x->where('user_id', $actor->id))->whereRaw("(select status from request_statuses where request_type = 'OT' and request_id = overtime_requests.id order by id desc limit 1) = 'Approved'"))->get()
            ->each(function (OvertimeDate $date) use ($events, $personal) {
                $events->push($this->event('ot-'.$date->overtime_request_id.'-'.$date->id, ($personal ? '' : $date->request->employee->name.' · ').'Authorized overtime', CarbonImmutable::parse($date->overtime_date)->toDateString(), 'Overtime'));
            });

        return $events;
    }

    /** @return array<string, mixed> */
    private function event(string $id, string $title, string $date, string $type): array
    {
        return ['id' => $id, 'title' => $title, 'allDay' => true, 'start' => $date, 'end' => CarbonImmutable::parse($date)->addDay()->toDateString(),
            'extendedProps' => ['calendar' => $type, 'can_edit' => false, 'request' => true]];
    }
}
