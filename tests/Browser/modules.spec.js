import { test, expect } from '@playwright/test';

let authenticatedState;
async function login(page) {
    if (authenticatedState) {
        await page.context().addCookies(authenticatedState.cookies);
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
        return;
    }
    await page.goto('/login');
    await page.getByLabel(/^Email/).fill('browser-admin@example.test');
    await page.getByLabel(/^Password/).fill('Browser-test-password-123!');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    authenticatedState = await page.context().storageState();
}

test('staff search, pagination and CRUD update only the table; invalid inputs stay in the modal', async ({ page }) => {
    await login(page);
    await page.goto('/hr/staff');
    const table = page.locator('#staff-table');
    await expect(table.locator('tbody tr')).toHaveCount(10);
    await page.evaluate(() => window.dashboardMarker = 'preserve-static-shell');
    await table.getByLabel('Search records').fill('Example Employee');
    await table.getByLabel('Search records').press('e');
    await table.getByLabel('Search records').press('Backspace');
    await expect(table.locator('.table-count')).toContainText('15 records');
    await table.getByRole('button', { name: 'Next', exact: true }).click();
    await expect(table.locator('tbody tr')).toHaveCount(5);
    await expect.poll(() => page.evaluate(() => window.dashboardMarker)).toBe('preserve-static-shell');
    await page.getByRole('button', { name: 'Add employee', exact: true }).click();
    const modal = page.locator('#staff-modal');
    await modal.getByLabel('First Name').fill('Browser');
    await modal.getByLabel('Last Name').fill('Created Employee');
    await modal.getByLabel(/^Email/).fill('created-by-browser@example.test');
    await modal.getByLabel('Employment type').selectOption('Permanent');
    await modal.getByRole('combobox', { name: 'Division', exact: true }).selectOption({ label: 'Example Research Division' });
    await modal.getByRole('combobox', { name: 'Position', exact: true }).selectOption(await modal.locator('[name="position_id"] option').filter({ hasText: 'Example New Employee Position' }).getAttribute('value'));
    await modal.getByLabel(/^Role/).selectOption('staff');
    await modal.getByLabel('Attendance ID').fill('0000');
    await modal.getByRole('button', { name: 'Save employee', exact: true }).click();
    await expect(modal.locator('[data-form-errors]')).toContainText('nonzero');
    await expect(page.locator('.swal2-toast')).toContainText('nonzero');
    await expect(modal).toBeVisible();
    await modal.getByLabel('Attendance ID').fill('0009991');
    await modal.getByRole('button', { name: 'Save employee', exact: true }).click();
    await expect(modal).toBeHidden();
    await expect.poll(() => page.evaluate(() => window.dashboardMarker)).toBe('preserve-static-shell');
    await table.getByLabel('Search records').fill('Browser Created');
    await table.getByLabel('Search records').press('d');
    await table.getByLabel('Search records').press('Backspace');
    await expect(table.locator('tbody')).toContainText('Browser Created Employee');
    await expect(table.locator('tbody img.account-avatar')).toHaveCount(1);
    await table.getByRole('button', { name: 'Edit', exact: true }).click();
    await modal.getByLabel('Nickname').fill('Updated asynchronously');
    await modal.getByRole('button', { name: 'Save employee', exact: true }).click();
    await expect(modal).toBeHidden();
    await table.getByRole('button', { name: 'Service record', exact: true }).click();
    const serviceRecord = page.locator('#service-record-modal');
    await expect(serviceRecord.getByRole('heading')).toHaveText('Service record — Browser Created Employee');
    await expect(serviceRecord.locator('tbody')).toContainText('Example New Employee Position');
    await expect(serviceRecord.getByRole('columnheader', { name: 'Employment event', exact: true })).toHaveCount(0);
    await expect(serviceRecord.getByRole('columnheader', { name: 'Notes', exact: true })).toHaveCount(0);
    await page.context().addInitScript(() => { window.print = () => { window.serviceRecordPrinted = true; }; });
    const printPageReady = page.waitForEvent('popup');
    await serviceRecord.getByRole('link', { name: 'Print', exact: true }).click();
    const printPage = await printPageReady;
    await expect(printPage).toHaveURL(/\/hr\/staff\/\d+\/service-record\/print$/);
    await expect(printPage.getByRole('heading', { name: 'Service record', exact: true })).toBeVisible();
    await expect(printPage.locator('main')).toContainText('Browser Created Employee');
    await expect(printPage.locator('tbody')).toContainText('Example New Employee Position');
    await expect.poll(() => printPage.evaluate(() => window.serviceRecordPrinted)).toBe(true);
    await printPage.close();
    await serviceRecord.getByRole('button', { name: 'Close', exact: true }).last().click();
    await expect(serviceRecord).toBeHidden();
    await table.getByRole('button', { name: 'Archive / reactivate', exact: true }).click();
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await expect(table.locator('tbody')).toContainText('Yes');
    await expect.poll(() => page.evaluate(() => window.dashboardMarker)).toBe('preserve-static-shell');
});

