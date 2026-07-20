# Playwright Browser Testing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Set up end-to-end browser testing for the Arazzo React Flow UI using Playwright.
**Architecture:** Playwright will be installed as a dev dependency via npm. We will configure Playwright to spin up the local Laravel dev server and Vite before running tests. The tests will verify the rendering of the Sidebar and Canvas, and simulate dragging an endpoint and generating the Arazzo YAML.
**Tech Stack:** Playwright, React, Laravel.

---

### Task 1: Install and Configure Playwright

**Files:**
- Modify: `package.json`
- Create: `playwright.config.js`
- Create: `tests/browser/e2e/ui.spec.js`

- [ ] **Step 1: Install Dependencies**

```bash
rtk proxy npm install --save-dev @playwright/test
rtk proxy npx playwright install --with-deps chromium
```

- [ ] **Step 2: Create Playwright Config**

```javascript
// playwright.config.js
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/browser/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'rtk proxy php artisan serve',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
  },
});
```

- [ ] **Step 3: Write initial sanity test**

```javascript
// tests/browser/e2e/ui.spec.js
import { test, expect } from '@playwright/test';

test('Arazzo Builder page loads successfully', async ({ page }) => {
  // Assuming the view is reachable at /arazzo. 
  // Wait, we need to create a test route for this to be reachable!
});
```
Wait, we need a route to serve `arazzo.blade.php`.
Let's add a route in `src/LaravelArazzoServiceProvider.php` for testing, or just rely on the host application. Since it's a package, it's best to add a specific route in the service provider for the UI, e.g. `/arazzo-builder`.

- [ ] **Step 4: Update LaravelArazzoServiceProvider to serve the UI view**

```php
// modify: src/LaravelArazzoServiceProvider.php
// Inside packageBooted(), add the view route:
    public function packageBooted(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'arazzo');

        \Illuminate\Support\Facades\Route::get('/arazzo-builder', function () {
            return view('arazzo::arazzo');
        })->middleware('web');

        \Illuminate\Support\Facades\Route::prefix('api/arazzo')
            ->middleware('api')
            ->group(function () {
                \Illuminate\Support\Facades\Route::get('/endpoints', [\Alama\LaravelArazzo\Http\Controllers\ArazzoApiController::class, 'endpoints']);
                \Illuminate\Support\Facades\Route::post('/generate', [\Alama\LaravelArazzo\Http\Controllers\ArazzoApiController::class, 'generate']);
            });
    }
```
*(Also rename `resources/views/arazzo.blade.php` to `arazzo.blade.php` inside a package view path if needed, but since it's already in `resources/views/`, `loadViewsFrom` will work).*

- [ ] **Step 5: Write the actual test**

```javascript
// tests/browser/e2e/ui.spec.js
import { test, expect } from '@playwright/test';

test('Arazzo Builder page loads successfully', async ({ page }) => {
  await page.goto('/arazzo-builder');
  
  // Verify the header is present
  await expect(page.locator('h1', { hasText: 'Arazzo Flow Builder' })).toBeVisible();
});
```

- [ ] **Step 6: Run sanity test to verify it passes**

```bash
# Note: Ensure Vite is built or running before testing. Playwright webServer config only runs `artisan serve`.
rtk proxy npm run build
rtk proxy npx playwright test
```
Expected: PASS

- [ ] **Step 7: Commit**

```bash
rtk proxy git add package.json package-lock.json playwright.config.js tests/browser/ src/LaravelArazzoServiceProvider.php
rtk proxy git commit -m "chore: setup playwright and serve builder view"
```

---

### Task 2: React Flow Drag and Drop Tests

**Files:**
- Modify: `tests/browser/e2e/ui.spec.js`

- [ ] **Step 1: Write test for Sidebar and Canvas**

```javascript
// tests/browser/e2e/ui.spec.js
// Append to the file:

test('loads endpoints and allows drag to canvas', async ({ page }) => {
  // Mock the API response to provide a consistent test environment
  await page.route('/api/arazzo/endpoints?spec=api.yaml', async route => {
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
```

- [ ] **Step 2: Run test to verify it passes**

Run: `rtk proxy npx playwright test`
Expected: PASS

- [ ] **Step 3: Write test for YAML generation**

```javascript
// tests/browser/e2e/ui.spec.js
// Append to the file:

test('generates yaml successfully', async ({ page }) => {
  // Mock endpoints
  await page.route('/api/arazzo/endpoints?spec=api.yaml', async route => {
    const json = [{ method: 'GET', path: '/users', operationId: 'getUsers' }];
    await route.fulfill({ json });
  });

  // Mock generate API
  await page.route('/api/arazzo/generate', async route => {
    const json = { yaml: 'arazzo: 1.0.1\ninfo:\n  title: Mocked\n  version: 1.0' };
    await route.fulfill({ json });
  });

  await page.goto('/arazzo-builder');
  
  // Drag node to canvas
  const sidebarItem = page.locator('aside >> text=getUsers');
  const canvas = page.locator('.react-flow__pane');
  await sidebarItem.dragTo(canvas);

  // Click Generate YAML
  await page.getByRole('button', { name: 'Generate YAML' }).click();

  // The Monaco Editor doesn't render raw text blocks easily in the DOM (it uses hidden textareas and complex rendering).
  // But we can check if the Monaco editor container becomes visible.
  await expect(page.locator('.monaco-editor')).toBeVisible();
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `rtk proxy npx playwright test`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
rtk proxy git add tests/browser/e2e/ui.spec.js
rtk proxy git commit -m "test: add playwright e2e tests for arazzo builder ui"
```
