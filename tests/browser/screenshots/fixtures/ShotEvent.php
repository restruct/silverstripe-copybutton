<?php

namespace Restruct\CbShots;

use SilverStripe\ORM\DataObject;

/**
 * README-SCREENSHOT FIXTURE ONLY - neutral demo content for the README screenshots, see
 * ../README.md. Never loaded by a real install or by the browser tests: it lives under
 * tests/browser/ (which carries a _manifest_exclude marker), and only the screenshot host gets it
 * copied into its app/src/.
 *
 * Tab "events" of ShotAdmin: menu mode, CopyButton::create().
 * Written to load on both Silverstripe 5 and 6 (no class imports that moved between the two).
 *
 * @property string $Title
 * @property string $StartDate
 * @property string $Venue
 */
class ShotEvent extends DataObject
{
    # Short table names: no namespaced defaults.
    private static $table_name = 'CbShotEvent';

    private static $singular_name = 'Event';

    private static $plural_name = 'Events';

    private static $db = [
        'Title' => 'Varchar(255)',
        'StartDate' => 'Date',
        'Venue' => 'Varchar(255)',
    ];

    # The copy keeps the Title, so sorting on it puts a copy right below its original; ID breaks
    # the tie, so the original stays first.
    private static $default_sort = '"Title" ASC, "ID" ASC';

    private static $summary_fields = [
        'Title' => 'Title',
        'StartDate.Nice' => 'Date',
        'Venue' => 'Venue',
    ];

    /** Seeded on every dev/build: title => [field => value]. */
    protected const SEEDS = [
        'Autumn lecture series' => ['StartDate' => '2026-10-06', 'Venue' => 'Main hall'],
        'Open day' => ['StartDate' => '2026-04-18', 'Venue' => 'Visitor centre'],
        'Summer workshop: watercolour' => ['StartDate' => '2026-07-09', 'Venue' => 'Studio 2'],
        'Winter concert' => ['StartDate' => '2026-12-12', 'Venue' => 'Main hall'],
    ];

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        # Wipe and re-seed each class's own rows, so every run starts from the same list and the
        # copies an earlier run made are gone.
        if (!static::SEEDS) {
            return;
        }
        foreach (static::get()->filter('ClassName', static::class) as $old) {
            $old->delete();
        }
        foreach (static::SEEDS as $title => $fields) {
            static::create(['Title' => $title] + $fields)->write();
        }
    }
}
