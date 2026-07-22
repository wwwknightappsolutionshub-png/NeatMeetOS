import { formatDateTime, type NotificationMessageAttempt } from '@/lib/notifications-types';
import { NotificationStatusBadge } from './badges';

export function NotificationAttemptsTable({ attempts }: { attempts?: NotificationMessageAttempt[] }) {
  if (!attempts || attempts.length === 0) {
    return <p className="text-sm text-zinc-500">No dispatch attempts recorded.</p>;
  }

  return (
    <table className="w-full text-left text-sm">
      <thead>
        <tr className="border-b text-zinc-500">
          <th className="py-2">#</th>
          <th>Status</th>
          <th>Provider</th>
          <th>Reference</th>
          <th>Attempted</th>
        </tr>
      </thead>
      <tbody>
        {attempts.map((attempt) => (
          <tr key={attempt.id} className="border-b border-zinc-100">
            <td className="py-2 text-zinc-500">{attempt.attempt_number}</td>
            <td>
              <NotificationStatusBadge status={attempt.status} />
            </td>
            <td className="text-zinc-600">{attempt.provider ?? '—'}</td>
            <td className="text-zinc-500">{attempt.provider_reference ?? '—'}</td>
            <td className="text-zinc-500">{formatDateTime(attempt.attempted_at)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
