import type { ButtonHTMLAttributes, ReactNode } from 'react';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  children: ReactNode;
  variant?: 'primary' | 'secondary';
}

export function Button({
  children,
  variant = 'primary',
  className = '',
  ...props
}: ButtonProps) {
  const base =
    'inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold tracking-tight transition disabled:opacity-50';
  const styles =
    variant === 'primary'
      ? 'bg-[var(--admin-accent)] text-white hover:brightness-110'
      : 'border border-[var(--admin-line)] bg-white text-[var(--admin-ink)] hover:bg-[var(--admin-wash)]';

  return (
    <button className={`${base} ${styles} ${className}`} {...props}>
      {children}
    </button>
  );
}
