import { test, expect, type Locator, type Page } from '@playwright/test';
import * as path from 'path';

// Generates the README screenshots in docs/images/. Not a test suite: run it with
// playwright.screenshots.config.ts (see README.md next to this file). The content comes from the
// screenshot fixtures in ./fixtures/: an "Events" ModelAdmin with one tab per copy-button setup
// (Events: menu mode, Ticket types: column mode, Newsletters: menu mode with open-after-copy),
// re-seeded on every dev/build.

const IMAGES = path.join(__dirname, '..', '..', '..', 'docs', 'images');

/** The GridField of a ShotAdmin tab (its name is the managed_models key). */
async function openTab(page: Page, tab: string): Promise<Locator> {
    await page.goto(`/admin/events/${tab}`);
    const grid = page.locator(`#Form_EditForm_${tab}`);
    await expect(grid.locator('tr.ss-gridfield-item').first()).toBeVisible();
    return grid;
}

/** The rows of a GridField whose Title cell is exactly this title. */
function rowsTitled(grid: Locator, title: string): Locator {
    const exact = new RegExp(`^\\s*${title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*$`);
    return grid.locator('tr.ss-gridfield-item').filter({ has: grid.page().locator('td.col-Title', { hasText: exact }) });
}

/** Wait until the GridField POST that a copy sends has been answered. */
function copyPosted(page: Page, gridName: string): Promise<unknown> {
    const url = new RegExp(`/field/${gridName}(\\?|$)`);
    return page.waitForResponse((r) => r.request().method() === 'POST' && url.test(r.url()) && r.status() === 200);
}

/** No hover or focus highlight left on what was just clicked. */
async function settle(page: Page): Promise<void> {
    await page.mouse.move(1270, 710);
    await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
    await page.waitForTimeout(400);
}

/** The window from the top down to just below `bottom` (the empty panel underneath adds nothing). */
async function clipTo(page: Page, bottom: Locator, extra = 24) {
    const box = (await bottom.boundingBox())!;
    return { x: 0, y: 0, width: 1280, height: Math.min(720, Math.ceil(box.y + box.height + extra)) };
}

test('Menu mode: "Copy" in a row\'s action menu', async ({ page }) => {
    const grid = await openTab(page, 'events');
    const row = rowsTitled(grid, 'Summer workshop: watercolour');
    await row.locator('.action-menu__toggle').click();
    const menu = row.locator('.action-menu__dropdown');
    await expect(menu.locator('.dropdown-item').filter({ hasText: /^Copy$/ })).toBeVisible();
    // Keep the menu open: move the pointer onto the Copy item, as a user about to click it would.
    await menu.locator('.dropdown-item').filter({ hasText: /^Copy$/ }).hover();
    await page.waitForTimeout(400);

    await page.screenshot({ path: path.join(IMAGES, 'menu-mode.png'), clip: await clipTo(page, menu) });
});

test('Column mode: the copy icon button, and the copy below its original', async ({ page }) => {
    const grid = await openTab(page, 'tickets');
    const posted = copyPosted(page, 'tickets');
    await rowsTitled(grid, 'Family (2 adults, 2 children)').locator('button.gridfield-button-copy').click();
    await posted;
    await expect(rowsTitled(grid, 'Family (2 adults, 2 children)')).toHaveCount(2);
    await settle(page);

    await page.screenshot({ path: path.join(IMAGES, 'column-mode.png'), clip: await clipTo(page, grid) });
});

test('Open after copy: the copy\'s edit form', async ({ page }) => {
    const grid = await openTab(page, 'newsletters');
    const row = rowsTitled(grid, 'May newsletter');
    await row.locator('.action-menu__toggle').click();
    await row.locator('.action-menu__dropdown .dropdown-item').filter({ hasText: /^Copy$/ }).click();
    // The admin loads the copy's edit form into the panel (X-ControllerURL), at .../item/<id>/edit.
    const form = page.locator('#Form_ItemEditForm');
    await expect(form).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/events\/newsletters\/.*item\/\d+\/edit/);
    await expect(form.locator('input[name="Title"]')).toHaveValue('May newsletter');
    await settle(page);

    await page.screenshot({ path: path.join(IMAGES, 'open-after-copy.png'), clip: await clipTo(page, form.locator('#Form_ItemEditForm_Intro_Holder')) });
});
