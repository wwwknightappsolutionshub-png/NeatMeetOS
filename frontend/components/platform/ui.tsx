import Link from 'next/link';
import type { ButtonHTMLAttributes, ReactNode, TableHTMLAttributes } from 'react';

/** Dark ops-console surface card. */
export function PlatformCard({
  title,
  description,
  children,
  className = '',
  padded = true,
  actions,
}: {
  title?: string;
  description?: string;
  children: ReactNode;
  className?: string;
  padded?: boolean;
  actions?: ReactNode;
}) {
  return (
    <section
      className={[
        'platform-ops-glow relative overflow-hidden rounded-xl border border-[var(--platform-line)] bg-[var(--platform-surface)] text-[var(--platform-fg)]',
        padded ? 'p-5' : 'p-0',
        className,
      ].join(' ')}
    >
      {title || actions ? (
        <div
          className={[
            'flex flex-wrap items-start justify-between gap-3 border-b border-[var(--platform-line-subtle)]',
            padded ? 'mb-4 pb-4' : 'px-5 py-4',
          ].join(' ')}
        >
          <div>
            {title ? (
              <h2 className="font-mono text-xs font-semibold uppercase tracking-[0.2em] text-[var(--platform-accent)]">
                {title}
              </h2>
            ) : null}
            {description ? (
              <p className="mt-1 text-sm text-[var(--platform-muted)]">{description}</p>
            ) : null}
          </div>
          {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
        </div>
      ) : null}
      <div className={!padded && (title || actions) ? 'px-0' : ''}>{children}</div>
    </section>
  );
}

export function PlatformField({
  label,
  hint,
  children,
}: {
  label: string;
  hint?: string;
  children: ReactNode;
}) {
  return (
    <label className="block text-sm">
      <span className="mb-1.5 block font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-[var(--platform-label)]">
        {label}
      </span>
      {children}
      {hint ? (
        <span className="mt-1.5 block text-xs text-[var(--platform-muted)]">{hint}</span>
      ) : null}
    </label>
  );
}

export const platformInputClass =
  'w-full rounded-md border border-[var(--platform-line)] bg-[var(--platform-input)] px-3 py-2 text-sm text-[var(--platform-fg)] placeholder:text-[var(--platform-muted)] outline-none transition focus:border-[var(--platform-accent)] focus:shadow-[0_0_0_3px_var(--platform-accent-soft)]';

export const platformSelectClass = platformInputClass;

export const platformTextareaClass = `${platformInputClass} resize-y min-h-[5rem]`;

const btnBase =
  'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-45';

export const platformBtnPrimaryClass = `${btnBase} bg-[var(--platform-accent)] text-[#041014] shadow-[0_0_20px_-6px_var(--platform-glow)] hover:brightness-110`;

export const platformBtnSecondaryClass = `${btnBase} border border-[var(--platform-line)] bg-[var(--platform-surface-elevated)] text-[var(--platform-fg)] hover:border-[var(--platform-accent)] hover:text-white`;

export const platformBtnGhostClass = `${btnBase} border border-transparent bg-transparent text-[var(--platform-label)] hover:border-[var(--platform-line-subtle)] hover:bg-white/[0.04] hover:text-white`;

export const platformBtnDangerClass = `${btnBase} border border-[var(--platform-danger)]/40 bg-[var(--platform-danger)]/10 text-[#ffb4af] hover:bg-[var(--platform-danger)]/20`;

export function PlatformButton({
  children,
  variant = 'primary',
  className = '',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  children: ReactNode;
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
}) {
  const styles =
    variant === 'primary'
      ? platformBtnPrimaryClass
      : variant === 'secondary'
        ? platformBtnSecondaryClass
        : variant === 'danger'
          ? platformBtnDangerClass
          : platformBtnGhostClass;
  return (
    <button type="button" className={`${styles} ${className}`} {...props}>
      {children}
    </button>
  );
}

export function PlatformLinkButton({
  href,
  children,
  variant = 'secondary',
  className = '',
}: {
  href: string;
  children: ReactNode;
  variant?: 'primary' | 'secondary' | 'ghost';
  className?: string;
}) {
  const styles =
    variant === 'primary'
      ? platformBtnPrimaryClass
      : variant === 'ghost'
        ? platformBtnGhostClass
        : platformBtnSecondaryClass;
  return (
    <Link href={href} className={`${styles} ${className}`}>
      {children}
    </Link>
  );
}

export function PlatformPageIntro({
  title,
  description,
  eyebrow = 'Ops console',
  actions,
}: {
  title: string;
  description: string;
  eyebrow?: string;
  actions?: ReactNode;
}) {
  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.24em] text-[var(--platform-accent)]">
          {eyebrow}
        </p>
        <h1 className="mt-2 text-2xl font-semibold tracking-tight text-white sm:text-3xl">{title}</h1>
        <p className="mt-2 max-w-2xl text-sm leading-relaxed text-[var(--platform-label)]">
          {description}
        </p>
      </div>
      {actions ? <div className="flex shrink-0 flex-wrap gap-2">{actions}</div> : null}
    </div>
  );
}

export function PlatformPage({
  children,
  width = '6xl',
}: {
  children: ReactNode;
  width?: '2xl' | '4xl' | '5xl' | '6xl';
}) {
  const max = {
    '2xl': 'max-w-2xl',
    '4xl': 'max-w-4xl',
    '5xl': 'max-w-5xl',
    '6xl': 'max-w-6xl',
  }[width];
  return <div className={`mx-auto grid w-full ${max} gap-5`}>{children}</div>;
}

