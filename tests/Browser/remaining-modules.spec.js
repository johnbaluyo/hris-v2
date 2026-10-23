import { test, expect } from '@playwright/test';

async function login(page, email = 'browser-admin@example.test', password = 'Browser-test-password-123!') {
    await page.goto('/login');
    await page.getByLabel(/^Email/).fill(email);
    await page.getByLabel(/^Password/).fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

test('HR builds a questionnaire and an applicant applies and submits a timed essay', async ({ page, browser }) => {
    await login(page);
    await page.goto('/careers');
    await page.getByRole('button', { name: 'Add exam category', exact: true }).click();
    const category = page.locator('#category-editor');
    await category.getByLabel(/^Name/).fill('Browser Written Exam');
    await category.getByLabel(/^Exam Type/).fill('Written');
    await category.getByLabel(/Time limit/).fill('20');
    await category.getByRole('button', { name: 'Save category' }).click();
    await expect(category).not.toBeVisible();
    const bankRow = page.locator('#exam-bank-table tbody tr').filter({ hasText: 'Browser Written Exam' });
    await bankRow.getByRole('button', { name: 'Add question', exact: true }).click();
    const question = page.locator('#question-editor');
    await question.getByLabel(/^Question Type/).selectOption('3');
    await question.getByLabel(/^Question \*/).fill('Describe your fictional research experience.');
    await question.getByRole('button', { name: 'Save question' }).click();
    await expect(question).not.toBeVisible();
    await page.getByRole('button', { name: 'Create vacancy' }).click();
    const vacancy = page.locator('#vacancy-editor');
    await vacancy.getByLabel(/^Position/).selectOption({ label: 'Example Recruitment Position ()' });
    await vacancy.getByLabel('Questionnaires').selectOption({ label: 'Browser Written Exam' });
    await vacancy.getByRole('button', { name: 'Save vacancy' }).click();
    await expect(vacancy).not.toBeVisible();

    const applicantContext = await browser.newContext();
    const applicant = await applicantContext.newPage();
    await login(applicant, 'browser-applicant@example.test');
    await applicant.goto('/careers');
    const row = applicant.locator('#vacancies-table tbody tr').filter({ hasText: 'Example Recruitment Position' });
    await row.getByRole('button', { name: 'Apply', exact: true }).click();
    await applicant.locator('.swal2-confirm').click();
    await expect(applicant.locator('#applications-table tbody')).toContainText('pending application');

    await page.reload();
    const application = page.locator('#applications-table tbody tr').filter({ hasText: 'Example Applicant' });
    await application.getByRole('button', { name: 'Review', exact: true }).click();
    const review = page.locator('#application-review');
    await review.getByLabel(/^Status/).selectOption('for assessment');
    await review.getByLabel(/Enable assigned examinations/).check();
    await review.getByRole('button', { name: 'Save review' }).click();
    await expect(review).not.toBeVisible();
    await applicant.reload();
    await applicant.locator('#applications-table').getByRole('button', { name: 'Details / exams' }).click();
    await applicant.getByRole('button', { name: /Start \/ resume Browser Written Exam/ }).click();
    await applicant.locator('.swal2-confirm').click();
    await expect(applicant).toHaveURL(/\/careers\/exams\/\d+$/);
    await applicant.getByLabel('Your response').fill('A fictional essay submitted by the browser.');
    await applicant.getByRole('button', { name: 'Submit final answers' }).click();
    await expect(applicant).toHaveURL(/\/careers$/);
    await applicantContext.close();
});

test('certificate requests, notifications and recognition forms save asynchronously', async ({ page }) => {
    await login(page);
    await page.goto('/cert');
    await page.getByRole('button', { name: 'Request certificate', exact: true }).click();
    const request = page.locator('#certificate-request');
    await request.getByLabel(/^Purpose/).fill('Browser fictional certificate request');
    await request.getByRole('button', { name: 'Submit request' }).click();
    await expect(request).not.toBeVisible();
    await expect(page.locator('#certificates-table tbody')).toContainText('For Acknowledgment');

    await page.goto('/notifications');
    await page.getByRole('button', { name: 'Publish notice' }).click();
    const notice = page.locator('#notification-publish');
    await notice.getByLabel(/^Title/).fill('Browser broadcast');
    await notice.getByLabel(/^Message/).fill('Fictional message');
    await notice.getByLabel('Audience').selectOption('broadcast');
    await notice.getByLabel(/Destination/).fill('/dashboard');
    await notice.getByRole('button', { name: 'Publish', exact: true }).click();
    await expect(notice).not.toBeVisible();
    const noticeRow = page.locator('#notifications-table tbody tr').filter({ hasText: 'Browser broadcast' });
    await noticeRow.getByRole('button', { name: 'Dismiss', exact: true }).click();
    await expect(page.locator('#notifications-table tbody')).not.toContainText('Browser broadcast');

    await page.goto('/recognition');
    await page.getByRole('button', { name: 'Create event' }).click();
    const event = page.locator('#recognition-editor');
    await event.getByLabel(/^Name/).fill('Browser attendance recognition');
    await event.getByLabel(/^Type/).selectOption('attendance');
    await event.getByLabel(/^Coverage Start/).fill('2026-01-01');
    await event.getByLabel(/^Coverage End/).fill('2026-09-30');
    await event.getByLabel(/^Recognition Date/).fill('2026-12-01');
    await event.getByLabel('Eligible voter employee types').selectOption('Permanent');
    await event.locator('[name="nominee_ids"]').selectOption({ label: 'Example Employee 01' });
    await event.getByRole('button', { name: 'Save event' }).click();
    await expect(event).not.toBeVisible();
    await expect(page.locator('#recognition-table tbody')).toContainText('Browser attendance recognition');
    await page.setViewportSize({ width: 390, height: 844 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('a staff WFH accomplishment can be reviewed and signed by its chief', async ({ page, browser }) => {
    await login(page, 'employee1@example.test', 'password');
    await page.goto('/wfh-accomplishments');
    await page.getByRole('button', { name: 'Submit accomplishment', exact: true }).click();
    const editor = page.locator('#wfh-editor');
    await editor.getByLabel(/^Wfh Date/).fill('2026-10-21');
    await editor.getByLabel(/^Accomplishment/).fill('Browser WFH accomplishment');
    await editor.getByRole('button', { name: 'Submit accomplishment' }).click();
    await expect(editor).not.toBeVisible();
    await expect(page.locator('#wfh-table tbody')).toContainText('for approval');
    const chiefContext = await browser.newContext();
    const chief = await chiefContext.newPage();
    await login(chief, 'browser-chief@example.test');
    await chief.goto('/wfh-accomplishments');
    await chief.locator('#wfh-table').getByRole('button', { name: 'Review', exact: true }).click();
    const review = chief.locator('#wfh-review');
    await review.getByLabel(/^Action/).selectOption('approve');
    await review.getByRole('button', { name: 'Record review' }).click();
    await expect(review).not.toBeVisible();
    await chief.getByRole('button', { name: 'Consolidate division' }).click();
    const consolidate = chief.locator('#wfh-consolidate');
    await consolidate.getByLabel(/^Division/).selectOption({ label: 'Example Research Division' });
    await consolidate.getByLabel(/^Wfh Date/).fill('2026-10-21');
    await consolidate.getByRole('button', { name: 'Capture draft report' }).click();
    await expect(consolidate).not.toBeVisible();
    await chief.locator('#wfh-reports-table').getByRole('button', { name: 'Sign', exact: true }).click();
    await chief.locator('.swal2-confirm').click();
    await expect(chief.locator('#wfh-reports-table tbody')).toContainText('for approval');
    await page.reload();
    await expect(page.locator('#wfh-table tbody').getByRole('button', { name: 'Edit', exact: true })).toHaveCount(0);
    await chiefContext.close();
});

test('all remaining module pages load on mobile with unique form labels', async ({ page }) => {
    await login(page);
    await page.setViewportSize({ width: 390, height: 844 });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const url of ['/careers', '/cert', '/recognition', '/wfh-accomplishments', '/notifications']) {
        await page.goto(url);
        await expect(page.locator('[data-table]').first()).toHaveAttribute('aria-busy', 'false');
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await expect.poll(() => page.evaluate(() => {
            const ids = [...document.querySelectorAll('[id]')].map(element => element.id);
            return ids.length === new Set(ids).size;
        })).toBe(true);
    }
    await page.screenshot({ path: 'storage/framework/testing/remaining-notifications-mobile.png', fullPage: true });
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/careers');
    await expect(page.locator('#vacancies-table')).toHaveAttribute('aria-busy', 'false');
    await page.screenshot({ path: 'storage/framework/testing/remaining-recruitment-desktop.png', fullPage: true });
    expect(errors).toEqual([]);
});
