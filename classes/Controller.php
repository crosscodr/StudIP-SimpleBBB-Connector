<?php
/**
 * @author  David Siegfried <david.siegfried@uni-vechta.de>
 * @license GPL2 or any later version
 */

namespace Vec\BBB;

use PluginController;
use Request;

class Controller extends PluginController
{
    public function render_template($template_name, $layout = null)
    {
        $layout_file = Request::isXhr()
            ? 'layouts/dialog.php'
            : 'layouts/base.php';

        $layout = $GLOBALS['template_factory']->open($layout_file);

        parent::render_template($template_name, $layout);
    }
}

