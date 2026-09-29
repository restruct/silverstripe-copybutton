<?php

namespace Unisolutions\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Unisolutions\GridField\CopyButton;
use Unisolutions\Tests\Stub\CopyAdmin;
use Unisolutions\Tests\Stub\CopyChild;
use Unisolutions\Tests\Stub\CopyController;
use Unisolutions\Tests\Stub\CopyParent;
use Unisolutions\Tests\Stub\CopyRecord;

/**
 * Behavioural tests for CopyButton::setOpenAfterCopy() (2.1.0), on Silverstripe 5 (and 4/6 where a host has them).
 *
 * The first group calls handleAction() on a GridField in a Form on a plain controller. The second group goes through a real ModelAdmin over HTTP, for a top-level
 * GridField and for one nested in a record's edit form, so the CMS's own routing, GridField request
 * handling and admin redirect() (X-ControllerURL on ajax) are exercised too.
 *
 * Runs from a host project (the module cannot boot on its own); see README "Running the tests".
 */
class OpenAfterCopyTest extends FunctionalTest
{
    protected static $fixture_file = 'OpenAfterCopyTest.yml';

    protected static $extra_dataobjects = [
        CopyRecord::class,
        CopyParent::class,
        CopyChild::class,
    ];

    protected static $extra_controllers = [
        CopyAdmin::class,
    ];

    /** @var Controller|null pushed by gridField(), popped in tearDown() */
    private $pushedController = null;

    /**
     * CopyAdmin extends ModelAdmin: without silverstripe/admin, routing it (which instantiates it)
     * would fatal. The admin tests skip in that case instead.
     */
    public static function getExtraControllers()
    {
        return static::adminInstalled() ? parent::getExtraControllers() : [];
    }

    /**
     * Route CopyAdmin the way AdminRootController routes a real admin: by its url_rule
     * ('/$ModelClass/$Action' for a ModelAdmin), so ModelAdmin::init() gets the ModelClass param and
     * opens the requested tab. SapphireTest's default route ('//$Action/$ID/$OtherID') would leave
     * ModelClass empty and always open the first managed model.
     */
    protected function getExtraRoutes()
    {
        $rules = [];
        foreach (static::getExtraControllers() as $class) {
            $link = rtrim(Director::makeRelative(Controller::singleton($class)->Link()) ?? '', '/');
            $rules[$link . '//' . ltrim(Config::inst()->get($class, 'url_rule') ?: '$Action/$ID/$OtherID', '/')] = $class;
        }
        return $rules;
    }

    private static function adminInstalled(): bool
    {
        return class_exists('SilverStripe\\Admin\\ModelAdmin');
    }

    protected function tearDown(): void
    {
        if ($this->pushedController) {
            $this->pushedController->popCurrent();
            $this->pushedController = null;
        }
        parent::tearDown();
    }

    /**
     * A GridField in a Form on a plain (non-ajax) controller, which is pushed as the current
     * controller, as it is during a real request.
     */
    private function gridField(CopyButton $button, bool $withDetailForm = true, $list = null): GridField
    {
        $config = GridFieldConfig::create()->addComponent($button);
        if ($withDetailForm) {
            $config->addComponent(GridFieldDetailForm::create());
        }
        $gridField = GridField::create('Records', 'Records', $list ?: CopyRecord::get(), $config);

        $controller = CopyController::create();
        $request = new HTTPRequest('POST', '/');
        $request->setSession(new Session([]));
        $controller->setRequest($request);
        $controller->pushCurrent();
        $this->pushedController = $controller;
        Form::create($controller, 'TestForm', FieldList::create($gridField), FieldList::create());

        return $gridField;
    }

    /** The one CopyRecord written since $before was taken. */
    private function newRecord(array $before): CopyRecord
    {
        $new = CopyRecord::get()->exclude('ID', $before);
        $this->assertCount(1, $new, 'exactly one copy is written');
        return $new->first();
    }

