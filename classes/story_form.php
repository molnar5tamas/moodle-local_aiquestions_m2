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
 * Story Form Class is defined here.
 *
 * @package     local_aiquestions_m2
 * @category    admin
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com> , Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Form to get the story from the user.
 *
 * @package     local_aiquestions_m2
 * @category    admin
 */
class local_aiquestions_m2_story_form extends moodleform {
    /**
     * Defines forms elements
     */
    public function definition() {
        global $courseid;
        $mform = $this->_form;

        require_once(__DIR__ . '/../locallib.php');

        // Question category: the course context on Moodle 4.5, the course question banks on Moodle 5.x.
        $contexts = local_aiquestions_m2_get_target_contexts($courseid);
        if (!empty($contexts)) {
            $mform->addElement('questioncategory', 'category', get_string('category', 'question'), ['contexts' => $contexts]);
            $mform->addHelpButton('category', 'category', 'local_aiquestions_m2');
        } else {
            $mform->addElement(
                'static',
                'categorynone',
                get_string('category', 'question'),
                get_string('noqbankavailable', 'local_aiquestions_m2')
            );
        }

        // Number of questions.
        $defaultnumofquestions = 4;
        $select = $mform->addElement(
            'select',
            'numofquestions',
            get_string('numofquestions', 'local_aiquestions_m2'),
            [
                '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6,
                '7' => 7, '8' => 8, '9' => 9, '10' => 10, '15' => 15,
            ]
        );

        $select->setSelected($defaultnumofquestions);
        $mform->setType('numofquestions', PARAM_INT);

        // Story.
        $mform->addElement(
            'textarea',
            'story',
            get_string('story', 'local_aiquestions_m2'),
            'wrap="virtual" rows="10" cols="50"'
        );
        // The maximum context length is i tokens. We limit the story to j tokens.

        $mform->setType('story', PARAM_RAW);
        $mform->addHelpButton('story', 'story', 'local_aiquestions_m2');

        // File upload (optional alternative/supplement to the story textarea).
        $mform->addElement(
            'filepicker',
            'storyfile',
            get_string('storyfile', 'local_aiquestions_m2'),
            null,
            [
                'maxbytes'       => 52428800, // 50 MB limit.
                'accepted_types' => ['.txt', '.pdf', '.docx', '.pptx'],
            ]
        );

        // MAXBYTES 'maxbytes'  => 1048576, // 1 MB limit.

        $mform->addHelpButton('storyfile', 'storyfile', 'local_aiquestions_m2');

        // File (pdf) already existing in the course (optional alternative/supplement).
        $pdfoptions = $this->get_course_pdfs($courseid);
        $mform->addElement('select', 'pdf', get_string('pdf', 'local_aiquestions_m2'), $pdfoptions);
        $mform->setType('pdf', PARAM_INT);
        $mform->addHelpButton('pdf', 'pdf', 'local_aiquestions_m2');

        // Add "AI_ " to question name.
        // AI_  identifier: always required; hidden from instructors.
        $mform->addElement('hidden', 'addidentifier', 1);
        $mform->setType('addidentifier', PARAM_INT);
        $mform->setConstant('addidentifier', 1);

        // Add custom prefix.
        $mform->addElement('text', 'customprefix', get_string('customprefix', 'local_aiquestions_m2'), ['size' => '30']);
        $mform->setType('customprefix', PARAM_TEXT);
        $mform->setDefault('customprefix', '');
        $mform->addHelpButton('customprefix', 'customprefix', 'local_aiquestions_m2');

        // Courseid.
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $buttonarray = [];
        $buttonarray[] =& $mform->createElement('submit', 'submitbutton', get_string('generate', 'local_aiquestions_m2'));
        $buttonarray[] =& $mform->createElement('cancel', 'cancel', get_string('backtocourse', 'local_aiquestions_m2'));
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

    /**
     * Get PDF resources from the course.
     *
     * @param int $courseid Course ID
     * @return array Array of PDF options for dropdown (file id => label)
     */
    private function get_course_pdfs($courseid) {
        global $DB;

        $pdfoptions = [];

        // Get all resource modules in the course that have a PDF file.
        $sql = "SELECT f.id AS fileid, r.name, f.filename
                FROM {resource} r
                JOIN {course_modules} cm ON cm.instance = r.id
                JOIN {modules} m ON m.id = cm.module
                JOIN {context} ctx ON ctx.instanceid = cm.id
                JOIN {files} f ON f.contextid = ctx.id
                WHERE cm.course = :courseid
                AND m.name = 'resource'
                AND f.component = 'mod_resource'
                AND f.filearea = 'content'
                AND f.filename != '.'
                AND LOWER(f.filename) LIKE '%.pdf'
                AND ctx.contextlevel = 70
                ORDER BY r.name, f.filename";

        $resources = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        // Check if mod_securepdf is installed and enabled.
        $pluginmanager = \core_plugin_manager::instance();
        $securepdfinfo = $pluginmanager->get_plugin_info('mod_securepdf');
        if ($securepdfinfo && ($securepdfinfo->is_enabled())) {
            // Get all securepdf modules in the course that have a PDF file.
            $sql = "SELECT f.id AS fileid, r.name, f.filename
                FROM {securepdf} r
                JOIN {course_modules} cm ON cm.instance = r.id
                JOIN {modules} m ON m.id = cm.module
                JOIN {context} ctx ON ctx.instanceid = cm.id
                JOIN {files} f ON f.contextid = ctx.id
                WHERE cm.course = :courseid
                AND m.name = 'securepdf'
                AND f.component = 'mod_securepdf'
                AND f.filearea = 'content'
                AND f.filename != '.'
                AND LOWER(f.filename) LIKE '%.pdf'
                AND ctx.contextlevel = 70
                ORDER BY r.name, f.filename";

            // Keyed by file id, so a plain "+" merge is safe (array_merge would renumber integer keys).
            $resources = $resources + $DB->get_records_sql($sql, ['courseid' => $courseid]);
        }

        foreach ($resources as $resource) {
            $pdfoptions[$resource->fileid] = $resource->name . ' (' . $resource->filename . ')';
        }

        // Add a default option for no PDF selected before all other options.
        if (empty($pdfoptions)) {
            $pdfoptions[0] = get_string('nopdfselected', 'local_aiquestions_m2');
        } else {
            asort($pdfoptions);
            $pdfoptions = [0 => get_string('nopdfselected', 'local_aiquestions_m2')] + $pdfoptions;
        }

        return $pdfoptions;
    }

    /**
     * Form validation — ensures the user provided either a story text or an uploaded file,
     * and checks if the character length exceeds the allowed context limit.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER, $CFG, $courseid;
        $errors = parent::validation($data, $files);

        $storytext = trim($data['story'] ?? '');
        $hasstory  = !empty($storytext);
        $hasfile   = !empty($data['storyfile']);
        $pdfid     = (int)($data['pdf'] ?? 0);
        $haspdf    = ($pdfid > 0);

        // 1. Check: Are all inputs empty?
        if (!$hasstory && !$hasfile && !$haspdf) {
            $errors['story']     = get_string('errornostory', 'local_aiquestions_m2');
            $errors['storyfile'] = get_string('errornostory', 'local_aiquestions_m2');
            $errors['pdf']       = get_string('errornostory', 'local_aiquestions_m2');
            return $errors;
        }

        // The selected PDF must be one of the PDFs of this course.
        $pdftext = '';
        if ($haspdf) {
            if (!array_key_exists($pdfid, $this->get_course_pdfs($courseid))) {
                $errors['pdf'] = get_string('nopdfselected', 'local_aiquestions_m2');
                return $errors;
            }
            require_once($CFG->dirroot . '/local/aiquestions_m2/locallib.php');
            $pdftext = local_aiquestions_m2_extract_pdf_text($pdfid);
        }

        // Maximum allowed number of characters (adjusted to the LiteLLM limit, minus the length of the prompt).
        // --context-length 65536  -> 160.000 - 200.000 characters.

        $maxchars = 160000;

        // 2. Check: If you've typed too much into the text area.
        $currentlength = mb_strlen($storytext, 'UTF-8');
        if ($currentlength > $maxchars) {
            $errors['story'] = "The submitted text is too long (" . number_format($currentlength, 0, ',', ' ') . " characters), "
                . "that exceeds the permitted limit " . number_format($maxchars, 0, ',', ' ');
        }

        // 2b. Check: text + selected course PDF.
        if ($haspdf) {
            $pdfchars = mb_strlen($pdftext, 'UTF-8');
            if (($currentlength + $pdfchars) > $maxchars) {
                $errors['pdf'] = "The submitted text is too long ("
                    . number_format($currentlength + $pdfchars, 0, ',', ' ')
                    . " characters), that exceeds the permitted limit "
                    . number_format($maxchars, 0, ',', ' ');
            }
        }

        // 3. Verification: If you've uploaded a file (PDF / TXT / DOCX / PPTX), we'll also check the length of the extracted text.
        if ($hasfile) {
            $fs = get_file_storage();
            $usercontext = \context_user::instance($USER->id);
            $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['storyfile'], 'id', false);

            foreach ($draftfiles as $file) {
                $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                $extracted = '';

                if ($ext === 'txt') {
                    $extracted = $file->get_content();
                } else if ($ext === 'pdf') {
                    require_once($CFG->dirroot . '/local/aiquestions_m2/locallib.php');
                    if (function_exists('local_aiquestions_m2_extract_pdf_text')) {
                        $extracted = local_aiquestions_m2_extract_pdf_text($file->get_id());
                    }
                } else if ($ext === 'docx' || $ext === 'pptx') {
                    $tempfile = tempnam($CFG->dataroot . '/temp/', 'moodle_office_');
                    $file->copy_content_to($tempfile);
                    $zip = new \ZipArchive();
                    if ($zip->open($tempfile) === true) {
                        if ($ext === 'docx') {
                            $xml = $zip->getFromName('word/document.xml');
                            if ($xml !== false) {
                                $extracted = strip_tags(str_replace('</w:p>', ' ', $xml));
                            }
                        } else if ($ext === 'pptx') {
                            for ($i = 1; $i <= 500; $i++) {
                                $xml = $zip->getFromName('ppt/slides/slide' . $i . '.xml');
                                if ($xml === false) {
                                    break;
                                }
                                $extracted .= ' ' . strip_tags(str_replace('</a:p>', ' ', $xml));
                            }
                        }
                        $zip->close();
                    }
                    @unlink($tempfile);
                }

                $filechars = mb_strlen($extracted, 'UTF-8');
                if (($currentlength + mb_strlen($pdftext, 'UTF-8') + $filechars) > $maxchars) {
                    $errors['storyfile'] = "The submitted text is too long ("
                        . number_format($currentlength + mb_strlen($pdftext, 'UTF-8') + $filechars, 0, ',', ' ')
                        . " characters), that exceeds the permitted limit "
                        . number_format($maxchars, 0, ',', ' ');
                }
            }
        }

        return $errors;
    }
}
