import { test, expect } from '@playwright/test';

async function login(page, email, password = 'Browser-test-password-123!') {
    await page.goto('/login');
    await page.getByLabel(/^Email/).fill(email);
    await page.getByLabel(/^Password/).fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

async function switchMode(page, mode) {
    const submitted = page.waitForResponse(response => response.url().endsWith('/account/access-mode') && response.request().method() === 'POST');
    await page.getByRole('combobox', { name: 'Account access', exact: true }).selectOption(mode);
    expect((await submitted).status()).toBe(302);
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('combobox', { name: 'Account access', exact: true })).toHaveValue(mode);
}

test('administrator switches between Admin, Staff and HR with real access limits and can always restore Admin', async ({ page, browser }) => {
    await login(page, 'browser-admin@example.test');
    const access = page.getByRole('combobox', { name: 'Account access', exact: true });
    await expect(access).toHaveValue('admin');
    expect(await page.getByRole('switch', { name: 'Switch color theme' }).evaluate(toggle => toggle.nextElementSibling.classList.contains('account-access-switch'))).toBe(true);
    await expect(page.getByRole('link', { name: 'Raw attendance punches', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Staff directory', exact: true })).toBeVisible();

    const independent = await browser.newContext();
    const otherSession = await independent.newPage();
    await login(otherSession, 'browser-admin@example.test');

    await switchMode(page, 'staff');
    await expect(page.getByRole('link', { name: 'Raw attendance punches', exact: true })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Staff directory', exact: true })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Careers', exact: true })).toBeVisible();
    const limited = await page.request.get('/hr/raw-attendance/data');
    expect(limited.status()).toBe(403);
    await page.reload();
    await expect(access).toHaveValue('staff');
    await otherSession.goto('/hr/raw-attendance');
    await expect(otherSession.getByRole('heading', { name: 'Raw attendance punches', exact: true })).toBeVisible();
    await expect(otherSession.getByRole('combobox', { name: 'Account access', exact: true })).toHaveValue('admin');

    await page.getByRole('link', { name: 'Account', exact: true }).click();
    await expect(page.getByRole('combobox', { name: 'Account access', exact: true })).toHaveValue('staff');
    await switchMode(page, 'hr');
    await expect(page.getByRole('link', { name: 'Staff directory', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Careers & recruitment', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Raw attendance punches', exact: true })).toHaveCount(0);
    expect((await page.request.get('/attendance/office-punch-windows')).status()).toBe(403);
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(access).toBeVisible();
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'storage/framework/testing/access-mode-hr-mobile.png', fullPage: true });

    await switchMode(page, 'admin');
    expect((await page.request.get('/hr/raw-attendance/data')).status()).toBe(200);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await expect(page.getByRole('link', { name: 'Raw attendance punches', exact: true })).toBeVisible();
    await independent.close();
});

test('staff users have no access switch and cannot submit a forged mode change', async ({ page }) => {
    await login(page, 'employee1@example.test', 'password');
    await expect(page.getByRole('combobox', { name: 'Account access', exact: true })).toHaveCount(0);
    const result = await page.evaluate(async () => {
        const response = await fetch('/account/access-mode', {
            method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ mode: 'admin' }),
        });
        return response.status;
    });
    expect(result).toBe(403);
});
