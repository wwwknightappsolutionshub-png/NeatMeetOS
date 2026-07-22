'use client';

import { Card } from '@/components/ui/Card';
import type { CheckoutLine } from '@/lib/pos-types';
import { formatMoneyCents } from '@/lib/pos-types';

interface CheckoutLinesTableProps {
  lines: CheckoutLine[];
  editable?: boolean;
  onRemove?: (lineId: string) => void;
}

export function CheckoutLinesTable({ lines, editable, onRemove }: CheckoutLinesTableProps) {
  return (
    <Card title="Basket">
      {lines.length === 0 ? (
        <p className="text-sm text-zinc-500">No lines yet. Import an appointment or add services / retail items.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b border-zinc-200 text-left text-zinc-500">
                <th className="py-2 pr-3">Item</th>
                <th className="py-2 pr-3">Type</th>
                <th className="py-2 pr-3">Qty</th>
                <th className="py-2 pr-3">Unit</th>
                <th className="py-2 pr-3">Total</th>
                {editable ? <th className="py-2" /> : null}
              </tr>
            </thead>
            <tbody>
              {lines.map((line) => (
                <tr key={line.id} className="border-b border-zinc-100">
                  <td className="py-2 pr-3">{line.description}</td>
                  <td className="py-2 pr-3 capitalize">{line.line_type.replace(/_/g, ' ')}</td>
                  <td className="py-2 pr-3">{line.quantity}</td>
                  <td className="py-2 pr-3">{formatMoneyCents(line.unit_price_cents)}</td>
                  <td className="py-2 pr-3">{formatMoneyCents(line.line_total_cents)}</td>
                  {editable && onRemove && line.line_type !== 'deposit_credit' ? (
                    <td className="py-2">
                      <button type="button" className="text-red-600 hover:underline" onClick={() => onRemove(line.id)}>
                        Remove
                      </button>
                    </td>
                  ) : editable ? <td className="py-2" /> : null}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}
