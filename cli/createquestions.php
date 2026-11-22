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
 * CLI utility to test create questions.
 *
 * @package     local_aiquestions_m2
 * @category    admin
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com> , Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$gift = "Mi Magyarország fővárosa, amely a Duna partján fekszik? {\n=Budapest\n~Debrecen\n~Szeged\n~Pécs\n}";

$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;
var_dump('CATEGORY:', local_aiquestions_m2_get_default_category(2));
var_dump('BLOCKS:', local_aiquestions_m2_get_valid_gift_blocks($gift));

$question = local_aiquestions_m2_create_questions(2, null, $gift, 1, 3, 0);
var_dump($question);
