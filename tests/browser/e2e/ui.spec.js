import { test, expect } from '@playwright/test';

test('Arazzo Builder page loads successfully', async ({ page }) => {
  await page.goto('/arazzo-builder');
  
  // Verify the header is present
  await expect(page.locator('h1', { hasText: 'Arazzo Flow Builder' })).toBeVisible();
});
