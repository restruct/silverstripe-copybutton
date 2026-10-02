import {
    test,
    expect,
    copyColumnButton,
    expectAjaxOk,
    expectOneNewRow,
    nestedGrid,
    openActionMenu,
    openTab,
    pathOf,
    rowId,
    rowIds,
    rowsTitled,
    showEditTab,
    waitForCopyPost,
    watchDocumentNavigations,
} from './support';

// setOpenAfterCopy(true) (3.1 / 2.1): after a copy the CMS loads the COPY's edit form into the
// panel. The server answers the AJAX copy with an X-ControllerURL header, and the admin client
// follows it with its own pjax request: no document navigation. Where the GridField cannot open the
// copy, the list re-renders instead, without an error.

test.describe('Open after copy', () => {
    test('a copy in a ModelAdmin list opens the copy\'s edit form', async ({ page }) => {
        const grid = await openTab(page, 'open');
        const original = rowsTitled(grid, 'Open copy').first();
        const before = await rowIds(grid, 'Open copy');

        const navigations = watchDocumentNavigations(page);
        const items = await openActionMenu(original);
        const posted = waitForCopyPost(page, 'open');
        await items.filter({ hasText: /^Copy$/ }).click();
        const headers = await expectAjaxOk(await posted);

        // The redirect target is the copy's edit URL, built like the row's edit button link.
        const target = headers['x-controllerurl'] ?? '';
        const match = target.match(/\/admin\/cb-browser\/open\/EditForm\/field\/open\/item\/(\d+)\/edit/);
        expect(match, `X-ControllerURL is the copy's edit URL (got "${target}")`).not.toBeNull();
        const copyId = Number(match![1]);
        expect(copyId, 'the opened record is the copy, not the original or an earlier copy').toBeGreaterThan(Math.max(...before));

        // The admin loaded that edit form into the panel.
        await expect(page).toHaveURL(new RegExp(`/item/${copyId}/edit`));
        const form = page.locator('#Form_ItemEditForm');
        await expect(form).toBeVisible();
        await expect(form.locator('input[name="Title"]')).toHaveValue('Open copy');
        expect(navigations(), 'document navigations after Copy').toEqual([]);
    });

    test('a GridField without a detail form falls back to re-rendering the list', async ({ page }) => {
        const grid = await openTab(page, 'nodetail');
        const pathBefore = pathOf(page);
        const before = await rowIds(grid, 'No detail copy');
        const navigations = watchDocumentNavigations(page);

        const posted = waitForCopyPost(page, 'nodetail');
        await copyColumnButton(rowsTitled(grid, 'No detail copy').first()).click();
        const headers = await expectAjaxOk(await posted);
        expect(headers['x-controllerurl'], 'nothing to open without a GridFieldDetailForm').toBeUndefined();

        await expectOneNewRow(grid, 'No detail copy', before);
        expect(pathOf(page), 'still on the same screen').toBe(pathBefore);
        expect(navigations()).toEqual([]);
    });
});

test.describe('Open after copy, GridField nested in a record\'s edit form', () => {
    /** Open the seeded parent's edit form from the list (a full page load of the list, then pjax). */
    async function openParent(page: import('@playwright/test').Page) {
        const grid = await openTab(page, 'parents');
        const parentId = await rowId(rowsTitled(grid, 'Nest parent'));
        await page.goto(`/admin/cb-browser/parents/EditForm/field/parents/item/${parentId}/edit`);
        await expect(page.locator('#Form_ItemEditForm')).toBeVisible();
        return parentId;
    }

    test('a has_many copy opens at the nested edit URL', async ({ page }) => {
        const parentId = await openParent(page);
        await showEditTab(page, 'Children');
        const children = nestedGrid(page, 'Children');
        const original = rowsTitled(children, 'Nest child').first();
        const before = await rowIds(children, 'Nest child');
        expect(before.length, 'the seeded child is listed').toBeGreaterThan(0);

        const navigations = watchDocumentNavigations(page);
        const posted = waitForCopyPost(page, 'Children');
        await copyColumnButton(original).click();
        const headers = await expectAjaxOk(await posted);

        // The parent's item path is part of the URL: the nested GridField's Link() already
        // includes it, which is what CopyButton relies on.
        const target = headers['x-controllerurl'] ?? '';
        const nested = new RegExp(
            `/admin/cb-browser/parents/EditForm/field/parents/item/${parentId}/ItemEditForm/field/Children/item/(\\d+)/edit`,
        );
        const match = target.match(nested);
        expect(match, `X-ControllerURL is the copy's nested edit URL (got "${target}")`).not.toBeNull();
        const copyId = Number(match![1]);
        expect(copyId).toBeGreaterThan(Math.max(...before));

        await expect(page).toHaveURL(new RegExp(`/field/Children/item/${copyId}/edit`));
        const form = page.locator('#Form_ItemEditForm');
        await expect(form.locator('input[name="Title"]')).toHaveValue('Nest child');
        expect(navigations()).toEqual([]);
    });

    test('a many_many copy (not in the list) falls back to re-rendering the list', async ({ page }) => {
        await openParent(page);
        await showEditTab(page, 'Tags');
        const pathBefore = pathOf(page);
        const tags = nestedGrid(page, 'Tags');
        await expect(rowsTitled(tags, 'Nest tag')).toHaveCount(1);

        const navigations = watchDocumentNavigations(page);
        const posted = waitForCopyPost(page, 'Tags');
        await copyColumnButton(rowsTitled(tags, 'Nest tag')).click();
        const headers = await expectAjaxOk(await posted);
        expect(headers['x-controllerurl'], 'the copy is not in the many_many list, so nothing to open').toBeUndefined();

        // duplicate() does not add the copy to the many_many list: the list re-renders unchanged,
        // and the parent's edit form stays open.
        await expect(tags.locator('tr.ss-gridfield-item')).toHaveCount(1);
        await expect(rowsTitled(tags, 'Nest tag')).toHaveCount(1);
        await expect(copyColumnButton(rowsTitled(tags, 'Nest tag'))).toBeVisible();
        expect(pathOf(page), 'still on the same screen').toBe(pathBefore);
        expect(navigations()).toEqual([]);
    });
});
