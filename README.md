silverstripe-copybutton
=======================

Adds a copy/duplicate button to GridFields

*Maintained by [Restruct](https://github.com/restruct). If this module saves you time, you can
[support ongoing maintenance](https://github.com/sponsors/restruct).*

## Original author

Elvinas Liutkevičius <elvinas (at) unisolutions (dot) eu>
(Forked from dhensby's SS4-updated fork for quick maintenance & updates/tags)

## Requirements

* Silverstripe 5 or 6 (`silverstripe/framework`)
* PHP 8.1 or newer (Silverstripe 6 itself needs 8.3)

## Installation

```
composer require restruct/silverstripe-copybutton
```

## Version compatibility

| Branch | Module version | Silverstripe | PHP |
|--------|----------------|--------------|-----|
| `main` | `3.x` | `^5 \|\| ^6` | `^8.1` |
| `v2` | `2.0.x` | `^4 \|\| ^5 \|\| ^6` (see note) | not declared |
| `1`, `1.0` | `1.x` | `^3` | not declared |

`2.0.1` declares Silverstripe 4, 5 and 6 but throws a Silverstripe 6-only exception class when a
user without create permission triggers a copy, so on 4 and 5 that path fatals (issue #3). `3.x`
and the `2.0.2` hotfix fix it. Silverstripe 4 reached end of life in April 2025 and is no longer supported or tested here;
projects still on it should stay on `2.0.x`.

`main` is the maintained line: it supports every Silverstripe version this module still targets.
The `v2` branch exists only for hotfixes to projects that stay on `^2.0` (such as `2.0.2`).

**`composer.json` is the source of truth** for exact constraints; this table is a quick reference.

## Usage

The component is `Unisolutions\GridField\CopyButton`. Add it to a GridField's config:

```php
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Unisolutions\GridField\CopyButton;

// As an item in the row's action menu (the "..." dropdown) - the default:
$config->addComponent(CopyButton::create());

// Or as a button in the Actions column, placed before the edit button:
$config->addComponent(CopyButton::create(true), GridFieldEditButton::class);
```

`CopyButton::create()` and `new CopyButton()` both work from 3.0 (2.x only supported `new`).
The one constructor argument is `$useAsColumn` (default `false`): `false` puts "Copy" in the row's
action menu, `true` renders an icon button in the Actions column instead.

Pass the class name, not a short string, as the second `addComponent()` argument: GridFieldConfig
places the component before the first existing component that is an `instanceof` it, and silently
appends it at the end when nothing matches.

In a ModelAdmin, the place to do this is `getGridFieldConfig()` (or the `updateGridFieldConfig`
extension hook):

```php
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Unisolutions\GridField\CopyButton;

class MyAdmin extends ModelAdmin
{
    protected function getGridFieldConfig(): GridFieldConfig
    {
        $config = parent::getGridFieldConfig();
        $config->addComponent(CopyButton::create(), GridFieldEditButton::class);
        return $config;
    }
}
```

## What a copy does

Clicking Copy calls `DataObject::duplicate()` on the record and writes the copy; the GridField then
re-renders with the copy in the list. No edit form is opened.

* **Relations are copied only where the model says so.** `duplicate()` follows the model's
  `cascade_duplicates` config; see
  [cascading duplications](https://docs.silverstripe.org/en/6/developer_guides/model/relations/#cascading-duplications).
* **Only records in the GridField's own list** can be copied: the record is looked up in that list,
  so a request for any other ID does nothing.
* **Permissions:** the button (column mode) or menu item (menu mode) is only shown to users for whom
  the record's `canCreate()` is true. A copy request from anyone else is refused with a
  `ValidationException` ("No create permissions") and nothing is written.

To act on the copy - say, to clear some relations - implement `onAfterDuplicate()` on the model or
in an extension. It runs on the **copy**, after it was written:

```php
use SilverStripe\Core\Extension;

class SomeObjectExtension extends Extension
{
    public function onAfterDuplicate($original, $doWrite, $relations)
    {
        $this->getOwner()->Members()->removeAll();
    }
}
```

## Configuration

The module's only configuration adds its stylesheet to the CMS:

```yaml
SilverStripe\Admin\LeftAndMain:
  extra_requirements_css:
    - "restruct/silverstripe-copybutton:css/GridFieldCopyButton.css"
```

Labels are translatable under `GridAction.Copy`, `GridAction.COPY_DESCRIPTION` and
`GridFieldAction_Copy.CreatePermissionsFailure` (see `lang/`).

## Running the tests

The module cannot be tested on its own: it needs a host Silverstripe project. Require it there
through a Composer **path repository with `symlink: true`** - `/tests` is `export-ignore`, so a dist
or mirrored install contains no tests - add `"Unisolutions\\Tests\\":
"vendor/restruct/silverstripe-copybutton/tests/"` to the host's `autoload-dev`, then:

```bash
# Silverstripe 5 (PHPUnit 9) - the path must come before flush=1
vendor/bin/phpunit vendor/restruct/silverstripe-copybutton/tests flush=1

# Silverstripe 6 (PHPUnit 11) - a flush=1 argument is ignored, use the env var
SS_PHPUNIT_FLUSH=1 vendor/bin/phpunit vendor/restruct/silverstripe-copybutton/tests
```

CI runs the same suite against Silverstripe 5 and 6 on every push; see `.github/workflows/ci.yml`.

## Relation to dhensby/silverstripe-copybutton

This package `replace`s `dhensby/silverstripe-copybutton` (and `unisolutions/silverstripe-copybutton`):
all three ship the same class, so only one of them can be installed. Upstream publishes its own
Silverstripe 6-only 3.0.0; this package's 3.x covers Silverstripe 5 and 6 and differs in behaviour
(see [CHANGELOG.md](CHANGELOG.md)).

## Licence

BSD-3-Clause, see [LICENSE](LICENSE).
