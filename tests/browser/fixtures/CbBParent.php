<?php

namespace Restruct\CbBrowser;

use SilverStripe\Forms\GridField\GridField;
use Unisolutions\GridField\CopyButton;

/**
 * BROWSER-TEST FIXTURE ONLY - tab "parents": a record whose edit form holds two NESTED GridFields
 * with the copy button (column mode, open-after-copy on), see CbBRecord for why this never loads in
 * a real install:
 *
 * - Children (has_many): the copy stays in the list, so its edit form opens at the nested URL
 *   .../item/<parent>/ItemEditForm/field/Children/item/<copy>/edit
 * - Tags (many_many): the copy is not in the list, so the button falls back to a list re-render
 *
 * @method \SilverStripe\ORM\HasManyList Children()
 * @method \SilverStripe\ORM\ManyManyList Tags()
 */
class CbBParent extends CbBRecord
{
    private static $table_name = 'CbBParent';

    private static $singular_name = 'Browser Parent';

    private static $has_many = [
        'Children' => CbBChild::class,
    ];

    private static $many_many = [
        'Tags' => CbBTag::class,
    ];

    protected const SEEDS = ['Nest parent'];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        # The scaffolded relation GridFields (GridFieldConfig_RelationEditor, with a detail form).
        foreach (['Children', 'Tags'] as $relation) {
            $grid = $fields->dataFieldByName($relation);
            if ($grid instanceof GridField) {
                $grid->getConfig()->addComponent(CopyButton::create(true)->setOpenAfterCopy(true));
            }
        }

        return $fields;
    }

    public function requireDefaultRecords()
    {
        # Children and tags first: CbBRecord's seeding replaces the parent, and the old parent's
        # children and tags (with the copies earlier runs made of them) go with it.
        if (static::class === self::class) {
            foreach (CbBChild::get() as $old) {
                $old->delete();
            }
            foreach (CbBTag::get() as $old) {
                $old->delete();
            }
        }

        parent::requireDefaultRecords();

        if (static::class === self::class) {
            $parent = static::get()->filter('Title', 'Nest parent')->first();
            CbBChild::create(['Title' => 'Nest child', 'ParentID' => $parent->ID])->write();
            $tag = CbBTag::create(['Title' => 'Nest tag']);
            $tag->write();
            $parent->Tags()->add($tag);
        }
    }
}
