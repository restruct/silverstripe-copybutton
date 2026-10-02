<?php

namespace Restruct\CbBrowser;

/**
 * BROWSER-TEST FIXTURE ONLY - tab "open": menu mode with setOpenAfterCopy(true), so a copy opens the
 * copy's edit form in the CMS panel (see CbBRecord).
 */
class CbBOpenRecord extends CbBRecord
{
    private static $table_name = 'CbBOpenRecord';

    private static $singular_name = 'Open Record';

    protected const SEEDS = ['Open copy'];
}
