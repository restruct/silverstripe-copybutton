<?php

namespace Unisolutions\GridField;

# Not imported: framework 6 only (framework 4/5 have SilverStripe\ORM\ValidationException).
# The class is resolved at runtime instead, see validationExceptionClass().
//use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;
use RuntimeException;

/**
 * This component provides a button for copying record.
 * First of all it dublicates record and then opens opens edit form {@link GridFieldDetailForm}.
 *
 * NB: the second half of the line above describes the Silverstripe 3 version (1.x). Since 2.x the
 * copy is written and the GridField re-renders with it in the list; no edit form is opened.
 * The button/menu item is only offered to users for whom the record's canCreate() is true.
 *
 * @package    framework
 * @subpackage gridfield
 * @author     Elvinas Liutkevičius <elvinas@unisolutions.eu>
 * @license    BSD http://silverstripe.org/BSD-license
 */
# Extends AbstractGridFieldComponent (framework 5 and 6) for its Injectable trait, like the core row
# actions: CopyButton::create() now works and the class can be swapped through the Injector. Up to
# 2.0.1 the component had no Injectable, so ::create() fatalled and only `new` worked.
class CopyButton extends AbstractGridFieldComponent implements GridField_ColumnProvider, GridField_ActionProvider, GridField_ActionMenuItem
{

    private $useAsColumn;

    public function __construct(bool $useAsColumn = false)
    {
        $this->useAsColumn = $useAsColumn;
    }

    public function augmentColumns($gridField, &$columns)
    {
        if (!in_array('Actions', $columns)) {
            $columns[] = 'Actions';
        }
    }

    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'grid-field__col-compact'];
    }

    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName == 'Actions') {
            return ['title' => ''];
        }
    }

    public function getColumnsHandled($gridField)
    {
        return ['Actions'];
    }

    public function getActions($gridField)
    {
        return ['copyrecord'];
    }

    public function getColumnContent($gridField, $record, $columnName)
    {
        if($this->useAsColumn === false){
            return;
        }

        if (!$record->canCreate()) {
            return;
        }

        $field = $this->getCopyAction($gridField, $record, $columnName);

        return $field->Field();
    }

    private function getCopyAction($gridField, $record, $columnName)
    {
        $title = _t('GridAction.Copy', 'Copy');
        $field = GridField_FormAction::create(
            $gridField,
            'CopyRecord'.$record->ID,
            false,
            "copyrecord",
            ['RecordID' => $record->ID]
        )
            ->addExtraClass('gridfield-button-copy')
            ->setAttribute('classNames', 'font-icon-plus')
            ->setAttribute('title', $title)
            ->setDescription(_t('GridAction.COPY_DESCRIPTION', 'Copy'))
            ->setAttribute('aria-label', $title);

        return $field;
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'copyrecord') {
            /** @var DataObject $item */
            $item = $gridField->getList()->byID($arguments['RecordID']);
            if (!$item) {
                return;
            }

            if (!$item->canCreate()) {
                # The exception class is resolved per framework major (see validationExceptionClass()):
                # 2.0.1 threw the framework-6-only class here, so on SS4/SS5 a user without create
                # permission got a class-not-found fatal instead of the validation message (#3).
                $exceptionClass = static::validationExceptionClass();
                throw new $exceptionClass(
                    _t('GridFieldAction_Copy.CreatePermissionsFailure', "No create permissions"), 0);
            }

            $clone = $item->duplicate();
            if (!$clone || $clone->ID < 1) {
                # Passing E_USER_ERROR to trigger_error()/user_error() is deprecated as of PHP 8.4,
                # which the Silverstripe 6 line runs on. An exception halts the request the same way.
                //user_error("Error Duplicating!", E_USER_ERROR);
                throw new RuntimeException('Error duplicating record ' . get_class($item) . '#' . $item->ID);
            }
        }
    }

    public function getTitle($gridField, $record, $columnName)
    {
        if($this->useAsColumn){
            return;
        }

        return _t('GridAction.Copy', "Copy");
    }

    public function getExtraData($gridField, $record, $columnName)
    {
        if($this->useAsColumn){
            return;
        }

        $field = $this->getCopyAction($gridField, $record, $columnName);

        if ($field) {
            return $field->getAttributes();
        }

        return null;
    }

    public function getGroup($gridField, $record, $columnName)
    {
        if($this->useAsColumn){
            return;
        }

        # No group means GridField_ActionMenu leaves the item out, which is how the core row actions
        # (e.g. GridFieldDeleteAction) hide themselves from users who may not use them. Up to 2.0.1
        # the menu item was offered to everyone and only refused once clicked; column mode already
        # hid the button (see getColumnContent()).
        if (!$record->canCreate()) {
            return null;
        }

        return GridField_ActionMenuItem::DEFAULT_GROUP;
    }

    /**
     * The ValidationException class of the running framework major.
     *
     * Framework 6 moved it from SilverStripe\ORM to SilverStripe\Core\Validation (framework 5.4
     * already deprecates the old name, but only framework 6 ships the new one), so neither name
     * can be imported unconditionally in a module that supports both majors. class_exists()
     * autoloads, which is what we want here: both candidates are plain framework classes.
     * Both names are written as strings, not ::class, so that no `use` import is needed and a
     * short name can never resolve into this file's own namespace.
     *
     * @return string
     */
    protected static function validationExceptionClass(): string
    {
        if (class_exists('SilverStripe\\Core\\Validation\\ValidationException')) {
            # framework 6
            return 'SilverStripe\\Core\\Validation\\ValidationException';
        }

        # framework 4 and 5
        return 'SilverStripe\\ORM\\ValidationException';
    }

}