export function PlatformStatCard({
  label,
  value,
  hint,
  tone = 'default',
}: {
  label: string;
  value: string;
  hint?: string;
  tone?: 'default' | 'success' | 'warning' | 'danger';
}) {
  const toneBorder =
    tone === 'success'
      ? 'border-[var(--platform-success)]/30'
      : tone === 'warning'
        ? 'border-[var(--platform-warning)]/30'
        : tone === 'danger'
          ? 'border-[var(--platform-danger)]/30'
          : 'border-[var(--platform-line)]';
  return (
    <div
      className={`platform-ops-glow rounded-xl border ${toneBorder} bg-[var(--platform-surface)] p-4`}
    >
      <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.18em] text-[var(--platform-muted)]">
        {label}
      </p>
      <p className="mt-2 font-mono text-2xl font-semibold tracking-tight text-white">{value}</p>
      {hint ? <p className="mt-1.5 text-xs text-[var(--platform-label)]">{hint}</p> : null}
    </div>
  );
}

export function PlatformErrorAlert({ message }: { message: string }) {
  return (
    <div
      role="alert"
      className="rounded-lg border border-[var(--platform-danger)]/40 bg-[var(--platform-danger)]/10 px-4 py-3 text-sm text-[#ffb4af]"
    >
      {message}
    </div>
  );
}

export function PlatformSuccessAlert({ message }: { message: string }) {
  return (
    <div className="rounded-lg border border-[var(--platform-success)]/35 bg-[var(--platform-success)]/10 px-4 py-3 text-sm text-[#9be9a8]">
      {message}
    </div>
  );
}

export function PlatformLoadingState({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="flex items-center gap-3 rounded-lg border border-[var(--platform-line-subtle)] bg-[var(--platform-surface-elevated)] px-4 py-6">
      <span className="relative flex h-2.5 w-2.5">
        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[var(--platform-accent)] opacity-60" />
        <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-[var(--platform-accent)]" />
      </span>
      <span className="font-mono text-xs uppercase tracking-[0.14em] text-[var(--platform-label)]">
        {label}
      </span>
    </div>
  );
}

export function PlatformEmptyState({ message }: { message: string }) {
  return (
    <div className="rounded-lg border border-dashed border-[var(--platform-line)] px-4 py-10 text-center">
      <p className="font-mono text-xs uppercase tracking-[0.16em] text-[var(--platform-muted)]">
        No data
      </p>
      <p className="mt-2 text-sm text-[var(--platform-label)]">{message}</p>
    </div>
  );
}

type BadgeTone = 'default' | 'success' | 'warning' | 'danger' | 'info';

export function PlatformBadge({
  children,
  tone = 'default',
}: {
  children: ReactNode;
  tone?: BadgeTone;
}) {
  const cls =
    tone === 'success'
      ? 'border-[var(--platform-success)]/35 bg-[var(--platform-success)]/12 text-[#9be9a8]'
      : tone === 'warning'
        ? 'border-[var(--platform-warning)]/35 bg-[var(--platform-warning)]/12 text-[#f0d58c]'
        : tone === 'danger'
          ? 'border-[var(--platform-danger)]/35 bg-[var(--platform-danger)]/12 text-[#ffb4af]'
          : tone === 'info'
            ? 'border-[var(--platform-accent)]/35 bg-[var(--platform-accent-soft)] text-[var(--platform-accent)]'
            : 'border-[var(--platform-line)] bg-white/[0.04] text-[var(--platform-label)]';
  return (
    <span
      className={`inline-flex items-center rounded-md border px-2 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-[0.12em] ${cls}`}
    >
      {children}
    </span>
  );
}

export function tenantStatusTone(status: string): BadgeTone {
  switch (status) {
    case 'active':
      return 'success';
    case 'trial':
      return 'warning';
    case 'pending_activation':
      return 'info';
    case 'suspended':
    case 'inactive':
      return 'danger';
    default:
      return 'default';
  }
}

export function PlatformTable({
  children,
  className = '',
  ...props
}: TableHTMLAttributes<HTMLTableElement>) {
  return (
    <div className="overflow-x-auto">
      <table
        className={`min-w-full text-left text-sm ${className}`}
        {...props}
      >
        {children}
      </table>
    </div>
  );
}

export function PlatformTableHead({ children }: { children: ReactNode }) {
  return (
    <thead className="border-b border-[var(--platform-line)] bg-[var(--platform-surface-elevated)] font-mono text-[10px] uppercase tracking-[0.14em] text-[var(--platform-muted)]">
      {children}
    </thead>
  );
}

export function PlatformCodeBlock({ children }: { children: string }) {
  return (
    <pre className="overflow-x-auto rounded-md border border-[var(--platform-line-subtle)] bg-[#06080b] p-3 font-mono text-xs leading-relaxed text-[var(--platform-label)]">
      {children}
    </pre>
  );
}

export function PlatformModalBackdrop({ children }: { children: ReactNode }) {
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-[#020305]/80 p-4 backdrop-blur-sm sm:items-center">
      {children}
    </div>
  );
}

export function PlatformModalPanel({
  title,
  subtitle,
  children,
  onClose,
  className = '',
}: {
  title: string;
  subtitle?: string;
  children: ReactNode;
  onClose?: () => void;
  className?: string;
}) {
  return (
    <div
      className={`platform-ops-glow max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-[var(--platform-line)] bg-[var(--platform-surface)] p-5 text-[var(--platform-fg)] ${className}`}
    >
      <div className="mb-4 flex items-start justify-between gap-3">
        <div>
          <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-[var(--platform-accent)]">
            {title}
          </p>
          {subtitle ? <p className="mt-1 text-sm text-[var(--platform-muted)]">{subtitle}</p> : null}
        </div>
        {onClose ? (
          <PlatformButton variant="ghost" className="!px-2 !py-1" onClick={onClose}>
            ✕
          </PlatformButton>
        ) : null}
      </div>
      {children}
    </div>
  );
}
