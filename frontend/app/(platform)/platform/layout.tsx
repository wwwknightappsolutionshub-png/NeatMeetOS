import type { ReactNode } from 'react';
import { PlatformAppShell } from '@/components/platform/PlatformAppShell';

export default function PlatformLayout({ children }: { children: ReactNode }) {
  return <PlatformAppShell>{children}</PlatformAppShell>;
}
