<?php
/**
 * Internal AJAX functions of the ajaxloader plugin.
 *
 * Internal calls have priority over all other AJAX handlers.
 *
 * @license GPL 2
 */

global $USERINFO, $conf;

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------

/**
 * Send a JSON response and terminate processing.
 */
$respond = static function ($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
};

/**
 * Check whether the current user is logged in.
 */
$isLoggedIn = static function () use (&$USERINFO) {
    return !empty($_SERVER['REMOTE_USER'])
        && is_array($USERINFO);
};

$isApiAdmin = static function () use ($isLoggedIn, &$conf, &$USERINFO) {

        if (!$isLoggedIn()) {
            return [false, 'authentication_required', 401];
        }

        // Read configured users and groups
        $allowedAdmins = $conf['plugin']['ajaxloader']['api_admins']
            ?? [];

        $username = $_SERVER['REMOTE_USER'];
        $groups = $USERINFO['grps'] ?? [];

        // Check user and group permissions
        $allowed = false;

        foreach ($allowedAdmins as $entry) {

            if ($entry === $username) {
                $allowed = true;
                break;
            }

            if (str_starts_with($entry, '@')) {
                $group = substr($entry, 1);

                if (in_array($group, $groups, true)) {
                    $allowed = true;
                    break;
                }
            }
        }
        if (!$allowed) {
            return [false, 'permission_denied', 403];
        } else {
            return [true, '', 200];    
        }
};


// ------------------------------------------------------------
// Internal call dispatch
// ------------------------------------------------------------

switch ($call) {

    // ========================================================
    // check_user_acl(page_id)
    // ========================================================

    case 'check_user_acl':

    $loggedIn = (
        !empty($_SERVER['REMOTE_USER']) &&
        !empty($USERINFO['name'])
    );

    $acl = 0;
    $e = '';
    $pageId = '';
    try {
        $pageId = cleanID($_REQUEST['id'] ?? '');
        if ($pageId !== '') {
            $acl = (int) auth_quickaclcheck($pageId);
        }
    } catch (Throwable $e) {
        $acl = 0;
    }

    $respond([
        'id' => $pageId,
        'logged_in' => $loggedIn,
        'acl' => $acl,
    ]);

    break;


    case 'check_token_acl':

        [$allowed, $error, $status] = $isApiAdmin();
        if (!$allowed) {
            $respond(['error' => $error], $status);
        }

        $acl = 0;
        $e = '';
        $pageId = '';
        $loggedIn=false;
        try {
            session_write_close();
            $_SESSION = [];
            $token = $_REQUEST['token'] ?? [];
            $session_tok = $token['session'] ?? '';
            if (!empty($session_tok)) {
                $_COOKIE[DOKU_SESSION_NAME] = $session_tok;
                $auth_tok = $token['auth'] ?? '';
                if (!empty($auth_tok)) {
                    $_COOKIE[DOKU_COOKIE] = $auth_tok;
                } else {
                    unset($_COOKIE[DOKU_COOKIE]);
                }
                session_id($session_tok);
                init_session();
                if (isset($_SESSION[DOKU_COOKIE])) {
                    $authData = $_SESSION[DOKU_COOKIE]['auth'] ?? [];
                    $authInfo = $authData['info'] ?? [];
                    $user = $authData['user'] ?? '';
                    $grps = $authInfo['grps'] ?? [];
                    if (!empty($authData) && isset($authData['time'])) {
                        $loggedIn=auth_login('', '');
                    } elseif (isset($authData['token'])) {
                        $user = '';
                        $grps = [];
                        $session_tok = '';
                    }
                    $pageId = cleanID($_REQUEST['id'] ?? '');
                    if ($pageId !== '') {
                        $acl = (int) auth_aclcheck($pageId, $user, $grps);
                    }
                } else {
                    $session_tok = '';
                }
            }
        } catch (Throwable $e) {
            $acl = 0;
        }

    session_write_close();
    
    $respond([
        'session' => $session_tok,
        'id' => $pageId,
        'logged_in' => $loggedIn,
        'acl' => $acl,
    ]);

    break;

 
    // ========================================================
    // check_user_tokens(tokens)
    // ========================================================

    case 'check_user_tokens':

        [$allowed, $error, $status] = $isApiAdmin();
        if (!$allowed) {
            $respond(['error' => $error], $status);
        }
        $tokens = $_REQUEST['tokens'] ?? [];
        $validated = [];
        foreach ($tokens as $token) {

            session_write_close();
            $_SESSION = [];

            $session_tok = $token['session'] ?? '';
            $session_data = [];
            if (!empty($session_tok)) {
                $_COOKIE[DOKU_SESSION_NAME] = $session_tok;
                $auth_tok = $token['auth'] ?? '';
                if (!empty($auth_tok)) {
                    $_COOKIE[DOKU_COOKIE] = $auth_tok;
                } else {
                    unset($_COOKIE[DOKU_COOKIE]);
                }
                session_id($session_tok);
                init_session();
                if (isset($_SESSION[DOKU_COOKIE])) {
                    $session_data[]='valid';
                }
                $authData = $_SESSION[DOKU_COOKIE]['auth'] ?? [];
                if (!empty($authData) && isset($authData['time'])) {
                    $loggedin=auth_login('', '');
                    if ($loggedin) {
                        $session_data[]='authentic';
                    }
                }
                if (!empty($session_data)) {
                    $validated[$session_tok]=$session_data;
                }
            }    
        }
        session_write_close();

        $respond($validated, 200);

        break;

    case 'get_doku_cookie':

        [$allowed, $error, $status] = $isApiAdmin();
        if (!$allowed) {
            $respond(['error' => $error], $status);
        }
        $respond([
            'doku_cookie' => DOKU_COOKIE
        ]);
 
        break;
}