test('positions can be edited and then created without overwriting the assigned position', async ({ page }) => {
    await login(page);
    await page.goto('/organization/positions');
    const table = page.locator('#organization-table');
    const modal = page.locator('#organization-modal');
    await expect(table.locator('tbody tr')).toHaveCount(10);
    const originalCount = parseInt(await table.locator('.table-count').textContent(), 10);
    await table.getByRole('button', { name: 'Edit', exact: true }).first().click();
    await modal.getByRole('textbox', { name: 'Title', exact: true }).fill('Updated Example Analyst');
    await modal.getByRole('button', { name: 'Save record', exact: true }).click();
    await expect(modal).toBeHidden();
    await expect(table.locator('tbody')).toContainText('Updated Example Analyst');

    await page.getByRole('button', { name: 'Add position', exact: true }).click();
    await modal.getByRole('textbox', { name: 'Title', exact: true }).fill('Browser New Position');
    await modal.getByLabel('Code', { exact: true }).fill('BROWSER-NEW');
    await modal.getByRole('combobox', { name: 'Classification', exact: true }).selectOption('Staff');
    await modal.getByRole('combobox', { name: 'Division', exact: true }).selectOption({ label: 'Example Research Division' });
    await modal.getByRole('combobox', { name: 'Plantilla', exact: true }).selectOption('false');
    await modal.getByRole('combobox', { name: 'Status', exact: true }).selectOption('Open for Application');
    await modal.getByRole('button', { name: 'Save record', exact: true }).click();
    await expect(modal).toBeHidden();
    await expect(table.locator('.table-count')).toContainText(`${originalCount + 1} records`);
    await table.getByRole('button', { name: 'Next', exact: true }).click();
    await expect(table.locator('tbody')).toContainText('Browser New Position');
    await expect(table.locator('tbody')).toContainText('BROWSER-NEW');
    await table.getByRole('button', { name: 'Previous', exact: true }).click();
    await expect(table.locator('tbody')).toContainText('Updated Example Analyst');
});

