import { test, expect } from '@playwright/test';

test('administrator session and mutation boundaries', async ({ page }) => {
  const username = process.env.RAD_TEST_ADMIN_USERNAME || 'admin';
  const password = process.env.RAD_TEST_ADMIN_PASSWORD;
  if (!password) throw new Error('RAD_TEST_ADMIN_PASSWORD is required');

  await page.goto('/rad-admin');
  await expect(page.getByRole('heading', { name: 'Sign in to RAD Admin' })).toBeVisible();
  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/rad-admin\/home\/view/);
  await expect(page.locator('meta[name="rad-csrf"]')).toHaveCount(1);

  await expect(page.locator('main#rad-main-content')).toHaveCount(1);
  await expect(page.locator('h1')).toHaveCount(1);
  const firstFocusableClass = await page.evaluate(() => {
    const focusable = Array.from(document.querySelectorAll('a[href], button, input, select, textarea, [tabindex]'));
    const first = focusable.find((element) => !element.hasAttribute('disabled') && element.getAttribute('tabindex') !== '-1');
    return first?.className || '';
  });
  expect(firstFocusableClass).toContain('rad-skip-link');
  await page.locator('.rad-skip-link').focus();
  await expect(page.locator('.rad-skip-link')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#rad-main-content')).toBeFocused();

  const accessibilityErrors = await page.evaluate(() => {
    const errors = [];
    const visible = (element) => {
      const style = window.getComputedStyle(element);
      return style.display !== 'none' && style.visibility !== 'hidden';
    };
    const accessibleName = (element) => {
      const labelledBy = element.getAttribute('aria-labelledby');
      if (labelledBy) {
        return labelledBy.split(/\s+/).map((id) => document.getElementById(id)?.textContent || '').join(' ').trim();
      }
      return (element.getAttribute('aria-label') || element.getAttribute('title') || element.textContent || '').trim();
    };
    document.querySelectorAll('button, a[href]').forEach((element) => {
      if (visible(element) && accessibleName(element) === '') {
        errors.push(`unnamed ${element.tagName.toLowerCase()}: ${element.outerHTML.slice(0, 240)}`);
      }
    });
    document.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((element) => {
      const label = element.id ? document.querySelector(`label[for="${CSS.escape(element.id)}"]`) : null;
      if (visible(element) && !label && !element.getAttribute('aria-label') && !element.getAttribute('aria-labelledby')) {
        errors.push(`unlabelled control ${element.getAttribute('name') || element.tagName.toLowerCase()}`);
      }
    });
    return errors;
  });
  expect(accessibilityErrors).toEqual([]);

  const navigation = await page.evaluate(() => performance.getEntriesByType('navigation')[0]?.duration || 0);
  expect(navigation).toBeLessThan(4000);
  expect(await page.locator('script[src], link[rel="stylesheet"]').count()).toBeLessThan(50);

  await page.reload();
  await expect(page).toHaveURL(/\/rad-admin\/home\/view/);
  await expect(page.locator('a[href*="/rad-admin/aiassist"]')).toHaveCount(0);

  const response = await page.goto('/rad-admin/config/archive/not-a-real-id');
  expect(response?.status()).toBe(405);
  await expect(page).toHaveTitle(/Error 405/);

  await page.goto('/rad-admin/home/view');
  await page.locator('[data-bs-toggle="dropdown"]:visible').last().click();
  const logout = page.locator('a[href$="/login/logout"]').last();
  await expect(logout).toBeVisible();
  await logout.click();
  await expect(page).toHaveURL(/\/login/);
  await expect(page.getByText(/Sign in/i).first()).toBeVisible();
});
