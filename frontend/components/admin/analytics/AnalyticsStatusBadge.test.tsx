/** @vitest-environment jsdom */
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { AnalyticsStatusBadge } from './AnalyticsStatusBadge';

afterEach(() => {
  cleanup();
});

describe('AnalyticsStatusBadge', () => {
  it('renders the label', () => {
    render(<AnalyticsStatusBadge label="Completed" />);
    expect(screen.getByText('Completed')).toBeTruthy();
  });

  it('defaults to zinc tone classes', () => {
    render(<AnalyticsStatusBadge label="Pending" />);
    const el = screen.getByText('Pending');
    expect(el.className).toContain('bg-zinc-200');
    expect(el.className).toContain('text-zinc-600');
  });

  it('applies green tone classes', () => {
    render(<AnalyticsStatusBadge label="Done" tone="green" />);
    const el = screen.getByText('Done');
    expect(el.className).toContain('bg-emerald-100');
    expect(el.className).toContain('text-emerald-800');
  });
});
