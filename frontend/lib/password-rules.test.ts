import { describe, expect, it } from 'vitest';
import {
  evaluatePasswordRules,
  isPasswordSecure,
  passwordSecurityMessage,
} from './password-rules';

describe('password-rules', () => {
  it('requires upper, lower, number, and special', () => {
    expect(isPasswordSecure('password')).toBe(false);
    expect(isPasswordSecure('Password1')).toBe(false);
    expect(isPasswordSecure('Password1!')).toBe(true);
    expect(evaluatePasswordRules('Password1!')).toEqual({
      length: true,
      upper: true,
      lower: true,
      number: true,
      special: true,
    });
  });

  it('returns a helpful message when rules fail', () => {
    expect(passwordSecurityMessage('short')).toContain('At least 8 characters');
    expect(passwordSecurityMessage('Password1!')).toBeNull();
  });
});
