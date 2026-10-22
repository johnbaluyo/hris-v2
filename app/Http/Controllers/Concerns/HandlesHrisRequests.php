<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Delegation;
use App\Models\PrivateFile;
use App\Models\RequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait HandlesHrisRequests
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function visibleRequests(Builder $query, Request $request, string $type): Builder
    {
        $actor = $request->user();
        $query->addSelect(['current_status' => RequestStatus::select('status')->whereColumn('request_id', $query->getModel()->getTable().'.id')
            ->where('request_type', $type)->latest('id')->limit(1)]);
        if ($actor->hasAbility('requests.certify') || $actor->actsAsDirector() || $actor->hasAbility('leave.witness')) {
            return $query;
        }
        $divisionIds = $actor->role === 'staff'
            ? Delegation::where('user_id', $actor->id)->where('ability', 'division-chief.oic')
                ->whereDate('period_start', '<=', today('Asia/Manila'))->whereDate('period_end', '>=', today('Asia/Manila'))->pluck('division_id')
            : collect();
        if ($actor->role === 'division_chief' && $actor->division_id !== null) {
            $divisionIds->push($actor->division_id);
        }
        if ($divisionIds->isNotEmpty()) {
            return $query->where(fn ($q) => $q->where('user_id', $actor->id)->orWhereHas('employee', fn ($employees) => $employees->whereIn('division_id', $divisionIds->unique())));
        }

        return $query->where('user_id', $actor->id);
    }

    private function assertOwnerOrOverride(Request $request, int $userId): void
    {
        abort_unless($request->user()->id === $userId || $request->user()->hasAbility('requests.override'), 403);
    }

    private function storeAttachment(Request $request, User $employee): ?int
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }
        $request->validate(['attachment' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
        $upload = $request->file('attachment');
        $path = $upload->store('hris/'.$employee->id.'/requests', 'local');
        abort_unless(is_string($path), 503, 'File storage is unavailable.');
        try {
            return PrivateFile::create(['user_id' => $employee->id, 'actor_id' => $request->user()->id, 'purpose' => 'request-evidence',
                'original_name' => mb_substr(basename($upload->getClientOriginalName()), 0, 255), 'path' => $path,
                'mime' => $upload->getMimeType(), 'size' => $upload->getSize(), 'checksum' => hash_file('sha256', $upload->getRealPath())])->id;
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }
}
