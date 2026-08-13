<?php

require_once __DIR__ . '/set_app_timezone.php';

$databaseFile = '../../db/wallos.db';
$db = new SQLite3($databaseFile);
$db->busyTimeout(5000);

if (!$db) {
    die('Connection to the database failed.');
}

require_once 'i18n/languages.php';
require_once 'i18n/getlang.php';
require_once 'i18n/' . $lang . '.php';
require_once 'remember_me.php';

$secondsInMonth = 30 * 24 * 60 * 60;
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $secondsInMonth,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $userId = $_SESSION['userId'];
} else {
    // The PHP session can be garbage-collected (default ~24 min) long before
    // the "remember me" cookie should expire (30 days). Fall back to it here,
    // the same way full page loads do via checksession.php, so AJAX/API
    // endpoints don't silently behave as logged-out after an idle period.
    $restoredUser = restoreSessionFromRememberMeCookie($db);
    $userId = $restoredUser !== false ? $restoredUser['id'] : 0;
}

if (!empty($userId)) {
    require_once __DIR__ . '/appearance.php';
    try {
        $tzStmt = $db->prepare('SELECT timezone FROM settings WHERE user_id = :userId');
        if ($tzStmt) {
            $tzStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $tzResult = $tzStmt->execute();
            $tzRow = $tzResult ? $tzResult->fetchArray(SQLITE3_ASSOC) : false;
            if ($tzRow && !empty($tzRow['timezone'])) {
                wallos_apply_user_timezone($tzRow['timezone']);
            }
        }
    } catch (Throwable $e) {
        // Older databases may not have the timezone column yet.
    }
}

?>