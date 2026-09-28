<?php

namespace App\Services;

use App\Enums\AdminNotificationTypeEnum;
use App\Enums\AuditEventEnum;
use App\Enums\ComplaintStatusEnum;
use App\Models\Complaint;
use App\Models\CustomerLocation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for customer complaints (Reklamationen).
 *
 * A complaint is a quality signal, not a work order: it never produces paid
 * hours. Every state change is written to the audit log, and a newly raised
 * complaint pings the administration inbox so it is not left unnoticed.
 */
class ComplaintService
{
    public function __construct(
        private readonly NotificationEngineService $notifications,
        private readonly AuditLogger $audit,
    ) {
    }

    // ── Read ───────────────────────────────────────────────────────────────

    /**
     * List complaints with optional filtering.
     *
     * A Mitarbeiter never sees the whole queue — only what they are assigned
     * to. Vorarbeiter and Administrator see everything.
     *
     * @param  array{client_id?: int|null, customer_id?: int|null, location_id?: int|null, status?: string|null, assigned_employee_id?: int|null}  $filters
     */
    public function listFor(User $actor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Complaint::query()->latest();

        if (! $actor->isAdministrator() && ! $actor->isVorarbeiter()) {
            $query->assignedTo($actor->id);
        }

        // The API speaks `client_id`, the schema says `customer_id`; accept both.
        $clientId = $filters['client_id'] ?? $filters['customer_id'] ?? null;
        if ($clientId !== null) {
            $query->where('customer_id', $clientId);
        }

        if (($locationId = $filters['location_id'] ?? null) !== null) {
            $query->where('location_id', $locationId);
        }

        if (($status = $filters['status'] ?? null) !== null) {
            $query->where('status', $status);
        }

        if (($assigned = $filters['assigned_employee_id'] ?? null) !== null) {
            $query->where('assigned_employee_id', $assigned);
        }

        return $query->paginate($perPage);
    }

    /**
     * Fetch a complaint the actor is allowed to see, or fail with a 404.
     *
     * A 404 rather than a 403 so that an employee cannot probe for the
     * existence of complaints belonging to somebody else.
     */
    public function findVisible(Complaint|int $complaint, User $actor): Complaint
    {
        $complaint = $complaint instanceof Complaint ? $complaint : Complaint::findOrFail($complaint);

        if (! $actor->isAdministrator() && ! $actor->isVorarbeiter()) {
            abort_unless(
                (int) $complaint->assigned_employee_id === (int) $actor->id,
                404
            );
        }

        return $complaint;
    }

    // ── Write ──────────────────────────────────────────────────────────────

