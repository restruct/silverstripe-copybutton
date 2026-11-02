<?php

namespace Restruct\CbShots;

use SilverStripe\ORM\DataObject;

/**
 * README-SCREENSHOT FIXTURE ONLY (see ShotEvent). Tab "tickets" of ShotAdmin: column mode,
 * CopyButton::create(true).
 *
 * @property string $Title
 * @property float $Price
 */
class ShotTicketType extends DataObject
{
    private static $table_name = 'CbShotTicketType';

    private static $singular_name = 'Ticket type';

    private static $plural_name = 'Ticket types';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Price' => 'Currency',
    ];

    # See ShotEvent: a copy lands right below its original.
    private static $default_sort = '"Title" ASC, "ID" ASC';

    private static $summary_fields = [
        'Title' => 'Title',
        'Price.Nice' => 'Price',
    ];

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        foreach (static::get() as $old) {
            $old->delete();
        }
        $seeds = ['Adult' => 12.5, 'Child (under 12)' => 6, 'Family (2 adults, 2 children)' => 32, 'Student' => 8];
        foreach ($seeds as $title => $price) {
            static::create(['Title' => $title, 'Price' => $price])->write();
        }
    }
}
