export type PasswordRuleId = 'length' | 'upper' | 'lower' | 'number' | 'special';

export interface PasswordRule {
  id: PasswordRuleId;
  label: string;
  test: (password: string) => boolean;
}

/** Caps + lower + number + special character (min 8). */
export const PASSWORD_RULES: PasswordRule[] = [
  {
    id: 'length',
    label: 'At least 8 characters',
    test: (password) => password.length >= 8,
  },
  {
    id: 'upper',
    label: 'One uppercase letter (A–Z)',
    test: (password) => /[A-Z]/.test(password),
  },
  {
    id: 'lower',
    label: 'One lowercase letter (a–z)',
    test: (password) => /[a-z]/.test(password),
  },
  {
    id: 'number',
    label: 'One number (0–9)',
    test: (password) => /\d/.test(password),
  },
  {
    id: 'special',
    label: 'One special character (!@#$…)',
    test: (password) => /[^A-Za-z0-9]/.test(password),
  },
];

export function evaluatePasswordRules(password: string): Record<PasswordRuleId, boolean> {
  return PASSWORD_RULES.reduce(
    (acc, rule) => {
      acc[rule.id] = rule.test(password);
      return acc;
    },
    {} as Record<PasswordRuleId, boolean>,
  );
}

export function isPasswordSecure(password: string): boolean {
  return PASSWORD_RULES.every((rule) => rule.test(password));
}

export function passwordSecurityMessage(password: string): string | null {
  if (isPasswordSecure(password)) return null;
  const missing = PASSWORD_RULES.filter((rule) => !rule.test(password)).map((rule) => rule.label);
  return `Password needs: ${missing.join(', ')}.`;
}
