<?php

namespace Unisolutions\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridFieldConfig;
use RuntimeException;
use Unisolutions\GridField\CopyButton;
use Unisolutions\Tests\Stub\CopyController;
use Unisolutions\Tests\Stub\CopyRecord;

/**
 * Behavioural tests for the CopyButton GridField component, on Silverstripe 5 and 6.
 *
 * Runs from a host project (the module cannot boot on its own); see README "Running the tests".
 */
class CopyButtonTest extends SapphireTest
{
    protected static $fixture_file = 'CopyButtonTest.yml';

    protected static $extra_dataobjects = [
        CopyRecord::class,
    ];

    /** @var Controller|null pushed by gridField() when a Form is needed, popped in tearDown() */
    private $pushedController = null;

    protected function tearDown(): void
    {
        if ($this->pushedController) {
            $this->pushedController->popCurrent();
            $this->pushedController = null;
        }
        parent::tearDown();
    }

    /**
     * A GridField over all CopyRecords. With $withForm it is attached to a Form on a controller, which
     * GridField_FormAction needs to build its link when a button or menu item is rendered.
     */
    private function gridField(CopyButton $button, bool $withForm = false, $list = null): GridField
    {
        $config = GridFieldConfig::create()->addComponent($button);
        $gridField = GridField::create('Records', 'Records', $list ?: CopyRecord::get(), $config);

        if ($withForm) {
            $controller = CopyController::create();
            $request = new HTTPRequest('GET', '/');
            $request->setSession(new Session([]));
            $controller->setRequest($request);
            $controller->pushCurrent();
            $this->pushedController = $controller;
            Form::create($controller, 'TestForm', FieldList::create($gridField), FieldList::create());
        }

        return $gridField;
    }

    /**
     * The ValidationException class of the running framework major - worked out here independently
     * of the module, so the test does not trust the code it is checking.
     */
    private function expectedValidationException(): string
    {
        return class_exists('SilverStripe\\Core\\Validation\\ValidationException')
            ? 'SilverStripe\\Core\\Validation\\ValidationException'
            : 'SilverStripe\\ORM\\ValidationException';
    }

    // --- Construction and GridField wiring ---

    public function testCreateGoesThroughTheInjectorAndKeepsTheConstructorArgument()
    {
        # Up to 2.0.1 CopyButton had no Injectable trait, so ::create() fatalled.
        $menu = CopyButton::create();
        $column = CopyButton::create(true);

        $this->assertInstanceOf(CopyButton::class, $menu);
        $record = $this->objFromFixture(CopyRecord::class, 'first');
        $this->logInWithPermission('ADMIN');
        # Menu mode by default: a menu title, no column content. Column mode: the reverse.
        $this->assertNotNull($menu->getTitle($this->gridField($menu), $record, 'Actions'));
        $this->assertNull($column->getTitle($this->gridField($column), $record, 'Actions'));
    }

    public function testDeclaresTheCopyActionAndTheActionsColumn()
    {
        $button = new CopyButton();
        $gridField = $this->gridField($button);

        $this->assertSame(['copyrecord'], $button->getActions($gridField));
        $this->assertSame(['Actions'], $button->getColumnsHandled($gridField));
        $this->assertSame(['title' => ''], $button->getColumnMetadata($gridField, 'Actions'));
        $this->assertSame(
            ['class' => 'grid-field__col-compact'],
            $button->getColumnAttributes($gridField, $this->objFromFixture(CopyRecord::class, 'first'), 'Actions')
        );
    }

    public function testAugmentColumnsAddsTheActionsColumnOnce()
    {
        $button = new CopyButton();
        $gridField = $this->gridField($button);

        $columns = ['Title'];
        $button->augmentColumns($gridField, $columns);
        $this->assertSame(['Title', 'Actions'], $columns);

        $button->augmentColumns($gridField, $columns);
        $this->assertSame(['Title', 'Actions'], $columns, 'Actions must not be added twice');
    }

    // --- handleAction(): the copy itself ---

