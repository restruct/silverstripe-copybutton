import {
    test,
    expect,
    copyColumnButton,
    expectAjaxOk,
    expectOneNewRow,
    openActionMenu,
    openTab,
    pathOf,
    rowIds,
    rowsTitled,
    waitForCopyPost,
    watchDocumentNavigations,
} from './support';

// Copying a row, with open-after-copy OFF (the default): the copy is written through an AJAX POST,
// the GridField re-renders in place with the copy below its original, and no edit form opens.

test.describe('Menu mode (default)', () => {
    test('"Copy" in the action menu copies the row and re-renders the list in place', async ({ page }) => {
        const grid = await openTab(page, 'menu');
        const pathBefore = pathOf(page);
        const before = await rowIds(grid, 'Menu copy');
        expect(before.length, 'the seeded row is listed').toBeGreaterThan(0);

        const items = await openActionMenu(rowsTitled(grid, 'Menu copy').first());
        const copy = items.filter({ hasText: /^Copy$/ });
        await expect(copy).toHaveCount(1);
        // The menu offers Copy next to the core actions, not instead of them.
        await expect(items.filter({ hasText: /^Edit$/ })).toHaveCount(1);

        const navigations = watchDocumentNavigations(page);
        const posted = waitForCopyPost(page, 'menu');
        await copy.click();
        const headers = await expectAjaxOk(await posted);
        // Open-after-copy is off: the response is the re-rendered GridField, not a redirect.
        expect(headers['x-controllerurl'], 'no redirect header with open-after-copy off').toBeUndefined();

        await expectOneNewRow(grid, 'Menu copy', before);

        // Still on the list: no document request, same screen, no edit form.
        expect(navigations(), 'document navigations after Copy').toEqual([]);
        expect(pathOf(page), 'still on the same screen').toBe(pathBefore);
        await expect(page.locator('#Form_ItemEditForm')).toHaveCount(0);
    });

    test('the action menu still copies after the GridField has re-rendered', async ({ page }) => {
        // The re-rendered GridField replaces the row markup; the admin must re-bind its action
        // menus, and the new rows must carry a working copy action (fresh StateID) again.
        const grid = await openTab(page, 'menu');
        for (let i = 0; i < 2; i++) {
            const before = await rowIds(grid, 'Menu twice');
            const items = await openActionMenu(rowsTitled(grid, 'Menu twice').first());
            const posted = waitForCopyPost(page, 'menu');
            await items.filter({ hasText: /^Copy$/ }).click();
            await expectAjaxOk(await posted);
            await expectOneNewRow(grid, 'Menu twice', before);
        }
    });

    test('no "Copy" item on a record the user may not create', async ({ page }) => {
        // Since 3.0 the menu item hides itself (getGroup() returns null) when the record's
        // canCreate() is false; the fixture denies it for titles starting with "Locked".
        const grid = await openTab(page, 'menu');
        const items = await openActionMenu(rowsTitled(grid, 'Locked menu'));
        await expect(items.filter({ hasText: /^Edit$/ })).toHaveCount(1);
        await expect(items.filter({ hasText: /^Copy$/ })).toHaveCount(0);
    });
});

test.describe('Column mode (CopyButton::create(true))', () => {
    test('the copy icon button copies the row and re-renders the list in place', async ({ page }) => {
        const grid = await openTab(page, 'column');
        const pathBefore = pathOf(page);
        const before = await rowIds(grid, 'Column copy');
        const button = copyColumnButton(rowsTitled(grid, 'Column copy').first());
        await expect(button).toBeVisible();
        await expect(button).toHaveAttribute('title', 'Copy');
        await expect(button).toHaveAttribute('aria-label', 'Copy');

        const navigations = watchDocumentNavigations(page);
        const posted = waitForCopyPost(page, 'column');
        await button.click();
        const headers = await expectAjaxOk(await posted);
        expect(headers['x-controllerurl']).toBeUndefined();

        const copyId = await expectOneNewRow(grid, 'Column copy', before);
        // The copy's row has its own copy button.
        await expect(copyColumnButton(grid.locator(`tr.ss-gridfield-item[data-id="${copyId}"]`))).toBeVisible();
        expect(navigations()).toEqual([]);
        expect(pathOf(page), 'still on the same screen').toBe(pathBefore);
    });

    test('the button sits before the row actions and draws its icon from the module stylesheet', async ({ page }) => {
        // addComponent(CopyButton::create(true), GridFieldEditButton::class) places it before the
        // edit action, which the admin renders inside the row's action menu.
        const grid = await openTab(page, 'column');
        const row = rowsTitled(grid, 'Column copy').first();
        const order = await row.locator('td.action-menu > *').evaluateAll((els) =>
            els.map((el) => (el.matches('button.gridfield-button-copy') ? 'copy' : el.matches('.gridfield-actionmenu__container') ? 'menu' : el.tagName)),
        );
        expect(order).toEqual(['copy', 'menu']);

        // css/GridFieldCopyButton.css (added to LeftAndMain by _config/copybutton.yml) draws the
        // icon as a ::before glyph in the admin icon font. Without the stylesheet the button is an
        // empty, invisible box: this is what proves the stylesheet is exposed and loaded.
        const glyph = await copyColumnButton(row).evaluate((el) => {
            const cs = getComputedStyle(el, '::before');
            const box = el.getBoundingClientRect();
            return { content: cs.content, font: cs.fontFamily, width: box.width, height: box.height };
        });
        expect(glyph.content).toBe('"j"');
        expect(glyph.font.toLowerCase()).toContain('silverstripe');
        expect(glyph.width).toBeGreaterThanOrEqual(44);
        expect(glyph.height).toBeGreaterThanOrEqual(32);
    });

    test('no copy button on a record the user may not create', async ({ page }) => {
        const grid = await openTab(page, 'column');
        const locked = rowsTitled(grid, 'Locked column');
        await expect(locked).toHaveCount(1);
        await expect(copyColumnButton(locked)).toHaveCount(0);
        // The rest of the row is intact (the action menu is still there).
        await expect(locked.locator('.action-menu__toggle')).toBeVisible();
    });
});
