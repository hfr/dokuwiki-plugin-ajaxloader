<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Adrian Lang <dokuwiki@cosmocode.de>
 */
use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

if (!defined('DOKU_INC')) {
    exit;
}

class action_plugin_ajaxloader extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook(
            'AJAX_CALL_UNKNOWN',
            'BEFORE',
            $this,
            'ajax'
        );
    }

    public function ajax(Event $event, $param)
    {
        $call = $event->data;

        require __DIR__ . '/common.php';
    }
}
