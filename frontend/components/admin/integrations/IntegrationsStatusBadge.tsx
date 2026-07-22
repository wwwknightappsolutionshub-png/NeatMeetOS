import type { BadgeTone } from '@/lib/integrations-types';

const TONE_CLASSES: Record<BadgeTone, string> = {
  green: 'bg-emerald-100 text-emerald-800',
  amber: 'bg-amber-100 text-amber-800',
  red: 'bg-red-100 text-red-700',
  zinc: 'bg-zinc-200 text-zinc-600',
};

interface IntegrationsStatusBadgeProps {
  label: string;
  tone?: BadgeTone;
}

export function IntegrationsStatusBadge({ label, tone = 'zinc' }: IntegrationsStatusBadgeProps) {
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${TONE_CLASSES[tone]}`}>
      {label}
    </span>
  );
}