    /**
     * Register a new complaint and alert the administration.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): Complaint
    {
        $complaint = DB::transaction(function () use ($attributes, $actor) {
            $complaint = Complaint::create([
                'customer_id'          => $attributes['client_id'] ?? $attributes['customer_id'] ?? null,
                'location_id'          => $attributes['location_id'],
                'assigned_employee_id' => $attributes['assigned_employee_id'] ?? null,
                'reported_by'          => $attributes['reported_by'] ?? $actor->name,
                'occurred_at'          => $attributes['occurred_at'] ?? now(),
                'title'                => $attributes['title'],
                'description'          => $attributes['description'],
                'photos'               => $attributes['photos'] ?? [],
                'status'               => $attributes['status'] ?? ComplaintStatusEnum::Pending->value,
            ]);

            $this->audit->record(
                $complaint,
                AuditEventEnum::Created,
                newValues: [
                    'status'      => $complaint->status->value,
                    'location_id' => $complaint->location_id,
                    'title'       => $complaint->title,
                ],
                actorId: $actor->id,
            );

            return $complaint;
        });

        $this->notifyAdmins($complaint, $actor);

        return $complaint;
    }

    /**
     * Move a complaint along its lifecycle.
     *
     * Resolving stamps `resolved_at`/`resolved_by` and requires a resolution
     * note; reopening clears them again so the record stays truthful.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateStatus(Complaint $complaint, ComplaintStatusEnum $status, array $attributes, User $actor): Complaint
    {
        $previous = $complaint->status;

        DB::transaction(function () use ($complaint, $status, $attributes, $actor, $previous) {
            $changes = ['status' => $status->value];

            if ($status === ComplaintStatusEnum::Resolved) {
                $changes['resolution']  = $attributes['resolution'] ?? $complaint->resolution;
                $changes['resolved_at'] = now();
                $changes['resolved_by'] = $actor->id;
            } else {
                // Reopened or moved back into progress — the old resolution
                // must not keep masquerading as the current one.
                $changes['resolution']  = null;
                $changes['resolved_at'] = null;
                $changes['resolved_by'] = null;
            }

            if (($assigned = $attributes['assigned_employee_id'] ?? null) !== null) {
                $changes['assigned_employee_id'] = $assigned;
            }

            $complaint->fill($changes)->save();

            $this->audit->record(
                $complaint,
                AuditEventEnum::Updated,
                oldValues: [
                    'status'     => $previous->value,
                    'resolution' => $complaint->resolution,
                ],
                newValues: $changes,
                reason: $attributes['reason'] ?? null,
                actorId: $actor->id,
            );
        });

        return $complaint->fresh(['assignedEmployee', 'resolver', 'location', 'customer']);
    }

    /**
     * Reassign the complaint to another employee.
     */
    public function assign(Complaint $complaint, User $assignee, User $actor): Complaint
    {
        $previous = $complaint->assigned_employee_id;

        $complaint->fill(['assigned_employee_id' => $assignee->id])->save();

        $this->audit->record(
            $complaint,
            AuditEventEnum::Reassigned,
            oldValues: ['assigned_employee_id' => $previous],
            newValues: ['assigned_employee_id' => $assignee->id],
            actorId: $actor->id,
        );

        return $complaint->fresh(['assignedEmployee']);
    }

    // ── Notifications ──────────────────────────────────────────────────────

    private function notifyAdmins(Complaint $complaint, User $actor): void
    {
        $this->notifications->notifyAdmins(
            AdminNotificationTypeEnum::Alert,
            __('complaints.notify_title', ['title' => $complaint->title]),
            __('complaints.notify_body', [
                'location' => $complaint->location?->name,
                'actor'    => $actor->name,
            ]),
            [
                'complaint_id' => $complaint->id,
                'location_id'  => $complaint->location_id,
                'status'       => $complaint->status->value,
            ],
            'complaint:'.$complaint->id
        );
    }

    // ── Validation helpers ─────────────────────────────────────────────────

    /**
     * @return array<string, array<int, string>>
     */
    public static function storeRules(): array
    {
        return [
            'client_id'             => ['nullable', 'integer', 'exists:customers,id'],
            'location_id'           => ['required', 'integer', 'exists:customer_locations,id'],
            'assigned_employee_id'  => ['nullable', 'integer', 'exists:users,id'],
            'reported_by'           => ['nullable', 'string', 'max:120'],
            'occurred_at'           => ['nullable', 'date'],
            'title'                 => ['required', 'string', 'max:191'],
            'description'           => ['required', 'string', 'max:5000'],
            'photos'                => ['nullable', 'array', 'max:10'],
            'photos.*'              => ['string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function statusRules(): array
    {
        return [
            'status'                => ['required', 'string', 'in:'.implode(',', ComplaintStatusEnum::values())],
            'resolution'            => ['nullable', 'string', 'max:5000'],
            'assigned_employee_id'  => ['nullable', 'integer', 'exists:users,id'],
            'reason'                => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function filterRules(): array
    {
        return [
            'client_id'             => ['nullable', 'integer', 'exists:customers,id'],
            'location_id'           => ['nullable', 'integer', 'exists:customer_locations,id'],
            'status'                => ['nullable', 'string', 'in:'.implode(',', ComplaintStatusEnum::values())],
            'assigned_employee_id'  => ['nullable', 'integer', 'exists:users,id'],
            'per_page'              => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
