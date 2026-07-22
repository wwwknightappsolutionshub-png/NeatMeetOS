import type { ReactNode } from 'react';

interface CardProps {
  title?: string;
  children: ReactNode;
  className?: string;
}

export function Card({ title, children, className = '' }: CardProps) {
  return (
    <section
      className={`rounded-2xl border border-[var(--admin-line)] bg-[var(--admin-surface)] p-5 text-[var(--admin-ink)] shadow-[0_1px_2px_rgba(28,25,23,0.04)] ${className}`}
    >
      {title ? (
        <h2 className="mb-3 text-sm font-semibold tracking-tight text-current">{title}</h2>
      ) : null}
      {children}
    </section>
  );
}
