silverstripe-copybutton
=======================

Adds a copy/duplicate button to GridFields

## Original author

Elvinas Liutkevičius <elvinas (at) unisolutions (dot) eu>
(Forked from dhensby's SS4-updated fork for quick maintenance & updates/tags)

## Documentation

Simply install the module using the standard method.

To add the button to the GridField you need to extend ModelAdmin and
override getEditForm() method like this:

	function getEditForm($id = null, $fields = null) {
		$form = parent::getEditForm();

		$form
			->Fields()
			->fieldByName($this->sanitiseClassName($this->modelClass))
			->getConfig()
			->addComponent(new CopyButton(), 'GridFieldEditButton') // or just ->addComponent(new CopyButton())
		;

		return $form;
	}


This action will make exact copy of the record (with all relations and etc.).
Note: you have to set configs for cascading duplications in your DataObjects to duplicate relations
See: https://docs.silverstripe.org/en/4/developer_guides/model/relations/#cascading-duplications

Sometimes you will need to do some actions just after copy operation (i.e.
you'll need to remove some relations). It is easily achieved by extending
DataObject and writing all the actions in the onAfterDuplicate() method.

	class SomeObjectExtension extends DataExtension {

		public function onAfterDuplicate() {
			DB::query("delete from Member_SomeObject where SomeObjectID = ".$this->owner->ID);
		}

	}

## Open the copy after copying

From 2.1, the button can open the copy's edit form straight away instead of re-rendering the list.
It is off by default:

```php
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Unisolutions\GridField\CopyButton;

$config->addComponent((new CopyButton())->setOpenAfterCopy(true), GridFieldEditButton::class);
```

(2.x has no `CopyButton::create()`; 3.x does.)

The copy opens in the GridField's own detail form (`GridFieldDetailForm`), at the same URL the row's
edit button links to. That works for a GridField in a ModelAdmin and for one nested in a record's
edit form. In the CMS the redirect is answered with an `X-ControllerURL` header, and the admin loads
the edit form into the panel; outside the CMS it is a normal 302 redirect.

When the GridField cannot open the copy, the button falls back to re-rendering the list, without an
error:

* the GridField has no `GridFieldDetailForm` (for example `GridFieldConfig_Base`), or
* the copy is not in the GridField's list. `duplicate()` does not add the copy to a `many_many`
  list, or to a list filtered on something the copy does not match; a `has_many` list keeps it,
  because the copy keeps the parent's ID.

This replaces redirecting from the model's `onAfterDuplicate()`, which fires on every
`duplicate()` call, not only on a click of this button.

## Running the tests

The module cannot be tested on its own: it needs a host Silverstripe project, with
silverstripe/admin (recipe-cms) so the ModelAdmin tests run rather than skip. Require the module there through a Composer path repository with
`symlink: true`, add `"Unisolutions\\Tests\\": "vendor/restruct/silverstripe-copybutton/tests/"` to
the host's `autoload-dev`, then:

```bash
# Silverstripe 4 and 5 (PHPUnit 9) - the path must come before flush=1
vendor/bin/phpunit vendor/restruct/silverstripe-copybutton/tests flush=1

# Silverstripe 6 (PHPUnit 11) - a flush=1 argument is ignored, use the env var
SS_PHPUNIT_FLUSH=1 vendor/bin/phpunit vendor/restruct/silverstripe-copybutton/tests
```
