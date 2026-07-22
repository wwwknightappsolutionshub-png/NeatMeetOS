import type { ReactNode } from 'react';
import { SmartErrorAlert } from '@/components/admin/ModuleUpgradeGate';

interface StatusBadgeProps {
  active: boolean;
}

export function StatusBadge({ active }: StatusBadgeProps) {
  return (
    <span
      className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
        active ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-200 text-zinc-600'
      }`}
    >
      {active ? 'Active' : 'Inactive'}
    </span>
  );
}

interface EmptyStateProps {
  message: string;
}

export function EmptyState({ message }: EmptyStateProps) {
  return <p className="text-sm text-zinc-500">{message}</p>;
}

interface ErrorAlertProps {
  message: string;
}

export function ErrorAlert({ message }: ErrorAlertProps) {
  return <SmartErrorAlert message={message} />;
}

interface LoadingStateProps {
  label?: string;
}

export function LoadingState({ label = 'Loading…' }: LoadingStateProps) {
  return <p className="text-sm text-zinc-500">{label}</p>;
}

interface FieldProps {
  label: string;
  children: ReactNode;
}

export function Field({ label, children }: FieldProps) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block text-zinc-600">{label}</span>
      {children}
    </label>
  );
}

export const inputClass =
  'w-full rounded-md border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-500 focus:outline-none';
