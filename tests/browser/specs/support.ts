import { test as base, expect, type Locator, type Page, type Request } from '@playwright/test';

// Shared fixtures and helpers for the copybutton specs.
//
// The CMS screen is the fixture ModelAdmin in tests/browser/fixtures/ (copied into the scratch host
// by the runner): /admin/cb-browser/<tab>, one tab per copy-button setup, each seeded on every
// dev/build with one record per spec (fixtures/CbBRecord.php).

/** The ModelAdmin tabs (managed_models keys), see fixtures/CbBAdmin.php. */
export type Tab = 'menu' | 'column' | 'open' | 'nodetail' | 'parents';

/**
 * test, extended with an automatic console guard: every spec fails if the page logs a console
 * error or throws an uncaught exception at any point, page load included. "Failed to load
 * resource" (any 4xx/5xx asset or request) arrives as a console error too, so a missing module
 * stylesheet or a 500 from the copy action is caught here as well. Warnings (the admin's own Apollo
 * deprecation notices) do not count.
 */
export const test = base.extend<{ consoleGuard: void }>({
    consoleGuard: [
        async ({ page }, use, testInfo) => {
            const errors: string[] = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    errors.push(`console.error: ${msg.text()} (${msg.location().url})`);
                }
            });
            page.on('pageerror', (err) => errors.push(`uncaught: ${err.message}`));

            await use();

            if (errors.length) {
                await testInfo.attach('console-errors', { body: errors.join('\n'), contentType: 'text/plain' });
            }
            expect(errors, 'no console errors or uncaught exceptions').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };

/** The GridField of a ModelAdmin tab (its name is the managed_models key). */
export function listGrid(page: Page, tab: Tab): Locator {
    return page.locator(`#Form_EditForm_${tab}`);
}

/** A GridField nested in a record's edit form, by relation name. */
export function nestedGrid(page: Page, relation: string): Locator {
    return page.locator(`#Form_ItemEditForm_${relation}`);
}

/** Open a ModelAdmin tab with a full page load and wait until its GridField has rows. */
export async function openTab(page: Page, tab: Tab): Promise<Locator> {
    await page.goto(`/admin/cb-browser/${tab}`);
    const grid = listGrid(page, tab);
    await expect(grid.locator('tr.ss-gridfield-item').first()).toBeVisible();
    return grid;
}

/** The rows of a GridField whose Title cell is exactly this title (an original and its copies). */
export function rowsTitled(grid: Locator, title: string): Locator {
    const exact = new RegExp(`^\\s*${title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*$`);
    return grid.locator('tr.ss-gridfield-item').filter({ has: grid.page().locator('td.col-Title', { hasText: exact }) });
}

/** The record IDs of the rows titled so, in list order (original first, see CbBRecord default_sort). */
export async function rowIds(grid: Locator, title: string): Promise<number[]> {
    return (await rowsTitled(grid, title).evaluateAll((rows) => rows.map((r) => r.getAttribute('data-id')))).map(Number);
}

/**
 * Wait until the GridField lists exactly one record titled so that was not in `before`, and return
 * its ID. Counting against a snapshot (not against "2") keeps the specs valid under --repeat-each,
 * where earlier repeats have already made copies of the same seeded row.
 */
export async function expectOneNewRow(grid: Locator, title: string, before: number[]): Promise<number> {
    await expect(rowsTitled(grid, title)).toHaveCount(before.length + 1);
    const added = (await rowIds(grid, title)).filter((id) => !before.includes(id));
    expect(added, 'exactly one new row').toHaveLength(1);
    expect(added[0], 'the copy is a new record').toBeGreaterThan(Math.max(...before));
    return added[0];
}

/** The record ID of a GridField row. */
export async function rowId(row: Locator): Promise<number> {
    return Number(await row.getAttribute('data-id'));
}

/** Column mode: the copy icon button in a row's Actions column. */
export function copyColumnButton(row: Locator): Locator {
    return row.locator('button.gridfield-button-copy');
}

/**
 * Menu mode: open the row's action menu ("...") and return its items. The admin's React action
 * menu renders each item from the row's data-schema, as a .dropdown-item.
 */
export async function openActionMenu(row: Locator): Promise<Locator> {
    await row.locator('.action-menu__toggle').click();
    const menu = row.locator('.action-menu__dropdown');
    await expect(menu).toBeVisible();
    return menu.locator('.dropdown-item');
}

/**
 * Wait for the copy action's POST: the GridField's own URL, carrying the action_gridFieldAlterAction
 * button the copy button (or menu item) submits.
 */
export function waitForCopyPost(page: Page, gridName: string): Promise<Request> {
    const url = new RegExp(`/field/${gridName}(\\?|$)`);
    return page.waitForRequest(
        (r) => r.method() === 'POST' && url.test(r.url()) && (r.postData() ?? '').includes('action_gridFieldAlterAction'),
    );
}

/**
 * Record every DOCUMENT request of the main frame from now on. The copy, and the edit form opened
 * after it, must load through the CMS's own XHR/pjax requests, never by replacing the page.
 * Returns a getter for the URLs seen.
 */
export function watchDocumentNavigations(page: Page): () => string[] {
    const seen: string[] = [];
    page.on('request', (r) => {
        if (r.isNavigationRequest() && r.frame() === page.mainFrame()) {
            seen.push(`${r.method()} ${r.url()}`);
        }
    });
    return () => [...seen];
}

/**
 * Assert a copy request was an AJAX POST answered with 200, and return the response headers. On
 * the open-after-copy path the admin controller answers with an X-ControllerURL header instead of
 * a 302, so a redirect status here would mean the CMS client is bypassed.
 */
export async function expectAjaxOk(request: Request): Promise<Record<string, string>> {
    expect(['xhr', 'fetch'], 'the copy is an AJAX request').toContain(request.resourceType());
    const response = await request.response();
    // On a failure, put the start of the body in the message: in dev mode it is Silverstripe's
    // error page, which names the exception and where it was thrown.
    const status = response?.status();
    const body = status === 200 ? '' : ((await response?.text().catch(() => '')) ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 1500);
    expect(status, `copy response status${body ? `; body: ${body}` : ''}`).toBe(200);
    return response!.headers();
}

/**
 * The path of the page URL. After a GridField re-render the admin rewrites the query string to
 * carry the GridField state (?gridState-<name>-0=...), which is not a navigation; the path is what
 * says which screen is showing.
 */
export function pathOf(page: Page): string {
    return new URL(page.url()).pathname;
}

/**
 * Show a tab of a record's edit form (relation GridFields sit on their own Root.<Relation> tab). The
 * tab strip is rendered in the panel header, outside the form, as jQuery UI tabs (role="tab").
 */
export async function showEditTab(page: Page, tab: string): Promise<void> {
    await page.getByRole('tab', { name: tab, exact: true }).click();
    await expect(page.locator(`#Root_${tab}`)).toBeVisible();
}
