import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import { TurnstileBootstrap } from '@/components/security/TurnstileBootstrap';
import './book.css';

export const metadata: Metadata = {
  title: 'Book online · NeatMeet OS',
  description: 'Book your salon appointment online',
};

export default function BookLayout({ children }: { children: ReactNode }) {
  return (
    <div className="book-portal min-h-full">
      <TurnstileBootstrap />
      {children}
    </div>
  );
}
