<?php

namespace Restruct\CbShots;

use SilverStripe\ORM\DataObject;

/**
 * README-SCREENSHOT FIXTURE ONLY (see ShotEvent). Tab "newsletters" of ShotAdmin: menu mode with
 * setOpenAfterCopy(true), so a copy opens the copy's edit form.
 * Its own DataObject, not a ShotEvent subclass: the Events tab would list it otherwise.
 *
 * @property string $Title
 * @property string $SendDate
 */
class ShotNewsletter extends DataObject
{
    private static $table_name = 'CbShotNewsletter';

    private static $singular_name = 'Newsletter';

    private static $plural_name = 'Newsletters';

    private static $db = [
        'Title' => 'Varchar(255)',
        'SendDate' => 'Date',
        'Intro' => 'Text',
    ];

    # See ShotEvent: a copy lands right below its original.
    private static $default_sort = '"Title" ASC, "ID" ASC';

    private static $summary_fields = [
        'Title' => 'Title',
        'SendDate.Nice' => 'Send date',
    ];

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        foreach (static::get() as $old) {
            $old->delete();
        }
        $seeds = [
            'April newsletter' => '2026-04-01',
            'March newsletter' => '2026-03-02',
            'May newsletter' => '2026-05-04',
        ];
        foreach ($seeds as $title => $date) {
            static::create([
                'Title' => $title,
                'SendDate' => $date,
                'Intro' => 'News from the visitor centre, the programme for the coming weeks and how to book.',
            ])->write();
        }
    }
}
