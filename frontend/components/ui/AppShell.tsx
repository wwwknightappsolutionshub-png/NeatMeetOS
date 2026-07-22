import Link from 'next/link';
import type { ReactNode } from 'react';

interface AppShellProps {
  title: string;
  workspace: string;
  children: ReactNode;
}

const navItems = [
  { href: '/admin/dashboard', label: 'Admin', workspace: 'admin' },
  { href: '/desk', label: 'Front Desk', workspace: 'desk' },
  { href: '/provider', label: 'Provider', workspace: 'provider' },
  { href: '/health', label: 'Health', workspace: 'public' },
];

export function AppShell({ title, workspace, children }: AppShellProps) {
  return (
    <div className="flex min-h-full flex-col bg-zinc-50 text-zinc-900">
      <header className="border-b border-zinc-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
          <div>
            <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">
              NeatMeet OS
            </p>
            <h1 className="text-lg font-semibold">{title}</h1>
          </div>
          <nav className="flex flex-wrap gap-2">
            {navItems.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className={`rounded-md px-3 py-1.5 text-sm ${
                  item.workspace === workspace
                    ? 'bg-zinc-900 text-white'
                    : 'text-zinc-600 hover:bg-zinc-100'
                }`}
              >
                {item.label}
              </Link>
            ))}
          </nav>
        </div>
      </header>
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6">{children}</main>
    </div>
  );
}
