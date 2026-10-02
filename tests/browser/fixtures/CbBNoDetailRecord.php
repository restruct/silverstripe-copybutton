<?php

namespace Restruct\CbBrowser;

/**
 * BROWSER-TEST FIXTURE ONLY - tab "nodetail": column mode with setOpenAfterCopy(true) on a GridField
 * WITHOUT a GridFieldDetailForm, where the copy has no edit form to open and the button must fall
 * back to re-rendering the list (see CbBRecord).
 */
class CbBNoDetailRecord extends CbBRecord
{
    private static $table_name = 'CbBNoDetailRecord';

    private static $singular_name = 'No-detail Record';

    protected const SEEDS = ['No detail copy'];
}