test('table error preserves controls and supports retry, empty results are clear', async ({ page }) => {
    await login(page);
    await page.goto('/hr/staff');
    const table = page.locator('#staff-table');
    await expect(table.locator('tbody tr')).toHaveCount(10);
    await page.route('**/hr/staff/data?**', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Temporary service interruption.' }) }));
    await table.getByLabel('Search records').fill('No matching person');
    await table.getByLabel('Search records').press('n');
    await table.getByLabel('Search records').press('Backspace');
    await expect(table.locator('.table-feedback')).toContainText('Temporary service interruption.');
    await expect(table.getByLabel('Search records')).toHaveValue('No matching person');
    await page.unroute('**/hr/staff/data?**');
    await table.getByRole('button', { name: 'Retry', exact: true }).click();
    await expect(table.locator('tbody')).toContainText('No records found.');
});

test('portal, calendar and PDS work on mobile with persistent light and dark themes', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await page.getByRole('switch', { name: 'Switch color theme' }).click();
    const theme = await page.locator('html').getAttribute('data-bs-theme');
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-bs-theme', theme);
    await page.getByRole('button', { name: 'Open navigation' }).click();
    await expect(page.locator('#navigation')).toBeVisible();
    await page.getByRole('button', { name: 'Close navigation' }).click();
    await page.goto('/profile');
    await page.getByRole('button', { name: 'Add / update section', exact: true }).click();
    const pds = page.locator('#pds-modal');
    await pds.getByLabel('First Name', { exact: true }).fill('Browser');
    await pds.getByLabel('Last Name', { exact: true }).fill('Administrator');
    await pds.getByRole('button', { name: 'Save section', exact: true }).click();
    await expect(pds).toBeHidden();
    await page.getByLabel('PDS section', { exact: true }).selectOption('family');
    await page.getByRole('button', { name: 'Add / update section', exact: true }).click();
    await pds.getByRole('button', { name: 'Add child', exact: true }).click();
    await pds.getByLabel('Child name').fill('Example Child');
    await pds.getByLabel('Birth date', { exact: true }).fill('2015-01-01');
    await pds.getByRole('button', { name: 'Save section', exact: true }).click();
    await expect(pds).toBeHidden();
    await page.locator('#pds-table').getByRole('button', { name: 'Edit', exact: true }).click();
    await expect(pds.getByLabel('Child name')).toHaveValue('Example Child');
    await pds.getByRole('button', { name: 'Cancel', exact: true }).click();
    for (const path of ['/dashboard', '/hr/staff', '/calendar', '/attendance', '/attendance/review']) {
        await page.goto(path);
        await expect(page.locator('h1')).toBeVisible();
        for (const table of await page.locator('[data-table]:visible').all()) {
            await expect(table).toHaveAttribute('aria-busy', 'false');
            await expect(table.locator('.table-feedback')).toBeHidden();
        }
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    }
    await page.screenshot({ path: 'storage/framework/testing/browser-mobile.png', fullPage: true });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/dashboard');
    await page.screenshot({ path: 'storage/framework/testing/browser-desktop.png', fullPage: true });
    await page.getByRole('switch', { name: 'Switch color theme' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-bs-theme', theme === 'dark' ? 'light' : 'dark');
    await page.screenshot({ path: 'storage/framework/testing/browser-alternate-theme.png', fullPage: true });
});

test('development, compliance, concern and reference modules render responsive live tables', async ({ page }) => {
    await login(page);
    await page.setViewportSize({ width: 390, height: 844 });
    for (const path of ['/learning-development', '/idp', '/saln', '/ipcr', '/concerns', '/personnel-documents', '/hr/references']) {
        await page.goto(path);
        await expect(page.locator('h1')).toBeVisible();
        for (const table of await page.locator('[data-table]:visible').all()) {
            await expect(table).toHaveAttribute('aria-busy', 'false');
            await expect(table.locator('.table-feedback')).toBeHidden();
        }
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    }
});

test('IDP, SALN and IPCR structured editors save nested records without leaving the page', async ({ page }) => {
    await login(page);
    await page.goto('/idp');
    await page.getByRole('button', { name: 'New IDP', exact: true }).click();
    const idp = page.locator('#idp-modal');
    await idp.getByLabel('Plan year').fill('2099');
    await idp.getByLabel('Coverage years').fill('2099, 2100');
    await idp.locator('select[name="purpose"]').selectOption('1');
    await idp.getByLabel('Development proposal').fill('Build secure services');
    await idp.getByLabel('Training or intervention title').fill('Secure coding');
    await idp.getByRole('button', { name: 'Add item' }).click();
    await idp.getByLabel('Development proposal').nth(1).fill('Improve facilitation');
    await idp.getByLabel('Training or intervention title').nth(1).fill('Facilitation workshop');
    await idp.getByRole('button', { name: 'Save draft' }).click();
    await expect(idp).toBeHidden();
    await expect(page.locator('#idp-table tbody')).toContainText('2099');

    await page.goto('/saln');
    await page.getByRole('button', { name: 'New filing', exact: true }).click();
    const saln = page.locator('#saln-modal');
    await saln.getByLabel('Filing type').selectOption('1');
    await saln.getByLabel('Compliance type').selectOption('1');
    await saln.getByLabel('Compliance date').fill('2099-12-31');
    await saln.getByRole('button', { name: 'Create working copy' }).click();
    const salnWork = page.locator('#saln-work-modal');
    await expect(salnWork).toBeVisible();
    await salnWork.locator('select[name="section"]').selectOption('real_property');
    await salnWork.getByLabel(/Declared amount/).fill('100.25');
    await salnWork.getByLabel('Section details').fill('description: Example property\nlocation: Manila');
    await salnWork.getByRole('button', { name: 'Add section entry' }).click();
    await expect(salnWork).toContainText('Assets: 100.25');

    await page.goto('/ipcr');
    await page.getByRole('button', { name: 'New IPCR', exact: true }).click();
    const ipcr = page.locator('#ipcr-modal');
    await ipcr.getByLabel('Period start').fill('2099-01-01');
    await ipcr.getByLabel('Period end').fill('2099-06-30');
    await ipcr.getByRole('button', { name: 'Create draft' }).click();
    const work = page.locator('#ipcr-work-modal');
    await expect(work).toBeVisible();
    await work.getByLabel('Category').selectOption('1');
    await work.getByLabel('Task').fill('Deliver secure service');
    await work.getByRole('button', { name: 'Add output' }).click();
    await work.getByLabel('Output').selectOption({ index: 1 });
    await work.getByLabel('Description').fill('Accepted on schedule');
    await work.getByRole('button', { name: 'Add indicator' }).click();
    await work.getByLabel('Success indicator').selectOption({ index: 1 });
    await work.locator('textarea[name="accomplishment"]').fill('Service released and accepted');
    await work.getByRole('button', { name: 'Add accomplishment' }).click();
    await expect(work.locator('#ipcr-structure')).toContainText('Service released and accepted');
});

test('calendar creates and deletes without a full reload and private event dates round trip', async ({ page }) => {
    await login(page);
    await page.goto('/calendar');
    await page.evaluate(() => window.calendarMarker = 'keep-shell');
    await expect(page.locator('#event-calendar .fc-daygrid')).toBeVisible();
    await page.evaluate(() => document.querySelector('#event-calendar').calendar.gotoDate('2026-10-01'));
    await page.getByRole('button', { name: 'Add event', exact: true }).click();
    const modal = page.locator('#event-modal');
    await modal.getByLabel(/^Title/).fill('Browser personal event');
    await modal.getByLabel(/^Starts \(Manila time\)/).fill('2026-10-21T08:00');
    await modal.getByLabel(/^Ends \(inclusive/).fill('2026-10-21T09:00');
    await modal.getByLabel('All day', { exact: true }).uncheck();
    await modal.getByLabel('Participants', { exact: true }).selectOption({ label: 'Example Employee 01' });
    await modal.getByRole('button', { name: 'Save event', exact: true }).click();
    await expect(modal).toBeHidden();
    const table = page.locator('#calendar-table');
    await expect(table.locator('tbody')).toContainText('Browser personal event');
    await expect(page.locator('#event-calendar .fc-event')).toContainText('Browser personal event');
    await expect(page.locator('.swal2-toast')).toContainText('Calendar event saved.');
    await table.getByRole('button', { name: 'Edit', exact: true }).click();
    await expect(modal.getByLabel(/^Starts \(Manila time\)/)).toHaveValue('2026-10-21T08:00');
    await expect(modal.getByLabel('Participants', { exact: true })).toHaveValues(['2']);
    await modal.getByRole('button', { name: 'Cancel', exact: true }).click();
    await table.getByRole('button', { name: 'Delete', exact: true }).click();
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await expect(table.locator('tbody')).toContainText('No records found.');
    await expect(page.locator('#event-calendar .fc-event')).toHaveCount(0);
    await expect.poll(() => page.evaluate(() => window.calendarMarker)).toBe('keep-shell');
});

test('leave form changes details by type, limits monetization fields and warns for holidays', async ({ page }) => {
    await login(page);
    await page.goto('/leave');
    await page.getByRole('button', { name: 'Apply for leave', exact: true }).click();
    const modal = page.locator('#leave-modal');
    await modal.getByLabel('Leave type').selectOption({ label: 'Vacation Leave' });
    const details = modal.getByLabel('Leave details');
    await expect(details.locator('option')).toHaveText(['Choose…', 'Within the Philippines', 'Abroad (specify)', 'Monetization of leave credits', 'Terminal Leave']);
    await details.selectOption('2');
    await expect(modal.getByLabel('Specify')).toBeVisible();
    await details.selectOption('7');
    await expect(modal.getByLabel('Number of days')).toBeVisible();
    await expect(modal.getByRole('group', { name: 'Inclusive dates' })).toBeHidden();
    await modal.getByLabel('Leave type').selectOption({ label: 'Mandatory/Forced Leave' });
    await expect(modal.getByLabel('For Disapproval (Exigency of service)')).toBeVisible();
    await expect(details).toBeHidden();
    await modal.getByLabel('Leave type').selectOption({ label: 'Special Privilege Leave' });
    await details.selectOption('1');
    await modal.getByLabel('Date or range start').fill('2026-10-22');
    await modal.getByRole('button', { name: 'Add', exact: true }).click();
    await expect(page.locator('.swal2-popup')).toContainText('Example Agency Holiday');
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
    await expect(modal.getByRole('button', { name: 'Remove 2026-10-22' })).toBeVisible();
    await modal.getByRole('button', { name: 'Submit application', exact: true }).click();
    await expect(modal).toBeHidden();
    const row = page.locator('#leave-table tbody tr').filter({ hasText: 'Special Privilege Leave' });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: 'Review / cancel', exact: true }).click();
    const review = page.locator('#leave-action');
    await expect(review.getByLabel('Action').locator('option')).toHaveText(['Choose…', 'Cancel my application']);
    await expect(review.getByRole('heading', { name: 'Action history' })).toBeVisible();
    await expect(review.locator('#leave-action-history')).toContainText('For HR Cert');
    await expect(review.locator('#leave-action-history')).toContainText('Administrator · system admin');
});

test('account settings show photos and SweetAlert2 feedback for Livewire saves, errors and photo uploads', async ({ page }) => {
    await login(page);
    await page.goto('/settings/profile');
    await expect(page.locator('img.account-avatar-lg')).toBeVisible();
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('.swal2-toast')).toContainText('Profile updated.');
    const email = page.getByRole('textbox', { name: 'Email', exact: true });
    await email.fill('employee1@example.test');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('.swal2-toast')).toContainText('taken');
    await email.fill('browser-admin@example.test');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.locator('.swal2-toast')).toContainText('Profile updated.');
    await page.getByLabel('Choose profile picture', { exact: true }).setInputFiles({
        name: 'synthetic-profile.png', mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+juxkAAAAASUVORK5CYII=', 'base64'),
    });
    await page.getByRole('button', { name: 'Upload picture', exact: true }).click();
    await expect(page.locator('.swal2-toast')).toContainText('Profile picture updated.');
    await expect.poll(() => page.locator('img.account-avatar-lg').evaluate(image => image.complete && image.naturalWidth)).toBe(1);
    await expect.poll(() => page.locator('body').evaluate(element => getComputedStyle(element).fontFamily)).toContain('-apple-system');
    await page.goto('/dashboard');
    await expect(page.locator('.hris-header img.account-avatar')).toBeVisible();
    await expect.poll(() => page.locator('.hris-header img.account-avatar').evaluate(image => image.complete && image.naturalWidth)).toBe(1);
});

