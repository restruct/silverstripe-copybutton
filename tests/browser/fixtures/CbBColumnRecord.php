<?php

namespace Restruct\CbBrowser;

/**
 * BROWSER-TEST FIXTURE ONLY - tab "column": CopyButton::create(true), an icon button in the Actions
 * column placed before the edit button, open-after-copy off (see CbBRecord).
 */
class CbBColumnRecord extends CbBRecord
{
    private static $table_name = 'CbBColumnRecord';

    private static $singular_name = 'Column Record';

    protected const SEEDS = ['Column copy', 'Locked column'];
}