    public function testCopyActionWritesADuplicateOfTheRecord()
    {
        $this->logInWithPermission('ADMIN');
        $button = new CopyButton();
        $original = $this->objFromFixture(CopyRecord::class, 'first');
        $before = CopyRecord::get()->column('ID');

        $button->handleAction($this->gridField($button), 'copyrecord', ['RecordID' => $original->ID], []);

        $new = CopyRecord::get()->exclude('ID', $before);
        $this->assertCount(1, $new, 'exactly one new record');
        $this->assertSame('First record', $new->first()->Title);
        $this->assertNotEquals($original->ID, $new->first()->ID);
        # The original is left alone.
        $this->assertSame('First record', CopyRecord::get()->byID($original->ID)->Title);
    }

    public function testCopyActionIgnoresOtherActionNames()
    {
        $this->logInWithPermission('ADMIN');
        $button = new CopyButton();
        $original = $this->objFromFixture(CopyRecord::class, 'first');
        $count = CopyRecord::get()->count();

        $button->handleAction($this->gridField($button), 'deleterecord', ['RecordID' => $original->ID], []);

        $this->assertSame($count, CopyRecord::get()->count());
    }

    public function testCopyActionOnlyCopiesRecordsInTheGridFieldList()
    {
        # The record is looked up in the GridField's own list, so a RecordID from outside it (a
        # tampered request, or a record filtered out of this grid) is not copied.
        $this->logInWithPermission('ADMIN');
        $button = new CopyButton();
        $second = $this->objFromFixture(CopyRecord::class, 'second');
        $list = CopyRecord::get()->exclude('ID', $second->ID);
        $count = CopyRecord::get()->count();

        $button->handleAction($this->gridField($button, false, $list), 'copyrecord', ['RecordID' => $second->ID], []);
        $button->handleAction($this->gridField($button, false, $list), 'copyrecord', ['RecordID' => 999999], []);

        $this->assertSame($count, CopyRecord::get()->count());
    }

