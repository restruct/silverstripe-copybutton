<?php

namespace Unisolutions\Tests\Stub;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Test-only record the CopyButton is exercised against, so the suite does not depend on how a
 * host project configures its own models.
 *
 * Deliberately NOT abstract, and no abstract base class either: TableBuilder instantiates every
 * DataObject in the manifest before it checks for TestOnly, and in test mode an installed module's
 * tests/ ARE in the manifest - an abstract fixture here would break every consuming project's
 * database tests (SOP Phase 4, quickaddnew 2.1.0).
 *
 * canCreate() is not overridden: DataObject's default (ADMIN only) is what the tests switch with
 * logInWithPermission('ADMIN') / logOut(), so no mutable static is needed.
 */
class CopyRecord extends DataObject implements TestOnly
{
    # Short table name; the FQCN-derived default would also fit, but is not relied upon.
    private static $table_name = 'CopyButtonTest_Record';

    private static $db = [
        'Title' => 'Varchar',
        # When true, onAfterDuplicate() un-sets the copy's ID, simulating a duplicate() that did not
        # produce a written record - the only way to reach CopyButton's "Error duplicating" branch,
        # since duplicate() itself throws on a failed write.
        'SimulateFailedCopy' => 'Boolean',
    ];

    /**
     * Invoked on the COPY by DataObject::duplicate() (via invokeWithExtensions), after it was written.
     */
    public function onAfterDuplicate($original = null, $doWrite = true, $relations = null)
    {
        if ($this->SimulateFailedCopy) {
            $this->ID = 0;
        }
    }
}
