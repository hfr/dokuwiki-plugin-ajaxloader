<?php

if (strpos($call, 'plugin_') === 0) {
    $name = explode('_', substr($call, 7), 3);

    $fname = DOKU_INC . "lib/plugins/{$name[0]}/ajax" .
             (count($name) === 2 ? "/{$name[1]}" : '') . '.php';

    if (is_file($fname)) {
        require $fname;

        if (isset($AJAX_JSON)) {
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode(
                $AJAX_JSON,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        exit;
    }
}
