<?php

namespace Unisolutions\GridField;

# Not imported: framework 6 only (framework 4/5 have SilverStripe\ORM\ValidationException).
# The class is resolved at runtime instead, see validationExceptionClass().
//use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\GridField\AbstractGridFieldComponent;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\ORM\DataObject;
use RuntimeException;

/**
 * This component provides a button for copying record.
 * First of all it dublicates record and then opens opens edit form {@link GridFieldDetailForm}.
 *
 * NB: the second half of the line above describes the Silverstripe 3 version (1.x). Since 2.x the
 * copy is written and the GridField re-renders with it in the list; no edit form is opened.
 * From 3.1 the edit form can be opened again, opt-in, with {@link setOpenAfterCopy()}.
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

    /**
     * Whether a successful copy opens the copy's edit form instead of re-rendering the list.
     * Off by default so existing GridFields keep their 2.x/3.0 behaviour.
     *
     * @var bool
     */
    private $openAfterCopy = false;

    public function __construct(bool $useAsColumn = false)
    {
        $this->useAsColumn = $useAsColumn;
    }

    /**
     * Open the copy's edit form (the GridField's own detail form) after a successful copy, instead of
     * re-rendering the list with the copy in it.
     *
     * Falls back to the list re-render when this GridField cannot open the copy: it has no
     * GridFieldDetailForm, or the copy is not in its list (a many_many list, or a filtered one, does
     * not gain the copy through duplicate() alone).
     *
     * @param bool $open
     * @return $this
     */
    public function setOpenAfterCopy(bool $open = true): static
    {
        $this->openAfterCopy = $open;
        return $this;
    }

    /**
     * @return bool
     */
    public function getOpenAfterCopy(): bool
    {
        return $this->openAfterCopy;
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
            # `classNames` is action-menu data, not an HTML attribute: set here it also rendered as a
            # literal classnames="..." attribute on the column button (#6). getExtraData() adds it
            # to the menu data instead.
            //->setAttribute('classNames', 'font-icon-plus')
            ->setAttribute('title', $title)
            ->setDescription(_t('GridAction.COPY_DESCRIPTION', 'Copy'))
            ->setAttribute('aria-label', $title);

        return $field;
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'copyrecord') {
            # A request whose action state carries no RecordID (replayed or hand-crafted POST) has
            # nothing to copy. Before 3.0.0 (including 2.0.2) the lookup below read the missing key
            # directly and raised an "Undefined array key" warning, which a consuming project's test
            # suite escalates.
            if (empty($arguments['RecordID'])) {
                return;
            }

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

            # Opt-in (setOpenAfterCopy()): open the copy's edit form. Returning the redirect response
            # makes GridField::gridFieldAlterAction() hand it back as-is instead of re-rendering the list.
            # A null result (option off, or the copy cannot be opened here) keeps the default behaviour.
            if ($this->openAfterCopy) {
                return $this->redirectToEditForm($gridField, $clone);
            }
        }
    }

    /**
     * Redirect to the copy's edit form in this GridField's detail form, or return null when that is
     * not possible and the list should simply re-render.
     *
     * The redirect goes through the current controller, which in the CMS is the admin controller
     * (LeftAndMain on Silverstripe 5, AdminController on 6), also for a GridField nested in a record's
     * edit form: the GridField and item request handlers in between are RequestHandlers, not
     * Controllers. On the CMS's ajax GridField request that controller's redirect() answers with an
     * X-ControllerURL header, and the admin client then loads the edit form into the panel.
     * Outside the CMS, Controller::redirect() gives a plain 302 to the same URL.
     *
     * @param GridField $gridField
     * @param DataObject $clone the written copy
     * @return HTTPResponse|null
     */
    protected function redirectToEditForm(GridField $gridField, DataObject $clone): ?HTTPResponse
    {
        # Without a detail form this GridField has no edit URL to go to (e.g. a read-only list).
        if (!$gridField->getConfig()->getComponentByType(GridFieldDetailForm::class)) {
            return null;
        }

        # The detail form only opens records it finds in the GridField's list. duplicate() does not add
        # the copy to a many_many list, or to a list filtered on something the copy does not match, so
        # the edit URL would 404 there.
        if (!$gridField->getList()->byID($clone->ID)) {
            return null;
        }

        # GridField::Link() is built from its Form's action; a GridField outside a Form (only seen in
        # code that calls handleAction() directly) has no URL at all.
        if (!$gridField->getForm()) {
            return null;
        }

        # The same URL GridFieldEditButton::getUrl() builds for a row, so it works for a GridField in a
        # ModelAdmin as well as for one nested in a record's edit form (whose Link() already includes
        # the parent item's path). addAllStateToUrl() carries the other GridFields' state along, as
        # the edit button does. It is called with one argument: framework 5 has an optional second
        # one, framework 6 does not.
        $link = $gridField->addAllStateToUrl(
            Controller::join_links($gridField->Link('item'), $clone->ID, 'edit')
        );

        $controller = Controller::curr();
        if (!$controller) {
            return null;
        }

        return $controller->redirect($link);
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
            # The admin's React action menu builds the dropdown item's class from `action` plus this
            # data's `classNames` only; the button's own `class` (gridfield-button-copy) never
            # reaches it, on framework 5 or 6. font-icon-plus gives the item the admin's plus icon,
            # as font-icon-edit/-trash do for the core items on framework 5.
            return array_merge($field->getAttributes(), ['classNames' => 'font-icon-plus']);
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