    public function testCopyActionWithoutARecordIdDoesNothingAndRaisesNoWarning()
    {
        # Before 3.0.0 (including 2.0.2) handleAction() read $arguments['RecordID'] unguarded: a
        # request without it raised "Undefined array key". The warning is captured here by our own
        # handler rather than left to the runner, because PHPUnit 9 (SS5) and 11 (SS6) escalate
        # warnings differently.
        $this->logInWithPermission('ADMIN');
        $button = new CopyButton();
        $count = CopyRecord::get()->count();
        $warnings = [];

        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;
            return true;
        });
        try {
            $button->handleAction($this->gridField($button), 'copyrecord', [], []);
            $button->handleAction($this->gridField($button), 'copyrecord', ['RecordID' => ''], []);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings, 'no PHP warning or notice may be raised');
        $this->assertSame($count, CopyRecord::get()->count(), 'nothing may be written');
    }

    /**
     * Regression for #3: 2.0.1 threw the framework-6-only SilverStripe\Core\Validation\ValidationException,
     * so on Silverstripe 5 this path fataled with class-not-found instead of refusing the copy.
     */
    public function testIssue3CopyWithoutCreatePermissionThrowsTheRunningMajorsValidationException()
    {
        $this->logOut();
        $button = new CopyButton();
        $original = $this->objFromFixture(CopyRecord::class, 'first');
        $count = CopyRecord::get()->count();

        try {
            $button->handleAction($this->gridField($button), 'copyrecord', ['RecordID' => $original->ID], []);
            $this->fail('A copy without create permission must be refused');
        } catch (\Throwable $e) {
            $this->assertSame($this->expectedValidationException(), get_class($e), $e->getMessage());
            $this->assertSame('No create permissions', $e->getMessage());
        }

        $this->assertSame($count, CopyRecord::get()->count(), 'nothing may be written');
    }

    public function testCopyThatComesBackUnwrittenThrowsInsteadOfPassingSilently()
    {
        # Up to 2.0.1 this was user_error(..., E_USER_ERROR), deprecated as of PHP 8.4.
        $this->logInWithPermission('ADMIN');
        $button = new CopyButton();
        $broken = $this->objFromFixture(CopyRecord::class, 'broken');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error duplicating record');

        $button->handleAction($this->gridField($button), 'copyrecord', ['RecordID' => $broken->ID], []);
    }

    // --- Column mode (new CopyButton(true)) ---

    public function testColumnModeRendersTheCopyButtonForUsersWhoCanCreate()
    {
        $this->logInWithPermission('ADMIN');
        $button = new CopyButton(true);
        $record = $this->objFromFixture(CopyRecord::class, 'first');

        $html = (string) $button->getColumnContent($this->gridField($button, true), $record, 'Actions');

        $this->assertStringContainsString('gridfield-button-copy', $html);
        $this->assertStringContainsString('action_gridFieldAlterAction', $html);
        $this->assertStringContainsString('title="Copy"', $html);
        # Column mode contributes nothing to the action menu.
        $gridField = $this->gridField($button);
        $this->assertNull($button->getTitle($gridField, $record, 'Actions'));
        $this->assertNull($button->getGroup($gridField, $record, 'Actions'));
        $this->assertNull($button->getExtraData($gridField, $record, 'Actions'));
    }

    public function testColumnModeRendersNothingForUsersWhoCannotCreate()
    {
        $this->logOut();
        $button = new CopyButton(true);
        $record = $this->objFromFixture(CopyRecord::class, 'first');

        $this->assertNull($button->getColumnContent($this->gridField($button, true), $record, 'Actions'));
    }

    // --- Menu mode (new CopyButton(), the default) ---

    public function testMenuModeOffersTheCopyItemForUsersWhoCanCreate()
    {
        $this->logInWithPermission('ADMIN');
        $button = new CopyButton();
        $record = $this->objFromFixture(CopyRecord::class, 'first');
        $gridField = $this->gridField($button, true);

        $this->assertNull($button->getColumnContent($gridField, $record, 'Actions'), 'no column button in menu mode');
        $this->assertSame('Copy', $button->getTitle($gridField, $record, 'Actions'));
        $this->assertSame(GridField_ActionMenuItem::DEFAULT_GROUP, $button->getGroup($gridField, $record, 'Actions'));
        $data = $button->getExtraData($gridField, $record, 'Actions');
        $this->assertIsArray($data);
        $this->assertStringContainsString('gridfield-button-copy', $data['class']);
        $this->assertArrayHasKey('data-url', $data);
    }

    /**
     * Regression for the menu item being offered to users who cannot create (up to 2.0.1 it was shown
     * to everyone and refused only once clicked). A null group is what makes GridField_ActionMenu
     * leave it out, as the core GridFieldDeleteAction does.
     */
    public function testMenuModeHidesTheCopyItemFromUsersWhoCannotCreate()
    {
        $this->logOut();
        $button = new CopyButton();
        $record = $this->objFromFixture(CopyRecord::class, 'first');

        $this->assertNull($button->getGroup($this->gridField($button, true), $record, 'Actions'));
    }

    public function testActionMenuRendersTheCopyItemOnlyForUsersWhoCanCreate()
    {
        # End to end through the framework's own GridField_ActionMenu, which builds the dropdown.
        $button = new CopyButton();
        $gridField = $this->gridField($button, true);
        $menu = new GridField_ActionMenu();
        $gridField->getConfig()->addComponent($menu);
        $record = $this->objFromFixture(CopyRecord::class, 'first');

        $this->logInWithPermission('ADMIN');
        $this->assertStringContainsString(
            'gridfield-button-copy',
            (string) $menu->getColumnContent($gridField, $record, 'Actions')
        );

        $this->logOut();
        $this->assertStringNotContainsString(
            'gridfield-button-copy',
            (string) $menu->getColumnContent($gridField, $record, 'Actions')
        );
    }

    // --- Config ---

    public function testCmsStylesheetIsRegisteredAndExists()
    {
        $leftAndMain = 'SilverStripe\\Admin\\LeftAndMain';
        if (!class_exists($leftAndMain)) {
            $this->markTestSkipped('silverstripe/admin is not installed in this host');
        }

        $css = Config::inst()->get($leftAndMain, 'extra_requirements_css');
        $this->assertContains('restruct/silverstripe-copybutton:css/GridFieldCopyButton.css', $css);

        $module = ModuleLoader::getModule('restruct/silverstripe-copybutton');
        $this->assertNotNull($module, 'module is in the manifest');
        $this->assertTrue($module->getResource('css/GridFieldCopyButton.css')->exists());
    }
}
