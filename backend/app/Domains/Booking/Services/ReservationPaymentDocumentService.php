<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Appointment;
use App\Domains\Booking\Models\BookableService;
use App\Domains\Booking\Models\ReservationPaymentDocument;
use App\Domains\Payments\Enums\PaymentMethodType;
use App\Domains\Payments\Services\DepositPaymentService;
use App\Domains\Payments\Services\TenantPaymentsSettingsService;
use App\Shared\Audit\AuditLogger;
use App\Shared\Support\PublicStorageUrl;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationPaymentDocumentService
{
    public const MIN_FEE_CENTS = 1000;

    public const MAX_PROOF_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BookingScopeValidator $scope,
        private readonly TenantPaymentsSettingsService $paymentsSettings,
        private readonly DepositPaymentService $depositPayments,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function list(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = ReservationPaymentDocument::query()
            ->with(['appointment.client', 'appointment.teamMember', 'appointment.serviceLines', 'client', 'service', 'reviewedBy'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->limit(200)->get();
    }

    public function find(string $id): ReservationPaymentDocument
    {
        $doc = ReservationPaymentDocument::query()
            ->with(['appointment.client', 'appointment.teamMember', 'appointment.serviceLines', 'client', 'service', 'reviewedBy'])
            ->findOrFail($id);

        $this->scope->assertTenantModel($doc);

        return $doc;
    }

    /**
     * Public upload of a bank-transfer proof before booking is finalized.
     */
    public function createFromPublicUpload(
        UploadedFile $file,
        string $bookingServiceId,
        string $paymentMethod,
    ): ReservationPaymentDocument {
        if ($paymentMethod !== ReservationPaymentDocument::METHOD_TRANSFER) {
            throw ValidationException::withMessages([
                'payment_method' => ['Only bank transfer proofs can be uploaded. Stripe and Google Pay are coming soon.'],
            ]);
        }

        if (! $this->paymentsSettings->hasCompleteBankDetails()) {
            throw ValidationException::withMessages([
                'bank_details' => ['This salon has not published bank details for transfers yet.'],
            ]);
        }

        $service = $this->scope->findBookableService($bookingServiceId);
        $amount = $this->requiredFeeCentsForService($service);

        if ($amount < self::MIN_FEE_CENTS) {
            throw ValidationException::withMessages([
                'booking_service_id' => ['This service does not require a reservation fee.'],
            ]);
        }

        $this->assertValidProofFile($file);

        $tenantId = $this->tenantContext->id();
        $path = $file->store('reservation-proofs/'.$tenantId, 'public');

        $doc = ReservationPaymentDocument::query()->create([
            'tenant_id' => $tenantId,
            'booking_service_id' => $service->id,
            'amount_cents' => $amount,
            'payment_method' => ReservationPaymentDocument::METHOD_TRANSFER,
            'status' => ReservationPaymentDocument::STATUS_PENDING_REVIEW,
            'proof_path' => $path,
            'proof_original_name' => $file->getClientOriginalName(),
            'proof_mime' => $file->getMimeType(),
            'proof_size_bytes' => $file->getSize(),
            'public_token' => Str::random(48),
            'metadata' => [
                'source' => 'online_booking',
            ],
        ]);

        $this->auditLogger->log('reservation_payment_document.uploaded', $doc, null, [
            'amount_cents' => $doc->amount_cents,
            'payment_method' => $doc->payment_method,
        ]);

        return $doc;
    }

    public function attachToAppointment(ReservationPaymentDocument $doc, Appointment $appointment): ReservationPaymentDocument
    {
        $this->scope->assertTenantModel($doc);
        $this->scope->assertTenantModel($appointment);

        if ($doc->appointment_id !== null && $doc->appointment_id !== $appointment->id) {
            throw ValidationException::withMessages([
                'reservation_document_id' => ['This payment proof is already linked to another booking.'],
            ]);
        }

        if ($doc->status !== ReservationPaymentDocument::STATUS_PENDING_REVIEW || blank($doc->proof_path)) {
            throw ValidationException::withMessages([
                'reservation_document_id' => ['Upload a successful transfer screenshot before confirming.'],
            ]);
        }

        $doc->forceFill([
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'amount_cents' => max($doc->amount_cents, (int) ($appointment->deposit_required_cents ?? 0)),
        ])->save();

        return $doc->fresh();
    }

    public function assertReadyForBooking(?string $documentId, BookableService $service): void
    {
        $amount = $this->requiredFeeCentsForService($service);
        if ($amount < self::MIN_FEE_CENTS) {
            return;
        }

        if ($documentId === null || $documentId === '') {
            throw ValidationException::withMessages([
                'reservation_document_id' => ['Upload your transfer screenshot to pay the reservation fee before booking.'],
            ]);
        }

        $doc = ReservationPaymentDocument::query()->find($documentId);
        if ($doc === null || $doc->tenant_id !== $this->tenantContext->id()) {
            throw ValidationException::withMessages([
                'reservation_document_id' => ['Reservation payment proof not found.'],
            ]);
        }

        if ($doc->payment_method !== ReservationPaymentDocument::METHOD_TRANSFER) {
            throw ValidationException::withMessages([
                'payment_method' => ['Only bank transfer is available right now.'],
            ]);
        }

        if (blank($doc->proof_path) || $doc->status !== ReservationPaymentDocument::STATUS_PENDING_REVIEW) {
            throw ValidationException::withMessages([
                'reservation_document_id' => ['Upload a successful transfer screenshot before confirming.'],
            ]);
        }

        if ($doc->appointment_id !== null) {
            throw ValidationException::withMessages([
                'reservation_document_id' => ['This payment proof was already used.'],
            ]);
        }
    }

    public function confirm(string $id, ?string $teamMemberId = null, ?string $note = null): ReservationPaymentDocument
    {
        $doc = $this->find($id);

        if ($doc->status !== ReservationPaymentDocument::STATUS_PENDING_REVIEW) {
            throw ValidationException::withMessages([
                'status' => ['Only pending payment documents can be confirmed.'],
            ]);
        }

        if ($doc->appointment_id === null) {
            throw ValidationException::withMessages([
                'appointment_id' => ['This document is not linked to a booking yet.'],
            ]);
        }

        return DB::transaction(function () use ($doc, $teamMemberId, $note) {
            $old = $doc->toArray();

            $this->depositPayments->recordPayment([
                'appointment_id' => $doc->appointment_id,
                'amount_cents' => $doc->amount_cents,
                'payment_method_type' => PaymentMethodType::BANK_TRANSFER,
                'payment_method_label' => 'Bank transfer (reservation proof)',
                'external_reference' => $doc->id,
                'metadata' => [
                    'reservation_payment_document_id' => $doc->id,
                    'proof_path' => $doc->proof_path,
                    'review_note' => $note,
                ],
            ], $teamMemberId);

            $doc->forceFill([
                'status' => ReservationPaymentDocument::STATUS_CONFIRMED,
                'reviewed_by_team_member_id' => $teamMemberId,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            $this->auditLogger->log('reservation_payment_document.confirmed', $doc, $old, $doc->toArray());

            return $doc->fresh(['appointment.client', 'appointment.teamMember', 'appointment.serviceLines', 'client', 'service', 'reviewedBy']);
        });
    }

    public function reject(string $id, ?string $teamMemberId = null, ?string $note = null): ReservationPaymentDocument
    {
        $doc = $this->find($id);

        if ($doc->status !== ReservationPaymentDocument::STATUS_PENDING_REVIEW) {
            throw ValidationException::withMessages([
                'status' => ['Only pending payment documents can be rejected.'],
            ]);
        }

        $old = $doc->toArray();
        $doc->forceFill([
            'status' => ReservationPaymentDocument::STATUS_REJECTED,
            'reviewed_by_team_member_id' => $teamMemberId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        $this->auditLogger->log('reservation_payment_document.rejected', $doc, $old, $doc->toArray());

        return $doc->fresh(['appointment.client', 'appointment.teamMember', 'appointment.serviceLines', 'client', 'service', 'reviewedBy']);
    }

    public function requiredFeeCentsForService(BookableService $service): int
    {
        if (! $service->deposit_required || $service->deposit_amount_cents === null) {
            return 0;
        }

        return max(0, (int) $service->deposit_amount_cents);
    }

    public function serialize(ReservationPaymentDocument $doc): array
    {
        $proofUrl = $doc->proof_path ? PublicStorageUrl::fromDiskPath($doc->proof_path) : null;

        return [
            'id' => $doc->id,
            'appointment_id' => $doc->appointment_id,
            'client_id' => $doc->client_id,
            'booking_service_id' => $doc->booking_service_id,
            'amount_cents' => $doc->amount_cents,
            'payment_method' => $doc->payment_method,
            'status' => $doc->status,
            'proof_url' => $proofUrl,
            'proof_original_name' => $doc->proof_original_name,
            'proof_mime' => $doc->proof_mime,
            'proof_size_bytes' => $doc->proof_size_bytes,
            'public_token' => $doc->public_token,
            'review_note' => $doc->review_note,
            'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
            'created_at' => $doc->created_at?->toIso8601String(),
            'appointment' => $doc->appointment ? [
                'id' => $doc->appointment->id,
                'booking_reference' => $doc->appointment->booking_reference,
                'starts_at' => $doc->appointment->starts_at?->toIso8601String(),
                'status' => $doc->appointment->status,
                'deposit_status' => $doc->appointment->deposit_status,
                'deposit_required_cents' => $doc->appointment->deposit_required_cents,
                'client_name' => $doc->appointment->client?->resolvedDisplayName(),
                'provider_name' => $doc->appointment->teamMember?->display_name,
                'services' => $doc->appointment->serviceLines?->pluck('service_name')->filter()->values()->all() ?? [],
            ] : null,
            'client_name' => $doc->client?->resolvedDisplayName(),
            'service_name' => $doc->service?->name,
            'reviewed_by_name' => $doc->reviewedBy?->display_name,
        ];
    }

    private function assertValidProofFile(UploadedFile $file): void
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        $allowedExt = ['jpg', 'jpeg', 'png'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/jpg'];

        if (! in_array($ext, $allowedExt, true) || ! in_array($mime, $allowedMime, true)) {
            throw ValidationException::withMessages([
                'proof' => ['Attachment must be a .jpg, .jpeg, or .png image.'],
            ]);
        }

        if ($file->getSize() > self::MAX_PROOF_BYTES) {
            throw ValidationException::withMessages([
                'proof' => ['Attachment must be 2MB or smaller.'],
            ]);
        }
    }
}
