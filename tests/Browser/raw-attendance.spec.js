import { test, expect } from '@playwright/test';

async function login(page, email, password) {
    await page.goto('/login');
    await page.getByLabel(/^Email/).fill(email);
    await page.getByLabel(/^Password/).fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

test('admin corrects raw captures with seconds and reason, then reviews history without a page reload', async ({ page }) => {
    await login(page, 'browser-admin@example.test', 'Browser-test-password-123!');
    await page.getByRole('link', { name: 'Raw attendance punches', exact: true }).click();
    const table = page.locator('#raw-punch-table');
    await table.getByLabel('Search records').fill('Example Employee 02');
    const row = table.locator('tbody tr').filter({ hasText: 'EXAMPLE-READER' });
    await expect(row).toContainText('08:00:59');
    await row.getByRole('button', { name: 'Edit', exact: true }).click();
    const modal = page.locator('#raw-punch-modal');
    const time = modal.getByLabel('Punch time (Asia/Manila)');
    expect(await time.inputValue()).toMatch(/T08:00:59$/);
    const date = (await time.inputValue()).slice(0, 10);
    await time.fill(`${date}T08:15:59`);
    await modal.getByLabel('Reason for correction').fill('Browser verified reader offset');
    await page.evaluate(() => { window.rawPunchPageMarker = 'same-page'; });
    const saved = page.waitForResponse(response => response.url().includes('/hr/raw-attendance/') && response.request().method() === 'PUT');
    await modal.getByRole('button', { name: 'Save correction', exact: true }).click();
    expect((await saved).status()).toBe(200);
    await expect(modal).not.toBeVisible();
    await expect(row).toContainText('08:15:59');
    expect(await page.evaluate(() => window.rawPunchPageMarker)).toBe('same-page');
    await row.getByRole('button', { name: 'History', exact: true }).click();
    const history = page.locator('#raw-history-modal');
    await expect(history).toContainText('Browser verified reader offset');
    await expect(history).toContainText(`${date} 08:00:59 → ${date} 08:15:59`);
    await expect(history.locator('section > p').first()).toContainText('Administrator');
    await history.getByRole('button', { name: 'Close', exact: true }).click();
    await expect(history).not.toBeVisible();
    await expect(page.locator('.modal-backdrop')).toHaveCount(0);
    const dtr = await page.evaluate(async ({ date }) => {
        const employees = await window.HRIS.request('/employees/lookup?search=Example%20Employee%2002');
        const employee = employees.data.find(item => item.name === 'Example Employee 02');
        return window.HRIS.request(`/attendance/${employee.id}/data?from=${date}&to=${date}`);
    }, { date });
    expect(dtr.data[0].time_in).toBe('08:15:59');
    await page.setViewportSize({ width: 390, height: 844 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'storage/framework/testing/raw-attendance-admin-mobile.png', fullPage: true });
});

test('staff cannot open raw attendance administration', async ({ page }) => {
    await login(page, 'employee1@example.test', 'password');
    await expect(page.getByRole('link', { name: 'Raw attendance punches', exact: true })).toHaveCount(0);
    const response = await page.goto('/hr/raw-attendance');
    expect(response.status()).toBe(403);
});
