'use client';

import Link from 'next/link';
import type { Appointment } from '@/lib/booking-types';
import { Button } from '@/components/ui/Button';

interface AppointmentBoardCardProps {
  appointment: Appointment;
  onCheckIn?: (id: string) => void;
  onNoShow?: (id: string) => void;
  onCancel?: (id: string) => void;
}

function statusBadgeClass(status: string): string {
  switch (status) {
    case 'checked_in':
      return 'bg-emerald-100 text-emerald-800';
    case 'no_show':
      return 'bg-red-100 text-red-800';
    case 'cancelled':
      return 'bg-zinc-200 text-zinc-600';
    case 'completed':
      return 'bg-blue-100 text-blue-800';
    default:
      return 'bg-zinc-100 text-zinc-700';
  }
}

export function AppointmentBoardCard({
  appointment,
  onCheckIn,
  onNoShow,
  onCancel,
}: AppointmentBoardCardProps) {
  const timeLabel = new Date(appointment.starts_at).toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  });

  return (
    <article className="rounded-lg border border-zinc-200 bg-white p-3 text-sm shadow-sm">
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="font-medium">
            {timeLabel} · {appointment.client?.resolved_display_name ?? 'Client'}
          </p>
          <p className="text-xs text-zinc-500">
            {appointment.team_member?.display_name ?? 'Unassigned'}
            {appointment.workspace ? ` · ${appointment.workspace.name}` : ''}
          </p>
          <p className="mt-1 text-xs text-zinc-400">
            {appointment.services?.map((s) => s.service_name).join(', ') || '—'}
          </p>
          <p className="text-xs text-zinc-400">
            {appointment.booking_source.replace('_', ' ')}
            {appointment.walk_in_stage ? ` · walk-in ${appointment.walk_in_stage}` : ''}
            {appointment.deposit_status !== 'not_required'
              ? ` · deposit ${appointment.deposit_status}`
              : ''}
          </p>
        </div>
        <span className={`rounded px-2 py-0.5 text-xs capitalize ${statusBadgeClass(appointment.status)}`}>
          {appointment.status.replace('_', ' ')}
        </span>
      </div>

      <div className="mt-2 flex flex-wrap gap-1">
        <Link href={`/admin/bookings/${appointment.id}`} className="text-xs underline">
          Detail
        </Link>
        <Link href={`/admin/bookings/${appointment.id}?rebook=1`} className="text-xs underline">
          Rebook
        </Link>
        {appointment.status === 'confirmed' && onCheckIn ? (
          <Button type="button" variant="secondary" onClick={() => onCheckIn(appointment.id)}>
            Check in
          </Button>
        ) : null}
        {['pending', 'confirmed'].includes(appointment.status) && onNoShow ? (
          <Button type="button" variant="secondary" onClick={() => onNoShow(appointment.id)}>
            No-show
          </Button>
        ) : null}
        {!['cancelled', 'completed', 'no_show'].includes(appointment.status) && onCancel ? (
          <Button type="button" variant="secondary" onClick={() => onCancel(appointment.id)}>
            Cancel
          </Button>
        ) : null}
      </div>
    </article>
  );
}
