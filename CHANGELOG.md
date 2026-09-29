# Changelog

## 3.1.0 (unreleased)

### Added

- `CopyButton::setOpenAfterCopy(bool $open = true)` / `getOpenAfterCopy()`: opt-in, opens the copy's
  edit form in the GridField's detail form after a successful copy, instead of re-rendering the list.
  The edit URL is built like `GridFieldEditButton`'s, so it works in a ModelAdmin and in a GridField
  nested in a record's edit form. It falls back to the list re-render when the GridField has no
  `GridFieldDetailForm` or the copy is not in its list (such as a `many_many` list). Off by default,
  so existing GridFields behave as in 3.0.

## 3.0.0 (2026-09-25)

Silverstripe 5 and 6 on one line. See [UPGRADING.md](UPGRADING.md).

### Breaking

- **Silverstripe 4 is no longer supported**: `silverstripe/framework` `^5 || ^6` (was
  `^4 || ^5 || ^6`), and PHP `^8.1`. Silverstripe 4 reached end of life in April 2025; projects on it
  stay on `2.0.x`.
- In menu mode the "Copy" item is no longer offered to users for whom the record's `canCreate()` is
  false (see Changed). Anything that relied on seeing it there, such as a UI test, will not find it.
- `silverstripe/vendor-plugin` `^2 || ^3` is now a direct requirement (the module exposes `css/`
  through it). Recipe-core 5 and 6 already bring it in; a project that pinned vendor-plugin 1.x cannot
  install 3.0.
- `composer.json` still `replace`s `dhensby/silverstripe-copybutton` (both packages ship the same
  class, `Unisolutions\GridField\CopyButton`, so they can never be installed side by side). Since
  upstream now publishes its own 3.0.0, a project that asks for `dhensby/silverstripe-copybutton:^3`
  and gets this package instead gets this package's behaviour (see Changed), not upstream's.

### Fixed

- **#3: the `canCreate()`-denied path fatalled on Silverstripe 4 and 5.** 2.0.1 imported
  `SilverStripe\Core\Validation\ValidationException`, which only framework 6 has, so a user without
  create permission who triggered a copy got a class-not-found error instead of "No create
  permissions". The exception class is now resolved for the running framework major
  (`SilverStripe\ORM\ValidationException` on 5, `SilverStripe\Core\Validation\ValidationException`
  on 6).
- A copy that comes back unwritten now throws a `RuntimeException` instead of calling
  `user_error(..., E_USER_ERROR)`, which is deprecated as of PHP 8.4.
- A copy action whose request carries no `RecordID` is ignored. Before, it raised an
  "Undefined array key" warning.

### Changed

- `CopyButton` extends `AbstractGridFieldComponent`, like the core row actions, so
  `CopyButton::create()` works and the class can be replaced through the Injector. Up to 2.0.1
  only `new CopyButton()` worked.
- Menu mode hides the "Copy" item from users who cannot create the record, the way the core
  `GridFieldDeleteAction` hides itself; column mode already hid its button. Before, the item was
  shown to everyone and refused only once clicked.
- The default branch is `main` (was `master`), and `dev-main` is the `3.x-dev` alias. The 2.0.x line
  lives on branch `v2`.

### Added

- A behavioural test suite (`tests/`) and CI across Silverstripe 5 and 6
  (`.github/workflows/ci.yml`).
- `funding` in `composer.json`.
- A `LICENSE` file. The licence is unchanged (BSD-3-Clause, as `composer.json` always declared); the
  file names the original author and the contributors.
- README: requirements, compatibility table, ModelAdmin usage via `getGridFieldConfig()`, what a
  copy does and does not do, configuration, and how to run the tests. The old example that passed
  `'GridFieldEditButton'` as a short string to `addComponent()` is corrected: that string never
  matched the namespaced class, so the button was appended at the end instead.

## 2.0.2 (2026-09-25)

Hotfix on the `v2` branch for projects that stay on `^2.0`: the fix for #3 (the permission-denied
path fatalled on Silverstripe 4 and 5), and the `LICENSE` file. Nothing else changes.

## 2.0.1

Declared Silverstripe 5 and 6 support (`^4 || ^5 || ^6`). The permission-denied path fatals on 4
and 5, see 3.0.0 "Fixed".

## 2.0.0

Silverstripe 4 line (`^4`; Silverstripe 5 and 6 were added in 2.0.1), forked from
`dhensby/silverstripe-copybutton`.
