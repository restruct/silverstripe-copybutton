<?php

namespace Restruct\CbBrowser;

/**
 * BROWSER-TEST FIXTURE ONLY - tab "menu": CopyButton::create(), the default menu mode ("Copy" in the
 * row's action menu), open-after-copy off (see CbBRecord for why this never loads in a real install).
 */
class CbBMenuRecord extends CbBRecord
{
    private static $table_name = 'CbBMenuRecord';

    private static $singular_name = 'Menu Record';

    protected const SEEDS = ['Menu copy', 'Menu twice', 'Locked menu'];
}
