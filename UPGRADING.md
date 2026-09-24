# Upgrading

## 2.x to 3.0

1. **Check your Silverstripe version.** 3.0 needs Silverstripe 5 or 6 and PHP 8.1+. On Silverstripe
   4, stay on `^2.0`.
2. **Widen your constraint.** `^2.0` / `~2` will not pick up 3.0:
   ```
   composer require restruct/silverstripe-copybutton:^3
   ```
   If you stay on `^2.0`, update to 2.0.2 at least: on Silverstripe 4 and 5 the permission-denied
   path of 2.0.1 fatals (issue #3).
3. **No code changes are required.** The class name (`Unisolutions\GridField\CopyButton`), the
   constructor argument and the translation keys are unchanged. `CopyButton::create()` now also works.
4. **Behaviour change to check:** in menu mode, users for whom the record's `canCreate()` is false
   no longer see the "Copy" item. If some users should be able to copy, make sure `canCreate()`
   (or an extension's `canCreate`) grants it to them - copying always required it; only the menu
   item's visibility changed.
5. If you caught `user_error` output from a failed copy, catch `RuntimeException` instead.
6. **New direct requirement:** `silverstripe/vendor-plugin` `^2 || ^3`. Recipe-core already brings it
   in; only a project that pinned vendor-plugin 1.x has to lift that pin.
7. **Silverstripe 5 only:** the permission-denied path throws `SilverStripe\ORM\ValidationException`,
   which framework 5.4 marks deprecated. With deprecation notices switched on you will see one on
   that path. It is expected, not a new bug: the Silverstripe 6 name does not exist on 5.
8. **If you required `dhensby/silverstripe-copybutton`:** this package `replace`s it, because both
   ship the same class and cannot be installed together. If Composer picks this package for that
   name, you get this package's behaviour (step 4), not upstream's 3.0.0.
