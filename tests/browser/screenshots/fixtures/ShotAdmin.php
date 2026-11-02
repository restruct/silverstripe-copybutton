<?php

namespace Restruct\CbShots;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Unisolutions\GridField\CopyButton;

/**
 * README-SCREENSHOT FIXTURE ONLY (see ShotEvent) - the CMS screen the README screenshots show:
 * /admin/events/<tab>, one tab per copy-button setup, configured the way the README tells a
 * project to.
 */
class ShotAdmin extends ModelAdmin
{
    private static $url_segment = 'events';

    private static $menu_title = 'Events';

    private static $menu_icon_class = 'font-icon-calendar';

    # Keyed managed_models: the key becomes the URL segment and the GridField's name.
    private static $managed_models = [
        'events' => ['dataClass' => ShotEvent::class, 'title' => 'Events'],
        'tickets' => ['dataClass' => ShotTicketType::class, 'title' => 'Ticket types'],
        'newsletters' => ['dataClass' => ShotNewsletter::class, 'title' => 'Newsletters'],
    ];

    protected function getGridFieldConfig(): GridFieldConfig
    {
        $config = parent::getGridFieldConfig();

        switch ($this->modelClass) {
            case ShotEvent::class:
                $config->addComponent(CopyButton::create(), GridFieldEditButton::class);
                break;
            case ShotTicketType::class:
                $config->addComponent(CopyButton::create(true), GridFieldEditButton::class);
                break;
            case ShotNewsletter::class:
                $config->addComponent(CopyButton::create()->setOpenAfterCopy(true), GridFieldEditButton::class);
                break;
        }

        return $config;
    }
}