    // --- The option ---

    public function testOpenAfterCopyIsOffByDefaultAndTheSetterIsFluent()
    {
        $button = new CopyButton();
        $this->assertFalse($button->getOpenAfterCopy(), 'off by default: no behaviour change for 2.0 users');

        $this->assertSame($button, $button->setOpenAfterCopy());
        $this->assertTrue($button->getOpenAfterCopy(), 'setOpenAfterCopy() without argument turns it on');

        $button->setOpenAfterCopy(false);
        $this->assertFalse($button->getOpenAfterCopy());
    }

    // --- handleAction() on a plain controller ---

    public function testOffTheListReRendersAndNothingRedirects()
    {
        $this->logInWithPermission('ADMIN');
        $button = new CopyButton();
        $gridField = $this->gridField($button);
        $original = $this->objFromFixture(CopyRecord::class, 'first');
        $before = CopyRecord::get()->column('ID');

        $result = $button->handleAction($gridField, 'copyrecord', ['RecordID' => $original->ID], []);

        $this->newRecord($before);
        # A null result is what makes GridField::gridFieldAlterAction() re-render the field.
        $this->assertNull($result);
        $this->assertNull($this->pushedController->getResponse()->getHeader('Location'), 'no redirect');
    }

    public function testOnRedirectsToTheCopysEditForm()
    {
        $this->logInWithPermission('ADMIN');
        $button = (new CopyButton())->setOpenAfterCopy(true);
        $gridField = $this->gridField($button);
        $original = $this->objFromFixture(CopyRecord::class, 'first');
        $before = CopyRecord::get()->column('ID');

        $result = $button->handleAction($gridField, 'copyrecord', ['RecordID' => $original->ID], []);

        $copy = $this->newRecord($before);
        $this->assertInstanceOf(HTTPResponse::class, $result);
        $this->assertTrue($result->isRedirect(), 'a plain (non-CMS) controller answers with a redirect');
        # The expected URL comes from the framework's own edit button for that row, not from the module.
        $expected = GridFieldEditButton::create()->getUrl($gridField, $copy, 'Actions');
        $this->assertStringEndsWith("/item/{$copy->ID}/edit", $expected);
        $this->assertSame(Director::absoluteURL($expected), $result->getHeader('Location'));
    }

    public function testOnWithoutADetailFormFallsBackToTheListWithoutAnError()
    {
        $this->logInWithPermission('ADMIN');
        $button = (new CopyButton())->setOpenAfterCopy(true);
        $gridField = $this->gridField($button, false);
        $original = $this->objFromFixture(CopyRecord::class, 'first');
        $before = CopyRecord::get()->column('ID');

        $result = $button->handleAction($gridField, 'copyrecord', ['RecordID' => $original->ID], []);

        $this->newRecord($before);
        $this->assertNull($result);
        $this->assertNull($this->pushedController->getResponse()->getHeader('Location'), 'no redirect');
    }

    public function testOnWithTheCopyOutsideTheListFallsBackToTheList()
    {
        # A list the copy does not end up in - as with a many_many list, which duplicate() does not
        # add the copy to. The detail form would 404 on it, so no redirect is made.
        $this->logInWithPermission('ADMIN');
        $button = (new CopyButton())->setOpenAfterCopy(true);
        $original = $this->objFromFixture(CopyRecord::class, 'first');
        $gridField = $this->gridField($button, true, CopyRecord::get()->filter('ID', $original->ID));
        $before = CopyRecord::get()->column('ID');

        $result = $button->handleAction($gridField, 'copyrecord', ['RecordID' => $original->ID], []);

        $this->newRecord($before);
        $this->assertNull($result);
        $this->assertNull($this->pushedController->getResponse()->getHeader('Location'), 'no redirect');
    }

    // --- Through a real ModelAdmin, over HTTP ---

