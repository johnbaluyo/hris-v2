import { test, expect } from '@playwright/test';

async function login(page, email, password = 'Browser-test-password-123!') {
    await page.goto('/login');
    await page.getByLabel(/^Email/).fill(email);
    await page.getByLabel(/^Password/).fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

async function navigationGroup(page, label) {
    return page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link', { name: label, exact: true }).evaluate(link => {
        let previous = link.previousElementSibling;
        while (previous && !previous.classList.contains('nav-section')) previous = previous.previousElementSibling;
        return previous?.textContent.trim();
    });
}

test('recruitment is grouped under HR administration while staff and chief see Careers in records', async ({ page, browser }) => {
    await login(page, 'browser-admin@example.test');
    await expect.poll(() => navigationGroup(page, 'Careers & recruitment')).toBe('HR ADMINISTRATION');
    await page.getByRole('link', { name: 'Careers & recruitment', exact: true }).click();
    await expect(page).toHaveURL(/\/hr\/recruitment$/);
    for (const [email, password] of [['employee1@example.test', 'password'], ['browser-chief@example.test', 'Browser-test-password-123!']]) {
        const context = await browser.newContext();
        const viewer = await context.newPage();
        await login(viewer, email, password);
        await expect.poll(() => navigationGroup(viewer, 'Careers')).toBe('DEVELOPMENT & RECORDS');
        await expect(viewer.getByRole('link', { name: 'Careers & recruitment', exact: true })).toHaveCount(0);
        await viewer.getByRole('link', { name: 'Careers', exact: true }).click();
        await expect(viewer.getByRole('heading', { name: 'Careers', exact: true })).toBeVisible();
        await expect(viewer.getByRole('button', { name: 'Create vacancy', exact: true })).toHaveCount(0);
        await expect(viewer.getByRole('button', { name: 'Upload document', exact: true })).toHaveCount(0);
        await context.close();
    }
});

test('admin enables office punches for everyone and the employee portal hides the button outside access windows', async ({ page, browser }) => {
    await login(page, 'browser-admin@example.test');
    await page.goto('/attendance');
    const office = page.getByRole('button', { name: 'Record office punch', exact: true });
    await expect(office).toBeHidden();
    const staffContext = await browser.newContext();
    const staff = await staffContext.newPage();
    await login(staff, 'employee1@example.test', 'password');
    const portalOffice = staff.getByRole('button', { name: 'Record office punch', exact: true });
    await expect(portalOffice).toBeHidden();
    const timing = await page.evaluate(async () => {
        const access = await window.HRIS.request('/attendance/clock-availability');
        const manila = Date.parse(access.server_time) + 8 * 60 * 60 * 1000;
        return { start: new Date(manila - 60000).toISOString().slice(0, 16), end: new Date(manila + 1800000).toISOString().slice(0, 16) };
    });
    await page.getByRole('button', { name: 'Set office punch duration' }).click();
    const modal = page.locator('#office-punch-window-modal');
    await modal.getByLabel('Starts (Manila time)').fill(timing.start);
    await modal.getByLabel('Ends (Manila time)').fill(timing.end);
    await modal.getByLabel('Reason').fill('Browser temporary capture window');
    await expect(modal.locator('[name="employee_ids"], [name="all_employees"]')).toHaveCount(0);
    await modal.getByRole('button', { name: 'Save access window' }).click();
    await expect(modal).not.toBeVisible();
    await expect(office).toBeEnabled();
    const punchResponse = page.waitForResponse(response => response.url().endsWith('/attendance/clock') && response.request().method() === 'POST');
    await office.click();
    expect((await punchResponse).status()).toBe(201);
    await expect(page.locator('#raw-table tbody')).toContainText('WEB APP');
    await staff.reload();
    await expect(portalOffice).toBeVisible();
    await expect(portalOffice).toBeEnabled();
    expect(await staff.getByRole('link', { name: 'View my attendance', exact: true }).evaluate(link => link.nextElementSibling.id)).toBe('clock-office');
    const staffPunchResponse = staff.waitForResponse(response => response.url().endsWith('/attendance/clock') && response.request().method() === 'POST');
    await portalOffice.click();
    expect((await staffPunchResponse).status()).toBe(201);
    await expect(staff).toHaveURL(/\/dashboard$/);
    const row = page.locator('#office-punch-windows tbody tr').filter({ hasText: 'Browser temporary capture window' });
    await row.getByRole('button', { name: 'Revoke', exact: true }).click();
    await page.locator('.swal2-confirm').click();
    await expect(office).toBeHidden();
    await staff.evaluate(() => window.dispatchEvent(new Event('attendance:office-access-changed')));
    await expect(portalOffice).toBeHidden();
    await staff.reload();
    await expect(portalOffice).toBeHidden();
    await staff.setViewportSize({ width: 390, height: 844 });
    await expect.poll(() => staff.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await staff.goto('/attendance');
    await expect(staff.getByRole('button', { name: 'Record office punch', exact: true })).toBeHidden();
    await expect(staff.getByRole('button', { name: 'Set office punch duration' })).toHaveCount(0);
    await page.setViewportSize({ width: 390, height: 844 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: 'storage/framework/testing/office-punch-admin-mobile.png', fullPage: true });
    await staffContext.close();
});
