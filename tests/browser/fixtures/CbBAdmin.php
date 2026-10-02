<?php

namespace Restruct\CbBrowser;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use Unisolutions\GridField\CopyButton;

/**
 * BROWSER-TEST FIXTURE ONLY - the CMS screen the specs open: /admin/cb-browser/<tab>
 * (see CbBRecord for why this never loads in a real install). One tab per copy-button setup.
 */
class CbBAdmin extends ModelAdmin
{
    private static $url_segment = 'cb-browser';

    private static $menu_title = 'CopyButton browser test';

    # Keyed managed_models (SS5 and SS6): the key becomes the URL segment, so specs need not spell
    # out the sanitised namespaced class name.
    private static $managed_models = [
        'menu' => ['dataClass' => CbBMenuRecord::class, 'title' => 'Menu mode'],
        'column' => ['dataClass' => CbBColumnRecord::class, 'title' => 'Column mode'],
        'open' => ['dataClass' => CbBOpenRecord::class, 'title' => 'Open after copy'],
        'nodetail' => ['dataClass' => CbBNoDetailRecord::class, 'title' => 'No detail form'],
        'parents' => ['dataClass' => CbBParent::class, 'title' => 'Nested'],
    ];

    # The README's recommended place: getGridFieldConfig(), with the class name (not a short string)
    # as the second addComponent() argument so the button lands before the edit button.
    protected function getGridFieldConfig(): \SilverStripe\Forms\GridField\GridFieldConfig
    {
        $config = parent::getGridFieldConfig();

        switch ($this->modelClass) {
            case CbBMenuRecord::class:
                $config->addComponent(CopyButton::create(), GridFieldEditButton::class);
                break;
            case CbBColumnRecord::class:
                $config->addComponent(CopyButton::create(true), GridFieldEditButton::class);
                break;
            case CbBOpenRecord::class:
                $config->addComponent(CopyButton::create()->setOpenAfterCopy(true), GridFieldEditButton::class);
                break;
            case CbBNoDetailRecord::class:
                # No detail form: nothing to open (and no edit/add button, which would need one).
                $config->removeComponentsByType([
                    GridFieldDetailForm::class,
                    GridFieldEditButton::class,
                    GridFieldAddNewButton::class,
                ]);
                $config->addComponent(CopyButton::create(true)->setOpenAfterCopy(true));
                break;
        }

        return $config;
    }
}
