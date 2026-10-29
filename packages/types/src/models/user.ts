import type { Settings } from '../generated/contract';

export interface User {
    id: number;
    name: string;
    email: string;
    /** Same-origin, owner-only URL, or null when no photo is set. */
    avatar_url: string | null;
}

/** The state of this device's sign-in, as reported by `GET /auth/me`. */
export interface AuthSession {
    /** "Keep me signed in" is active on this device. */
    remembered: boolean;
    /** When the sign-in lapses if Focus isn't used (renews with use). */
    expires_at: string;
    idle_timeout_minutes: number;
    remember_days: number;
}

export interface Me {
    user: User;
    session: AuthSession;
    settings: Settings;
}
