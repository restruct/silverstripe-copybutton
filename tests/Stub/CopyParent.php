<?php

namespace Unisolutions\Tests\Stub;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;
use Unisolutions\GridField\CopyButton;

/**
 * Parent record whose edit form holds a nested GridField (the has_many Children), to check that the
 * open-after-copy URL is built for a GridField inside a record's edit form, not only for a top-level
 * ModelAdmin GridField.
 *
 * Not abstract, for the same reason as CopyRecord.
 */
class CopyParent extends DataObject implements TestOnly
{
    private static $table_name = 'CopyButtonTest_Parent';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_many = [
        'Children' => CopyChild::class,
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        # The scaffolded has_many GridField (GridFieldConfig_RelationEditor, so it has a detail form)
        # gets the copy button with open-after-copy on. duplicate() keeps the copy's ParentID, so the
        # copy is in this has_many list and can be opened.
        $children = $fields->dataFieldByName('Children');
        if ($children instanceof GridField) {
            $children->getConfig()->addComponent((new CopyButton(true))->setOpenAfterCopy(true));
        }

        return $fields;
    }
}
