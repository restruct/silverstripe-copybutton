<?php

namespace Unisolutions\Tests\Stub;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Child record in CopyParent's nested has_many GridField. canCreate() is DataObject's default
 * (ADMIN only), like CopyRecord.
 */
class CopyChild extends DataObject implements TestOnly
{
    private static $table_name = 'CopyButtonTest_Child';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Parent' => CopyParent::class,
    ];
}
