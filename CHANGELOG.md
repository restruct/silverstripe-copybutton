# Changelog

## 3.0.0 (unreleased)

Silverstripe 5 and 6 on one line. See [UPGRADING.md](UPGRADING.md).

### Breaking

- **Silverstripe 4 is no longer supported**: `silverstripe/framework` `^5 || ^6` (was
  `^4 || ^5 || ^6`), and PHP `^8.1`. Silverstripe 4 reached end of life in April 2025; projects on it
  stay on `2.0.x`.
- In menu mode the "Copy" item is no longer offered to users for whom the record's `canCreate()` is
  false (see Changed). Anything that relied on seeing it there, such as a UI test, will not find it.

### Fixed

- **#3: the `canCreate()`-denied path fatalled on Silverstripe 4 and 5.** 2.0.1 imported
  `SilverStripe\Core\Validation\ValidationException`, which only framework 6 has, so a user without
  create permission who triggered a copy got a class-not-found error instead of "No create
  permissions". The exception class is now resolved for the running framework major
  (`SilverStripe\ORM\ValidationException` on 5, `SilverStripe\Core\Validation\ValidationException`
  on 6).
- A copy that comes back unwritten now throws a `RuntimeException` instead of calling
  `user_error(..., E_USER_ERROR)`, which is deprecated as of PHP 8.4.

### Changed

- `CopyButton` extends `AbstractGridFieldComponent`, like the core row actions, so
  `CopyButton::create()` works and the class can be replaced through the Injector. Up to 2.0.1
  only `new CopyButton()` worked.
- Menu mode hides the "Copy" item from users who cannot create the record, the way the core
  `GridFieldDeleteAction` hides itself; column mode already hid its button. Before, the item was
  shown to everyone and refused only once clicked.

### Added

- A behavioural test suite (`tests/`) and CI across Silverstripe 5 and 6
  (`.github/workflows/ci.yml`).
- `funding` in `composer.json`.
- README: requirements, compatibility table, ModelAdmin usage via `getGridFieldConfig()`, what a
  copy does and does not do, configuration, and how to run the tests. The old example that passed
  `'GridFieldEditButton'` as a short string to `addComponent()` is corrected: that string never
  matched the namespaced class, so the button was appended at the end instead.

## 2.0.1

Declared Silverstripe 6 support (`^4 || ^5 || ^6`). The permission-denied path fatals on 4 and 5,
see 3.0.0 "Fixed".

## 2.0.0

Silverstripe 4 (later also 5) line, forked from `dhensby/silverstripe-copybutton`.
