<?php

namespace Unisolutions\Tests\Stub;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;

/**
 * Gives the GridField's Form a controller, so GridField_FormAction can build its Link() when a
 * button or menu item is rendered.
 */
class CopyController extends Controller implements TestOnly
{
    private static $url_segment = 'copybutton-test';
}
