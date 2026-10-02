<?php

namespace Restruct\CbBrowser;

/**
 * BROWSER-TEST FIXTURE ONLY - a row of CbBParent's nested has_many GridField. duplicate() keeps the
 * copy's ParentID, so the copy is in that list and open-after-copy can open it (see CbBRecord).
 * Seeded by CbBParent, not by itself.
 *
 * @method CbBParent Parent()
 */
class CbBChild extends CbBRecord
{
    private static $table_name = 'CbBChild';

    private static $singular_name = 'Browser Child';

    private static $has_one = [
        'Parent' => CbBParent::class,
    ];
}
