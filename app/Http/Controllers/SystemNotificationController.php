<?php

namespace App\Http\Controllers;

use App\Models\NotificationReceipt;
use App\Models\ServiceClient;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\Notifications\NotificationPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SystemNotificationController extends Controller
{
    public function index(): View
    {
        return view('hris.notifications');
    }

    /** @return Builder<NotificationReceipt> */
    private function receipts(Request $request, bool $active = true): Builder
    {
        return NotificationReceipt::where('user_id', $request->user()->id)->with('notification')
            ->when($active, fn ($q) => $q->whereNull('dismissed_at')->whereHas('notification', fn ($n) => $n->where('is_active', true)
                ->where(fn ($expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()))));
    }

    public function data(Request $request): JsonResponse
    {
        $query = $this->receipts($request)->orderByDesc(SystemNotification::select('priority')->whereColumn('id', 'notification_receipts.system_notification_id')->limit(1))
            ->orderByDesc('system_notification_id');
        $page = $query->paginate(10);
        $page->getCollection()->transform(fn (NotificationReceipt $receipt) => $this->resource($receipt));

        return response()->json($page->toArray() + ['status' => 'success', 'notifications' => $page->items(), 'count' => $page->total()]);
    }

    public function count(Request $request): JsonResponse
    {
        $query = $this->receipts($request);

        return response()->json(['status' => 'success', 'total' => (clone $query)->count(), 'unread' => $query->whereNull('read_at')->count()]);
    }

    public function receipt(Request $request, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['read', 'dismiss', 'read-all', 'dismiss-all'], true), 404);
        $all = str_ends_with($action, '-all');
        $field = str_starts_with($action, 'read') ? 'read_at' : 'dismissed_at';
        $query = $this->receipts($request, $all);
        if (! $all) {
            $data = $request->validate(['id' => 'required|integer']);
            $query->where('system_notification_id', $data['id']);
            abort_unless((clone $query)->exists(), 404);
        }
        $count = (clone $query)->whereNull($field)->update([$field => now()]);

        return response()->json(['status' => 'success', 'message' => 'Notification state saved.', 'count' => $count,
            'notification' => $all ? null : $this->resource($query->firstOrFail())]);
    }

    public function create(Request $request, NotificationPublisher $publisher): JsonResponse
    {
        $client = $request->attributes->get('service_client');
        $external = $client instanceof ServiceClient;
        abort_unless($external || $request->user()?->hasAbility('notifications.publish'), 403);
        $data = $request->validate(['title' => 'required|string|max:255', 'message' => 'required|string|max:5000',
            'source' => 'nullable|string|max:100', 'system' => 'nullable|string|max:100', 'url' => 'nullable|string|max:2048',
            'notification_type' => 'required|in:personal,broadcast,role_based', 'user_id' => 'nullable|integer|exists:users,id',
            'user_email' => 'nullable|email|max:255', 'target_user_ids' => 'nullable|array|max:500', 'target_user_ids.*' => 'integer|distinct|exists:users,id',
            'target_roles' => 'nullable|array', 'target_roles.*' => ['string', 'distinct', Rule::in(array_keys(config('hris.roles')))],
            'priority' => 'sometimes|integer|min:0|max:10', 'expires_at' => 'nullable|date|after:now',
            'badge_text' => 'sometimes|string|max:40', 'badge_class' => 'sometimes|in:badge-info,badge-success,badge-warning,badge-danger',
            'event_id' => ($external ? 'required' : 'nullable').'|string|max:120', 'target_users' => 'prohibited']);
        if ($external && $data['notification_type'] !== 'personal') {
            abort_unless(in_array('notifications.broadcast', $client->scopes, true), 403);
        }
        $roles = $data['target_roles'] ?? [];
        $recipients = [];
        $email = null;
        if ($data['notification_type'] === 'personal') {
            $user = ! empty($data['user_email']) ? User::where('email', $data['user_email'])->first() : User::whereKey($data['user_id'] ?? 0)->first();
            if (! $user || ! $user->isEligible() || (! empty($data['user_id']) && $user->id !== (int) $data['user_id'])) {
                throw ValidationException::withMessages(['user_id' => 'Provide a matching active recipient ID or email.']);
            }
            $recipients = [$user->id];
            $email = $user->email;
        } elseif ($data['notification_type'] === 'broadcast') {
            $recipients = User::where('role', '!=', 'applicant')->where('is_archived', false)
                ->where(fn ($q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>', today('Asia/Manila')))->pluck('id')->all();
        } else {
            if (empty($roles) && empty($data['target_user_ids'])) {
                throw ValidationException::withMessages(['target_roles' => 'Select explicit roles or recipient IDs.']);
            }
            $recipients = User::where('is_archived', false)->where(fn ($q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>', today('Asia/Manila')))
                ->where(fn ($q) => $q->whereIn('role', $roles)->orWhereIn('id', $data['target_user_ids'] ?? []))->pluck('id')->all();
        }
        $url = $data['url'] ?? null;
        if ($url !== null) {
            $this->validateDestination($url, $external ? $client->name : 'HRIS');
        }
        $notice = $publisher->publish(['source_key' => $external ? 'service:'.$client->id : 'browser:'.$request->user()->id,
            'event_id' => $data['event_id'] ?? (string) Str::uuid(), 'system' => $external ? $client->name : ($data['system'] ?? 'HRIS'),
            'source' => $external ? $client->name : ($data['source'] ?? 'HRIS'), 'notification_type' => $data['notification_type'],
            'recipient_email' => $email, 'title' => $data['title'], 'message' => $data['message'], 'url' => $url,
            'priority' => $data['priority'] ?? 5, 'badge_text' => $data['badge_text'] ?? 'New', 'badge_class' => $data['badge_class'] ?? 'badge-info',
            'expires_at' => isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at'])->format('Y-m-d H:i:s') : null], $recipients, $roles, $data['target_user_ids'] ?? []);

        return response()->json(['status' => 'success', 'message' => 'Notification created successfully', 'notification_id' => $notice->id], 201);
    }

    private function validateDestination(string $url, string $source): void
    {
        $valid = preg_match('#^/(?!/)[^\\\\\x00-\x20]*$#D', $url) === 1;
        foreach (config('records.notification_destinations.'.$source, []) as $base) {
            $base = rtrim($base, '/');
            if (filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['https'], true)
                && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null
                && ($url === $base || str_starts_with($url, $base.'/') || str_starts_with($url, $base.'?'))) {
                $valid = true;
            }
        }
        if (! $valid) {
            throw ValidationException::withMessages(['url' => 'Choose a local path or an approved HTTPS destination.']);
        }
    }

    /** @return array<string, mixed> */
    private function resource(NotificationReceipt $receipt): array
    {
        $notice = $receipt->notification;

        return ['id' => 'notif-'.$notice->id, 'notification_id' => $notice->id, 'url' => $notice->url,
            'source' => $notice->source, 'title' => $notice->title, 'meta' => $notice->message, 'badgeClass' => $notice->badge_class,
            'badgeText' => $notice->badge_text, 'icon' => 'fas fa-bell', 'priority' => $notice->priority,
            'is_read' => $receipt->read_at !== null, 'is_dismissed' => $receipt->dismissed_at !== null,
            'system' => $notice->system, 'created_at' => $notice->created_at];
    }
}
