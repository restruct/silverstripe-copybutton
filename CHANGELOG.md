# Changelog

## 2.1.0 (unreleased)

### Added

- `CopyButton::setOpenAfterCopy(bool $open = true)` / `getOpenAfterCopy()`: opt-in, opens the copy's
  edit form in the GridField's detail form after a successful copy, instead of re-rendering the list.
  The edit URL is built like `GridFieldEditButton`'s, so it works in a ModelAdmin and in a GridField
  nested in a record's edit form. It falls back to the list re-render when the GridField has no
  `GridFieldDetailForm` or the copy is not in its list (such as a `many_many` list). Off by default,
  so existing GridFields behave as in 2.0. Backported from 3.1.0.
- Behavioural tests for this option (`tests/`), kept out of dist installs through `.gitattributes`.

## 2.0.2 (2026-09-25)

Hotfix for projects that stay on `^2.0`. The maintained line is 3.x on `main` (Silverstripe 5 and 6).

### Fixed

- **#3: the `canCreate()`-denied path fatalled on Silverstripe 4 and 5.** 2.0.1 imported
  `SilverStripe\Core\Validation\ValidationException`, which only framework 6 has, so a user without
  create permission who triggered a copy got a class-not-found error instead of "No create
  permissions". The exception class is now resolved for the running framework major
  (`SilverStripe\ORM\ValidationException` on 4 and 5, `SilverStripe\Core\Validation\ValidationException`
  on 6).

### Added

- A `LICENSE` file. The licence is unchanged (BSD-3-Clause, as `composer.json` always declared); the
  file names the original author and the contributors.

## 2.0.1

Declared Silverstripe 5 and 6 support (`^4 || ^5 || ^6`). The permission-denied path fatals on 4
and 5, see 2.0.2.

## 2.0.0

Silverstripe 4 line (`^4`; Silverstripe 5 and 6 were added in 2.0.1), forked from
`dhensby/silverstripe-copybutton`.
