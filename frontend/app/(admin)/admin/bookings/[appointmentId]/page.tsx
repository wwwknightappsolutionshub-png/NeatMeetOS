'use client';

import Link from 'next/link';
import { Suspense, useCallback, useEffect, useState } from 'react';
import { useParams, useSearchParams } from 'next/navigation';
import { AppointmentPackagePanel } from '@/components/admin/booking/AppointmentPackagePanel';
import { AdminBookingShell } from '@/components/admin/booking/AdminBookingShell';
import { ErrorAlert, Field, inputClass, LoadingState } from '@/components/admin/ui';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import type { Appointment } from '@/lib/booking-types';
import { APPOINTMENT_STATUSES } from '@/lib/booking-types';
import type { Workspace } from '@/lib/identity-types';
import { fetchWorkspaces } from '@/services/identity.service';
import {
  cancelAppointment,
  correctAppointmentStatus,
  fetchAppointment,
  proposeAppointmentPostpone,
  reassignAppointmentWorkspace,
  rebookAppointment,
  updateAppointment,
  updateAppointmentStatus,
} from '@/services/booking.service';
import {
  fetchAppointmentDeposit,
  recordAppointmentDepositPayment,
  refundAppointmentDeposit,
  waiveAppointmentDeposit,
} from '@/services/payments.service';
import type { DepositInspect } from '@/lib/payments-types';

export default function AppointmentDetailPage() {
  return (
    <Suspense fallback={
      <AdminBookingShell title="Appointment">
        <LoadingState />
      </AdminBookingShell>
    }>
      <AppointmentDetailContent />
    </Suspense>
  );
}

