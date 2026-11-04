import { defineConfig, devices } from '@playwright/test';

const port = Number(process.env.E2E_PORT ?? 8799);

export const owner = {
    email: process.env.FOCUS_OWNER_EMAIL ?? 'owner@lovince.test',
    password: process.env.FOCUS_OWNER_PASSWORD ?? 'e2e-only-Passw0rd!',
};

export default defineConfig({
    testDir: 'e2e',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        trace: 'retain-on-failure',
        timezoneId: 'Africa/Nairobi',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: 'e2e/serve.sh',
        url: `http://127.0.0.1:${port}/up`,
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            E2E_PORT: String(port),
            FOCUS_OWNER_EMAIL: owner.email,
            FOCUS_OWNER_PASSWORD: owner.password,
        },
    },
});
