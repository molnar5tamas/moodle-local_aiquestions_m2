<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     local_aiquestions_m2
 * @category    admin
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com> , Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Downloads the generated GIFT text of a finished generation request.
 *
 * @package     local_aiquestions_m2
 */

// A PHP notice/deprecation do not wrong the JSON.
define('NO_DEBUG_DISPLAY', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_login();

$uniqid = required_param('uniqid', PARAM_RAW_TRIMMED);
$check  = optional_param('check', 0, PARAM_BOOL);

$record = $DB->get_record('local_aiquestions_m2', ['uniqid' => $uniqid]);

// Only user's query (or admin).
if ($record && (int) $record->userid !== (int) $USER->id && !is_siteadmin()) {
    $record = false;
}

// Status: pending / ready / failed.
$status = 'failed';
$gift = '';
$reason = 'There is no query (uniqid=' . s($uniqid) . ').';
if ($record) {
    $success = json_decode((string) $record->success, true);
    if (is_array($success) && !empty($success) && trim((string) $record->gift) !== '') {
        $status = 'ready';
        $gift = (string) $record->gift;
    } else if (
        (int) $record->tries >= (int) $record->numoftries
        || ((string) $record->gift !== '' && empty($success))
    ) {
        $status = 'failed';
        $reason = 'Generation was unsuccessful.';
        if (is_siteadmin()) {
            $reason .= ' ' . (string) $record->gift;
        }
    } else {
        $status = 'pending';
        $reason = 'The generation is still running; please try again in a few seconds.';
    }
}

if ($check) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'status' => $status,
        'reason' => $status === 'failed' ? $reason : '',
    ]);
    die();
}

if ($status !== 'ready') {
    throw new moodle_exception('generalexceptionmessage', 'error', '', $reason);
}

// Windows line breaks to make it look good in Notepad, too.
$gift = str_replace(["\r\n", "\r"], "\n", $gift);
$gift = str_replace("\n", "\r\n", $gift);

while (ob_get_level()) {
    ob_end_clean();
}
$filename = 'aiquestions_' . date('Ymd_Hi') . '.txt';
send_file($gift, $filename, 0, 0, true, true, 'text/plain');