test('month calendar shows all events in Manila time on a mobile device in another timezone', async ({ browser }) => {
    const context = await browser.newContext({ baseURL: 'http://127.0.0.1:8765', timezoneId: 'America/Los_Angeles', viewport: { width: 390, height: 844 } });
    try {
        const page = await context.newPage();
        await login(page);
        const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
        for (let index = 1; index <= 13; index++) {
            const edge = index === 13;
            const response = await page.request.post('/calendar/events', { headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, data: {
                title: edge ? 'Manila month edge' : `Month overview event ${index}`, event_type: 'My Calendar', all_day: true,
                starts_at: edge ? '2026-10-31T00:00:00+08:00' : '2026-10-21T00:00:00+08:00',
                ends_at: edge ? '2026-10-31T00:00:00+08:00' : '2026-10-22T00:00:00+08:00', participants: [],
            } });
            expect(response.ok()).toBeTruthy();
        }
        await page.goto('/calendar');
        await expect(page.locator('#event-calendar .fc-daygrid')).toBeVisible();
        await page.evaluate(() => document.querySelector('#event-calendar').calendar.gotoDate('2026-10-01'));
        await expect.poll(() => page.evaluate(() => document.querySelector('#event-calendar').calendar.getEvents().length)).toBe(13);
        await expect(page.locator('#event-calendar .fc-event').filter({ hasText: 'Manila month edge' })).toBeVisible();
        await page.locator('#event-calendar .fc-event').filter({ hasText: 'Manila month edge' }).click();
        const modal = page.locator('#event-modal');
        await expect(modal.getByLabel(/^Starts \(Manila time\)/)).toHaveValue('2026-10-31T00:00');
        await modal.getByRole('button', { name: 'Cancel', exact: true }).click();
        await expect(modal).toBeHidden();
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: 'storage/framework/testing/calendar-mobile.png', fullPage: true });
        await page.getByRole('button', { name: 'Previous month', exact: true }).click();
        await expect(page.locator('.fc-toolbar-title')).toHaveText('September 2026');
        await page.request.post('/calendar/events', { headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, data: {
            title: 'Office grid activity', event_type: 'Office', all_day: true,
            starts_at: '2026-10-23T00:00:00+08:00', ends_at: '2026-10-23T00:00:00+08:00', participants: [],
        } });
        await page.evaluate(() => document.querySelector('#event-calendar').calendar.gotoDate('2026-10-01'));
        await page.getByLabel('Calendar', { exact: true }).selectOption('office');
        await expect.poll(() => page.evaluate(() => document.querySelector('#event-calendar').calendar.getEvents().map(event => event.title))).toEqual(['Office grid activity']);
        await expect(page.locator('#calendar-table tbody')).toContainText('Office grid activity');
        await page.screenshot({ path: 'storage/framework/testing/calendar-mobile.png', fullPage: true });
        await page.setViewportSize({ width: 1440, height: 1000 });
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await expect.poll(() => page.evaluate(() => document.querySelector('#event-calendar .fc-col-header').getBoundingClientRect().width >= document.querySelector('#event-calendar').getBoundingClientRect().width - 4)).toBe(true);
        await page.screenshot({ path: 'storage/framework/testing/calendar-desktop.png', fullPage: true });
    } finally { await context.close(); }
});
