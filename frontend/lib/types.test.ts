import { describe, expect, it } from 'vitest';
import type { ApiResponse } from '@/lib/types';

describe('API types contract', () => {
  it('accepts standard success envelope', () => {
    const payload: ApiResponse<{ status: string }> = {
      success: true,
      message: 'OK',
      data: { status: 'healthy' },
    };

    expect(payload.success).toBe(true);
    expect(payload.data.status).toBe('healthy');
  });
});
