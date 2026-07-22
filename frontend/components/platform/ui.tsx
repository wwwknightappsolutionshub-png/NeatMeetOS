import type { ButtonHTMLAttributes, ReactNode } from 'react';

/** Dark-surface card for Platform control (avoids admin white Card + light labels). */
export function PlatformCard({
  title,
  children,
  className = '',
  padded = true,
}: {
  title?: string;
  children: ReactNode;
  className?: string;
  padded?: boolean;
}) {
  return (
    <section
      className={[
        'rounded-2xl border border-[var(--platform-line)] bg-[var(--platform-surface)] text-[var(--platform-fg)] shadow-none',
        padded ? 'p-5' : 'p-0',
        className,
      ].join(' ')}
    >
      {title ? (
        <h2 className="mb-3 text-sm font-semibold tracking-tight text-white">{title}</h2>
      ) : null}
      {children}
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
      <span className="mb-1.5 block font-medium text-[var(--platform-label)]">{label}</span>
      {children}
      {hint ? <span className="mt-1.5 block text-xs text-[var(--platform-muted)]">{hint}</span> : null}
    </label>
  );
}

export const platformInputClass =
  'w-full rounded-lg border border-[var(--platform-line)] bg-[var(--platform-input)] px-3 py-2 text-sm text-white placeholder:text-stone-500 outline-none focus:border-amber-500/70 focus:ring-1 focus:ring-amber-500/40';

export const platformBtnPrimaryClass =
  'inline-flex items-center justify-center rounded-lg bg-[var(--platform-accent)] px-4 py-2 text-sm font-semibold text-white hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50';

export const platformBtnSecondaryClass =
  'inline-flex items-center justify-center rounded-lg border border-[var(--platform-line)] bg-transparent px-4 py-2 text-sm font-semibold text-stone-100 hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-50';

export function PlatformButton({
  children,
  variant = 'primary',
  className = '',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  children: ReactNode;
  variant?: 'primary' | 'secondary';
}) {
  const styles = variant === 'primary' ? platformBtnPrimaryClass : platformBtnSecondaryClass;
  return (
    <button type="button" className={`${styles} ${className}`} {...props}>
      {children}
    </button>
  );
}

export function PlatformPageIntro({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  return (
    <div>
      <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-400">
        Platform
      </p>
      <h1 className="mt-1 text-2xl font-semibold tracking-tight text-white">{title}</h1>
      <p className="mt-1 max-w-2xl text-sm leading-relaxed text-stone-300">{description}</p>
    </div>
  );
}
