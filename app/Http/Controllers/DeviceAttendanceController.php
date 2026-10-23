<?php

namespace App\Http\Controllers;

use App\Models\RawPunch;
use App\Models\ServiceClient;
use App\Support\AttendanceIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeviceAttendanceController extends Controller
{
    public function maxId(Request $request): JsonResponse
    {
        /** @var ServiceClient $client */
        $client = $request->attributes->get('service_client');
        $data = $request->validate(['source_epoch' => 'nullable|string|regex:/^[A-Za-z0-9_-]{1,40}$/D']);

        return response()->json(['success' => true, 'max_id' => (int) RawPunch::where('service_client_id', $client->id)
            ->where('source_epoch', $data['source_epoch'] ?? 'default')->max('anviz_log_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var ServiceClient $client */
        $client = $request->attributes->get('service_client');
        $data = $request->validate(['source_epoch' => 'nullable|string|regex:/^[A-Za-z0-9_-]{1,40}$/D',
            'records' => 'required|array|min:1|max:1000',
            'records.*' => 'array:anviz_log_id,user_atms_id,time_log,device,record_type,created_at,updated_at',
            'records.*.anviz_log_id' => 'required|integer|min:1|distinct', 'records.*.user_atms_id' => ['required', 'string', 'regex:/^[0-9]{1,50}$/D'],
            'records.*.time_log' => 'required|date', 'records.*.device' => 'required|string|max:100', 'records.*.record_type' => 'nullable|string|max:50',
            'records.*.created_at' => 'nullable|date', 'records.*.updated_at' => 'nullable|date']);
        $epoch = $data['source_epoch'] ?? 'default';
        $counts = DB::transaction(function () use ($client, $data, $epoch) {
            // Serialize batches from this stream, including concurrent retries.
            $lockedClient = ServiceClient::whereKey($client->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedClient->revoked_at !== null || ! hash_equals($lockedClient->token_hash, $client->token_hash), 401, 'The service credential is no longer valid.');
            $inserted = $duplicates = $unmatched = 0;
            foreach ($data['records'] as $index => $input) {
                if (! in_array($input['device'], $client->devices, true)) {
                    throw ValidationException::withMessages(["records.{$index}.device" => 'This device is not registered for the service client.']);
                }
                $atmsId = AttendanceIdentity::normalize($input['user_atms_id']);
                $values = ['atms_id' => $atmsId,
                    'punched_at' => CarbonImmutable::parse($input['time_log'], 'Asia/Manila')->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    'device' => $input['device'], 'record_type' => $input['record_type'] ?? null];
                $key = $client->id.':'.$epoch.':'.$input['anviz_log_id'];
                $existing = RawPunch::where('source_key', $key)->first();
                if ($existing) {
                    $source = $existing->source_evidence ?? [];
                    foreach ($values as $field => $value) {
                        $old = array_key_exists($field, $source) ? $source[$field]
                            : ($field === 'punched_at' ? $existing->punched_at->format('Y-m-d H:i:s') : $existing->$field);
                        if ($old !== $value) {
                            throw ValidationException::withMessages(["records.{$index}.anviz_log_id" => 'This source event already exists with different evidence. No records were inserted.']);
                        }
                    }
                    $duplicates++;
                    $punch = $existing;
                } else {
                    $punch = RawPunch::create($values + ['service_client_id' => $client->id, 'source_key' => $key, 'anviz_log_id' => $input['anviz_log_id'], 'source_epoch' => $epoch]);
                    $inserted++;
                }
                if ($punch->user_id === null) {
                    $unmatched++;
                }
            }

            return ['received_count' => count($data['records']), 'inserted_count' => $inserted, 'duplicate_count' => $duplicates, 'unmatched_count' => $unmatched];
        }, 3);

        return response()->json(['success' => true, 'message' => 'Batch processed.'] + $counts, 201);
    }
}
