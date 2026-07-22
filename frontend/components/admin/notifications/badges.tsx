import {
  channelLabel,
  humanizeToken,
  purposeLabel,
  statusTone,
} from '@/lib/notifications-types';

export function NotificationStatusBadge({ status }: { status?: string | null }) {
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusTone(status)}`}>
      {humanizeToken(status)}
    </span>
  );
}

export function NotificationChannelBadge({ channel }: { channel?: string | null }) {
  return (
    <span className="inline-flex rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700">
      {channelLabel(channel)}
    </span>
  );
}

export function NotificationPurposeBadge({ purpose }: { purpose?: string | null }) {
  return (
    <span className="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
      {purposeLabel(purpose)}
    </span>
  );
}
