import Link from 'next/link';
import type { ReactNode } from 'react';

interface AnalyticsSectionCardProps {
  title: string;
  href?: string;
  children: ReactNode;
}

/**
 * A titled section card used on the overview dashboard. When `href` is provided
 * the title becomes a link into the deeper analytics subpage.
 */
export function AnalyticsSectionCard({ title, href, children }: AnalyticsSectionCardProps) {
  return (
    <section className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-zinc-700">{title}</h2>
        {href ? (
          <Link href={href} className="text-xs text-zinc-600 underline">
            View
          </Link>
        ) : null}
      </div>
      {children}
    </section>
  );
}
