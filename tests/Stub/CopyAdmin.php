<?php

namespace Unisolutions\Tests\Stub;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Unisolutions\GridField\CopyButton;

/**
 * A real ModelAdmin for the open-after-copy tests, so the copy request goes through the CMS's own
 * routing, GridField request handling and admin controller redirect() (X-ControllerURL on ajax).
 *
 * TestOnly keeps it out of the CMS menu and the admin routes; OpenAfterCopyTest routes it through
 * SapphireTest::$extra_controllers instead. Only loaded when silverstripe/admin is installed: the
 * tests that use it skip otherwise.
 */
class CopyAdmin extends ModelAdmin implements TestOnly
{
    private static $url_segment = 'copybutton-test-admin';

    private static $managed_models = [
        CopyRecord::class,
        CopyParent::class,
    ];

    /**
     * Column mode, so the rendered copy button carries the action's name for the test to submit.
     * CopyParent's own copy button (for the nested GridField) is added in CopyParent::getCMSFields().
     */
    protected function getGridFieldConfig(): GridFieldConfig
    {
        $config = parent::getGridFieldConfig();
        if ($this->modelClass === CopyRecord::class) {
            $config->addComponent((new CopyButton(true))->setOpenAfterCopy(true), GridFieldEditButton::class);
        }
        return $config;
    }
}
