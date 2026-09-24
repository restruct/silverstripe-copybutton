# Upgrading

## 2.x to 3.0

1. **Check your Silverstripe version.** 3.0 needs Silverstripe 5 or 6 and PHP 8.1+. On Silverstripe
   4, stay on `^2.0`.
2. **Widen your constraint.** `^2.0` / `~2` will not pick up 3.0:
   ```
   composer require restruct/silverstripe-copybutton:^3
   ```
   If you are on Silverstripe 5 and stay on 2.0.1, note that its permission-denied path fatals
   (issue #3).
3. **No code changes are required.** The class name (`Unisolutions\GridField\CopyButton`), the
   constructor argument and the translation keys are unchanged. `CopyButton::create()` now also works.
4. **Behaviour change to check:** in menu mode, users for whom the record's `canCreate()` is false
   no longer see the "Copy" item. If some users should be able to copy, make sure `canCreate()`
   (or an extension's `canCreate`) grants it to them - copying always required it; only the menu
   item's visibility changed.
5. If you caught `user_error` output from a failed copy, catch `RuntimeException` instead.