    /**
     * Render the GridField at $pageUrl, submit the copy button in the row of $recordId the way the
     * CMS's GridField JS does (ajax POST to the GridField's URL, X-Pjax: CurrentField), and return
     * the response.
     */
    private function clickCopyInAdmin(string $pageUrl, string $gridFieldUrl, int $recordId): HTTPResponse
    {
        $page = $this->get($pageUrl);
        $this->assertSame(200, $page->getStatusCode(), "$pageUrl renders");

        # The row of the record, then the copy button in it; the button's name carries the StateID
        # under which the GridField stored the action (in the session) while rendering.
        $this->assertSame(
            1,
            preg_match('#<tr[^>]*data-id="' . $recordId . '"[^>]*>(.*?)</tr>#s', $page->getBody(), $row),
            "the row of record $recordId is rendered"
        );
        $this->assertSame(
            1,
            preg_match('#<button[^>]*name="(action_gridFieldAlterAction\?StateID=[^"]+)"[^>]*gridfield-button-copy#s', $row[1], $button),
            'the row has a copy button'
        );

        return $this->post($gridFieldUrl, [$button[1] => '1'], [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Pjax' => 'CurrentField',
        ]);
    }

    public function testOnInAModelAdminAnswersWithTheCopysEditUrlAsXControllerUrl()
    {
        if (!static::adminInstalled()) {
            $this->markTestSkipped('silverstripe/admin is not installed in this host');
        }
        $this->logInWithPermission('ADMIN');
        $this->autoFollowRedirection = false;
        $original = $this->objFromFixture(CopyRecord::class, 'first');
        $before = CopyRecord::get()->column('ID');
        $tab = 'admin/copybutton-test-admin/Unisolutions-Tests-Stub-CopyRecord';
        $gridFieldUrl = "$tab/EditForm/field/Unisolutions-Tests-Stub-CopyRecord";

        $response = $this->clickCopyInAdmin($tab, $gridFieldUrl, $original->ID);

        $copy = $this->newRecord($before);
        $controllerUrl = (string) $response->getHeader('X-ControllerURL');
        $this->assertStringEndsWith("$gridFieldUrl/item/{$copy->ID}/edit", strtok($controllerUrl, '?'));

        # And that URL opens the copy's edit form.
        $form = $this->get($controllerUrl);
        $this->assertSame(200, $form->getStatusCode());
        $this->assertStringContainsString('value="First record"', $form->getBody());
    }

    public function testOnInANestedGridFieldAnswersWithTheCopysNestedEditUrl()
    {
        if (!static::adminInstalled()) {
            $this->markTestSkipped('silverstripe/admin is not installed in this host');
        }
        $this->logInWithPermission('ADMIN');
        $this->autoFollowRedirection = false;
        $parent = $this->objFromFixture(CopyParent::class, 'parent');
        $child = $this->objFromFixture(CopyChild::class, 'child');
        $before = CopyChild::get()->column('ID');
        $parentItem = 'admin/copybutton-test-admin/Unisolutions-Tests-Stub-CopyParent'
            . "/EditForm/field/Unisolutions-Tests-Stub-CopyParent/item/{$parent->ID}";
        $gridFieldUrl = "$parentItem/ItemEditForm/field/Children";

        $response = $this->clickCopyInAdmin("$parentItem/edit", $gridFieldUrl, $child->ID);

        $new = CopyChild::get()->exclude('ID', $before);
        $this->assertCount(1, $new, 'exactly one copy is written');
        $copy = $new->first();
        $this->assertSame((int) $parent->ID, (int) $copy->ParentID, 'the copy stays in the has_many list');
        $controllerUrl = (string) $response->getHeader('X-ControllerURL');
        $this->assertStringEndsWith("$gridFieldUrl/item/{$copy->ID}/edit", strtok($controllerUrl, '?'));

        $form = $this->get($controllerUrl);
        $this->assertSame(200, $form->getStatusCode());
        $this->assertStringContainsString('value="Child record"', $form->getBody());
    }
}
