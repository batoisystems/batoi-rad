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
