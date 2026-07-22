'use client';

import Link from 'next/link';
import type { ReactNode } from 'react';

export interface AdminModuleLink {
  href: string;
  label: string;
  active: boolean;
}

interface AdminModuleChromeProps {
  eyebrow: string;
  title: string;
  links: AdminModuleLink[];
  children: ReactNode;
}

/** Module title + secondary nav inside AdminAppShell (no page chrome / auth). */
export function AdminModuleChrome({
  eyebrow,
  title,
  links,
  children,
}: AdminModuleChromeProps) {
  return (
    <div>
      <div className="mb-6 border-b border-zinc-200 pb-4">
        <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">
          {eyebrow}
        </p>
        <div className="mt-1 flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-lg font-semibold text-zinc-900">{title}</h1>
          {links.length > 0 ? (
            <nav className="flex flex-wrap gap-3 text-sm">
              {links.map((link) => (
                <Link
                  key={link.href}
                  href={link.href}
                  className={
                    link.active
                      ? 'font-medium text-zinc-900 underline'
                      : 'text-zinc-600 hover:underline'
                  }
                >
                  {link.label}
                </Link>
              ))}
            </nav>
          ) : null}
        </div>
      </div>
      {children}
    </div>
  );
}
