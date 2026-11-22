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

namespace local_aiquestions_m2;

/**
 * The createquestions test class.
 *
 * @package     local_aiquestions_m2
 * @category    test
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com> , Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class questions_test extends \advanced_testcase {
    // Write the tests here as public functions.
    // Please refer to {@link https://docs.moodle.org/dev/PHPUnit} for more details on PHPUnit tests in Moodle.

    /**
     * Dummy test.
     *
     * This is to be replaced by some actually useful test.
     *
     * @coversNothing
     */
    public function test_dummy(): void {
        $this->assertTrue(true);
    }

    /**
     * Test local_aiquestions_m2_create_questions.
     * @covers \local_aiquestions_m2_create_questions
     */
    public function test_create_questions(): void {
        require_once(__DIR__ . '/../locallib.php');
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $gift = "Which planet is the largest in our solar system? {
                = Jupiter
                ~ Mars
                ~ Venus
                ~ Earth
            }";
        // Create user.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        // Params are : courseid, category (null = course default), gift, numofquestions, userid, addidentifier.
        $questions = \local_aiquestions_m2_create_questions($course->id, null, $gift, 1, $user->id, 1);
        $this->assertNotEmpty($questions);
        $this->assertEquals('multichoice', $questions[0]->qtype);
        $this->assertStringStartsWith('AI_: ', $questions[0]->name);
    }

    /**
     * Test local_aiquestions_m2_escape_json.
     * @covers \local_aiquestions_m2_escape_json
     */
    public function test_escape_json(): void {
        require_once(__DIR__ . '/../locallib.php');
        $myjson = '{"name":"My long
            text with new line"}';
        $escapedjson = \local_aiquestions_m2_escape_json($myjson);
        $this->assertEquals('{\"name\":\"My long\n            text with new line\"}', $escapedjson);
    }

    /**
     * Test local_aiquestions_m2_check_gift.
     * @covers \local_aiquestions_m2_check_gift
     */
    public function test_check_gift(): void {
        require_once(__DIR__ . '/../locallib.php');
        $gift = "::My interesting questionText
            {
                = right answer
                ~ wrong1
                ~ wrong2
                ~ wrong3
            }

            ::My interesting second questionText
            {
                = second right answer
                ~ s wrong1
                ~ s wrong2
                ~ s wrong3
            }";
        $brokengift = "::My interesting questionText
            {
                right answer
                ~ wrong1
                ~ wrong2
                ~ wrong3
            }

            ::My interesting second questionText
            {
                = second right answer
                s wrong1
                ~ s wrong2
                ~ s wrong3
            }";

        $this->assertTrue(\local_aiquestions_m2_check_gift($gift));
        $this->assertFalse(\local_aiquestions_m2_check_gift($brokengift));
    }
}