function AppointmentDetailContent() {
  const params = useParams();
  const searchParams = useSearchParams();
  const appointmentId = params.appointmentId as string;
  const showRebook = searchParams.get('rebook') === '1';

  const [appointment, setAppointment] = useState<Appointment | null>(null);
  const [workspaces, setWorkspaces] = useState<Workspace[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [startsAt, setStartsAt] = useState('');
  const [status, setStatus] = useState('');
  const [internalNotes, setInternalNotes] = useState('');
  const [depositInspect, setDepositInspect] = useState<DepositInspect | null>(null);
  const [depositMethod, setDepositMethod] = useState('card');
  const [waiveNotes, setWaiveNotes] = useState('');
  const [workspaceId, setWorkspaceId] = useState('');
  const [noShowReason, setNoShowReason] = useState('');
  const [correctionNote, setCorrectionNote] = useState('');
  const [rebookStartsAt, setRebookStartsAt] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      fetchAppointment(appointmentId),
      fetchWorkspaces(),
      fetchAppointmentDeposit(appointmentId).catch(() => null),
    ])
      .then(([appt, wss, deposit]) => {
        setAppointment(appt);
        setWorkspaces(wss);
        setDepositInspect(deposit);
        setStartsAt(appt.starts_at.slice(0, 16));
        setStatus(appt.status);
        setInternalNotes(appt.internal_notes ?? '');
        setWorkspaceId(appt.workspace_id ?? '');
        setNoShowReason(appt.no_show_reason ?? '');
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load'))
      .finally(() => setLoading(false));
  }, [appointmentId]);

  useEffect(() => {
    load();
  }, [load]);

  async function handleReschedule(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      await updateAppointment(appointmentId, {
        starts_at: startsAt,
        internal_notes: internalNotes,
      });
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Update failed');
    }
  }

  async function handleProposePostpone() {
    setError(null);
    try {
      await proposeAppointmentPostpone(appointmentId, {
        starts_at: new Date(startsAt).toISOString(),
      });
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Postpone request failed');
    }
  }

  async function handleStatus() {
    try {
      await updateAppointmentStatus(appointmentId, status, noShowReason || undefined);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Status update failed');
    }
  }

  async function handleCorrectStatus() {
    if (!correctionNote.trim()) {
      setError('Correction note is required');
      return;
    }
    try {
      await correctAppointmentStatus(appointmentId, status, correctionNote);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Correction failed');
    }
  }

  async function handleRebook(event: React.FormEvent) {
    event.preventDefault();
    try {
      const newAppt = await rebookAppointment(appointmentId, { starts_at: rebookStartsAt });
      window.location.href = `/admin/bookings/${newAppt.id}`;
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Rebook failed');
    }
  }

  async function handleWorkspaceReassign() {
    try {
      await reassignAppointmentWorkspace(appointmentId, workspaceId || null);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Workspace reassignment failed');
    }
  }

  async function handleCancel() {
    try {
      await cancelAppointment(appointmentId, 'Cancelled from admin');
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Cancel failed');
    }
  }

  const isTerminal = appointment
    ? ['cancelled', 'completed', 'no_show'].includes(appointment.status)
    : false;

  if (loading && !appointment) {
    return (
      <AdminBookingShell title="Appointment">
        <LoadingState />
      </AdminBookingShell>
    );
  }

  return (
    <AdminBookingShell title={appointment?.client?.resolved_display_name ?? 'Appointment'}>
      <p className="mb-4 text-sm">
        <Link href="/admin/bookings" className="text-zinc-600 hover:underline">
          ← Back to day board
        </Link>
      </p>
      {error ? <ErrorAlert message={error} /> : null}

      {appointment ? (
        <div className="grid gap-4 md:grid-cols-2">
          <Card title="Details">
            <dl className="space-y-2 text-sm">
              <div><dt className="text-zinc-500">Provider</dt><dd>{appointment.team_member?.display_name ?? 'Unassigned'}</dd></div>
              <div><dt className="text-zinc-500">Location</dt><dd>{appointment.location?.name}</dd></div>
              <div><dt className="text-zinc-500">Workspace</dt><dd>{appointment.workspace?.name ?? '—'}</dd></div>
              <div><dt className="text-zinc-500">Services</dt><dd>{appointment.services?.map((s) => s.service_name).join(', ')}</dd></div>
              <div><dt className="text-zinc-500">Source</dt><dd>{appointment.booking_source}{appointment.walk_in_stage ? ` (${appointment.walk_in_stage})` : ''}</dd></div>
              <div><dt className="text-zinc-500">Reference</dt><dd>{appointment.booking_reference ?? '—'}</dd></div>
              {appointment.rebooked_from_appointment_id ? (
                <div><dt className="text-zinc-500">Rebooked from</dt><dd><Link href={`/admin/bookings/${appointment.rebooked_from_appointment_id}`} className="underline">Prior appointment</Link></dd></div>
              ) : null}
              <div><dt className="text-zinc-500">Deposit</dt><dd>{appointment.deposit_status}{appointment.deposit_required_cents ? ` (£${(appointment.deposit_required_cents / 100).toFixed(2)} expected)` : ''}</dd></div>
              {appointment.no_show_reason ? (
                <div><dt className="text-zinc-500">No-show reason</dt><dd>{appointment.no_show_reason}</dd></div>
              ) : null}
              <div><dt className="text-zinc-500">End</dt><dd>{new Date(appointment.ends_at).toLocaleString()}</dd></div>
            </dl>
          </Card>

          <Card title="Reschedule">
            <form onSubmit={handleReschedule} className="grid gap-3">
              <Field label="Start">
                <input type="datetime-local" className={inputClass} value={startsAt} onChange={(e) => setStartsAt(e.target.value)} />
              </Field>
              <Field label="Internal notes">
                <textarea className={inputClass} rows={2} value={internalNotes} onChange={(e) => setInternalNotes(e.target.value)} />
              </Field>
              <div className="flex flex-wrap gap-2">
                <Button type="submit">Save changes</Button>
                <Button type="button" variant="secondary" onClick={() => void handleProposePostpone()}>
                  Propose to customer
                </Button>
              </div>
              <p className="text-xs text-[var(--admin-muted)]">
                “Propose to customer” sends WhatsApp/email/in-app Confirm/Decline (required when ≥15
                minutes remain). Decline keeps the original time.
              </p>
            </form>
          </Card>

          <Card title="Workspace">
            <div className="flex flex-wrap gap-2">
              <select className={inputClass} value={workspaceId} onChange={(e) => setWorkspaceId(e.target.value)}>
                <option value="">None</option>
                {workspaces.map((w) => (
                  <option key={w.id} value={w.id}>{w.name} ({w.workspace_type})</option>
                ))}
              </select>
              <Button type="button" onClick={handleWorkspaceReassign}>Reassign</Button>
            </div>
          </Card>

          {(showRebook || isTerminal) ? (
            <Card title="Rebook">
              <form onSubmit={handleRebook} className="grid gap-3">
                <Field label="New start">
                  <input type="datetime-local" className={inputClass} value={rebookStartsAt} onChange={(e) => setRebookStartsAt(e.target.value)} required />
                </Field>
                <Button type="submit">Create rebooked appointment</Button>
              </form>
            </Card>
          ) : null}

          <AppointmentPackagePanel appointmentId={appointmentId} clientId={appointment.client_id} />

          {appointment.deposit_status !== 'not_required' ? (
            <Card title="Deposit (Payments)">
              <p className="mb-2 text-xs text-zinc-500">
                Booking defines the requirement; Payments records collection via commerce deposit records.
              </p>
              <dl className="mb-3 space-y-1 text-sm">
                <div>
                  <dt className="text-zinc-500">Booking status</dt>
                  <dd>{appointment.deposit_status}</dd>
                </div>
                {depositInspect?.deposit_record ? (
                  <div>
                    <dt className="text-zinc-500">Commerce lifecycle</dt>
                    <dd>{String(depositInspect.deposit_record.lifecycle_state ?? '—')}</dd>
                  </div>
                ) : null}
              </dl>
              {['pending', 'failed'].includes(appointment.deposit_status) ? (
                <div className="mb-3 grid gap-2">
                  <Field label="Payment method">
                    <select className={inputClass} value={depositMethod} onChange={(e) => setDepositMethod(e.target.value)}>
                      <option value="card">Card</option>
                      <option value="cash">Cash</option>
                      <option value="bank_transfer">Bank transfer</option>
                    </select>
                  </Field>
                  <Button
                    type="button"
                    onClick={async () => {
                      await recordAppointmentDepositPayment(appointmentId, {
                        payment_method_type: depositMethod,
                      });
                      load();
                    }}
                  >
                    Record deposit payment
                  </Button>
                </div>
              ) : null}
              {['pending', 'failed'].includes(appointment.deposit_status) ? (
                <div className="mb-3 grid gap-2">
                  <Field label="Waiver notes">
                    <input className={inputClass} value={waiveNotes} onChange={(e) => setWaiveNotes(e.target.value)} />
                  </Field>
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={async () => {
                      await waiveAppointmentDeposit(appointmentId, waiveNotes || undefined);
                      load();
                    }}
                  >
                    Waive deposit
                  </Button>
                </div>
              ) : null}
              {appointment.deposit_status === 'satisfied' ? (
                <Button
                  type="button"
                  variant="secondary"
                  onClick={async () => {
                    await refundAppointmentDeposit(appointmentId, { reason: 'Admin deposit refund' });
                    load();
                  }}
                >
                  Refund deposit
                </Button>
              ) : null}
            </Card>
          ) : null}

          <Card title="Status">
            <div className="grid gap-2">
              <select className={inputClass} value={status} onChange={(e) => setStatus(e.target.value)}>
                {APPOINTMENT_STATUSES.map((s) => (
                  <option key={s} value={s}>{s.replace('_', ' ')}</option>
                ))}
              </select>
              {status === 'no_show' ? (
                <Field label="No-show reason">
                  <input className={inputClass} value={noShowReason} onChange={(e) => setNoShowReason(e.target.value)} />
                </Field>
              ) : null}
              {isTerminal ? (
                <Field label="Correction note">
                  <input className={inputClass} value={correctionNote} onChange={(e) => setCorrectionNote(e.target.value)} />
                </Field>
              ) : null}
              <div className="flex flex-wrap gap-2">
                {isTerminal ? (
                  <Button type="button" onClick={handleCorrectStatus}>Correct status</Button>
                ) : (
                  <Button type="button" onClick={handleStatus}>Update status</Button>
                )}
                {appointment.status === 'confirmed' ? (
                  <Button type="button" variant="secondary" onClick={() => updateAppointmentStatus(appointmentId, 'checked_in').then(load)}>
                    Quick check-in
                  </Button>
                ) : null}
              </div>
            </div>
          </Card>

          {appointment.status !== 'cancelled' ? (
            <Card title="Cancel">
              <Button type="button" variant="secondary" onClick={handleCancel}>
                Cancel appointment
              </Button>
            </Card>
          ) : null}
        </div>
      ) : null}
    </AdminBookingShell>
  );
}
