<?php

namespace Restruct\CbBrowser;

use SilverStripe\ORM\DataObject;

/**
 * BROWSER-TEST FIXTURE ONLY - the record the copy button duplicates, and the base of the other
 * fixture records (one subclass per ModelAdmin tab, so each tab has its own GridField setup).
 *
 * Never loaded by a real install: it lives under tests/browser/, which carries a _manifest_exclude
 * marker, and the browser-test runner copies it into a scratch host's app/ before dev/build.
 * Written to load on both Silverstripe 5 and 6 (no class imports that moved between the two).
 * Deliberately NOT abstract: an abstract DataObject anywhere in a manifest fatals the test-database
 * build (SOP "Never declare an abstract DataObject"), and a copy of this pattern may end up in one.
 *
 * Every dev/build (the runner does one per run) wipes and re-seeds each subclass's rows, so a run
 * starts from the same few records and the copies made by earlier runs are gone.
 *
 * @property string $Title
 */
class CbBRecord extends DataObject
{
    # Short table names throughout: no namespaced defaults, MySQL caps table names at 64 characters.
    private static $table_name = 'CbBRecord';

    private static $singular_name = 'Browser Record';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    # The copy keeps the Title, so sorting on it puts a copy right below its original; ID breaks the
    # tie, so the original stays first.
    private static $default_sort = '"Title" ASC, "ID" ASC';

    private static $summary_fields = [
        'Title' => 'Title',
    ];

    /**
     * The titles each subclass seeds on dev/build. One record per spec, so parallel specs never
     * copy the same row and each can count "its" rows by title.
     */
    protected const SEEDS = [];

    /**
     * Records titled "Locked ..." may not be created, so the copy button must not be offered on
     * their row. canCreate() is asked on the RECORD by CopyButton (getColumnContent/getGroup), while
     * the ModelAdmin's Add button asks the singleton, whose Title is empty, so Add stays available.
     */
    public function canCreate($member = null, $context = [])
    {
        if (strpos((string) $this->Title, 'Locked') === 0) {
            return false;
        }
        return parent::canCreate($member, $context);
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        # requireDefaultRecords() runs once per class in the hierarchy; each class seeds only its own
        # rows, and a class without seeds leaves its table alone (CbBParent seeds its children/tags).
        if (!static::SEEDS) {
            return;
        }
        foreach (static::get()->filter('ClassName', static::class) as $old) {
            $old->delete();
        }
        foreach (static::SEEDS as $title) {
            static::create(['Title' => $title])->write();
        }
    }
}
