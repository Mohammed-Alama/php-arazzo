import { test, expect } from '@playwright/test';

test('Arazzo Builder page loads successfully', async ({ page }) => {
  await page.goto('/arazzo-builder');
  
  // Verify the header is present
  await expect(page.locator('h1', { hasText: 'Arazzo Flow Builder' })).toBeVisible();
});

test('loads endpoints and allows drag to canvas', async ({ page }) => {
  // Mock the API response to provide a consistent test environment
  await page.route('**/api/arazzo/endpoints*', async route => {
    const json = [
      { method: 'GET', path: '/users', operationId: 'getUsers' },
      { method: 'POST', path: '/users', operationId: 'createUser' }
    ];
    await route.fulfill({ json });
  });

  await page.goto('/arazzo-builder');
  
  // Wait for sidebar endpoints to load
  await expect(page.locator('aside >> text=getUsers')).toBeVisible();
  
  // The canvas should be empty initially
  await expect(page.locator('.react-flow__node')).toHaveCount(0);

  // Drag the 'getUsers' endpoint to the React Flow canvas
  const sidebarItem = page.locator('aside >> text=getUsers');
  const canvas = page.locator('.react-flow__pane');
  
  await sidebarItem.dragTo(canvas);

  // Verify the node is now present in the canvas
  await expect(page.locator('.react-flow__node')).toHaveCount(1);
  await expect(page.locator('.react-flow__node >> text=getUsers')).toBeVisible();
});

test('generates yaml successfully', async ({ page }) => {
  // Mock endpoints
  await page.route('**/api/arazzo/endpoints*', async route => {
    const json = [{ method: 'GET', path: '/users', operationId: 'getUsers' }];
    await route.fulfill({ json });
  });

  // Mock generate API
  await page.route('**/api/arazzo/generate*', async route => {
    const json = { yaml: 'arazzo: 1.0.1\ninfo:\n  title: Mocked\n  version: 1.0' };
    await route.fulfill({ json });
  });

  await page.goto('/arazzo-builder');
  
  // Wait for sidebar
  await expect(page.locator('aside >> text=getUsers')).toBeVisible();

  // Drag node to canvas
  const sidebarItem = page.locator('aside >> text=getUsers');
  const canvas = page.locator('.react-flow__pane');
  await sidebarItem.dragTo(canvas);

  // Click Generate YAML
  await page.getByRole('button', { name: 'Generate YAML' }).click();

  // Verify editor appears
  await expect(page.locator('.monaco-editor')).toBeVisible();
});
