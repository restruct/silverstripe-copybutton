<?php

namespace Restruct\CbBrowser;

/**
 * BROWSER-TEST FIXTURE ONLY - a row of CbBParent's nested many_many GridField. duplicate() does NOT
 * add the copy to that list, so open-after-copy must fall back to re-rendering it (see CbBRecord).
 * Seeded by CbBParent, not by itself.
 */
class CbBTag extends CbBRecord
{
    private static $table_name = 'CbBTag';

    private static $singular_name = 'Browser Tag';

    private static $belongs_many_many = [
        'Parents' => CbBParent::class,
    ];
}
