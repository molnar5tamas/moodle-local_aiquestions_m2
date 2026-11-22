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
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com>, Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

// Get course id for creating the questions in it's bank.
$courseid = optional_param('courseid', 0, PARAM_INT);
if ($courseid == 0) {
    redirect(new moodle_url('/local/aiquestions_m2/index.php'));
}
require_login($courseid);
// Check if the user has the capability to create questions.
$context = context_course::instance($courseid);
require_capability('moodle/question:add', $context);
require_once("$CFG->libdir/formslib.php");
require_once(__DIR__ . '/locallib.php');

if (!function_exists('local_aiquestions_m2_pdf_to_text')) {
    /**
     * Extract the text of a PDF stored_file.
     *
     * Tries the pdftotext binary first (keeps accented characters, e.g. Hungarian),
     * and falls back to the bundled smalot parser (local_aiquestions_m2_extract_pdf_text) when pdftotext is not available.
     *
     * @param stored_file $file PDF file (course resource or user draft)
     * @return string Extracted text (empty string on failure)
     */
    function local_aiquestions_m2_pdf_to_text(stored_file $file): string {
        $text = '';

        // 1. pdftotext, if the server has it and shell_exec is not disabled.
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        if (function_exists('shell_exec') && !in_array('shell_exec', $disabled)) {
            $tmppath = $file->copy_content_to_temp();
            if ($tmppath) {
                $out = shell_exec('pdftotext -enc UTF-8 ' . escapeshellarg($tmppath) . ' - 2>/dev/null');
                @unlink($tmppath);
                if (is_string($out) && trim($out) !== '') {
                    $text = $out;
                }
            }
        }

        // 2. Fallback: smalot PDF parser (note: local_aiquestions_m2_extract_pdf_text drops non-ASCII characters).
        if (trim($text) === '' && function_exists('local_aiquestions_m2_extract_pdf_text')) {
            $text = local_aiquestions_m2_extract_pdf_text($file->get_id());
        }

        if (trim($text) === '') {
            debugging('PDF content could not be extracted: ' . $file->get_filename(), DEBUG_DEVELOPER);
        }
        return (string)$text;
    }
}
$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('pluginname', 'local_aiquestions_m2'));
$PAGE->set_title(get_string('pluginname', 'local_aiquestions_m2'));
$PAGE->set_url('/local/aiquestions_m2/story.php?courseid=' . $courseid);
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add(get_string('pluginname', 'local_aiquestions_m2'), new moodle_url('/local/aiquestions_m2/'));
$PAGE->navbar->add(
    get_string(
        'story',
        'local_aiquestions_m2'
    ),
    new moodle_url('/local/aiquestions_m2/story.php?courseid=' . $courseid)
);
$PAGE->requires->js_call_amd('local_aiquestions_m2/state');
echo $OUTPUT->header();
$mform = new local_aiquestions_m2_story_form();
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
} else if ($data = $mform->get_data()) {
    // File upload handling.
    // If the user uploaded a file, read its text content and append it to the story.
    $storytext = $data->story ?? '';

    $draftitemid = file_get_submitted_draft_itemid('storyfile');
    if (!empty($draftitemid)) {
        $fs = get_file_storage();
        $usercontext = context_user::instance($USER->id);

        // Retrieve all files from the draft area (exclude directories).
        $draftfiles = $fs->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $draftitemid,
            'id',
            false   // Exclude directories.
        );

        foreach ($draftfiles as $draftfile) {
            $filename  = $draftfile->get_filename();
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if ($extension === 'txt') {
                // Plain text: read directly, then normalise to clean UTF-8.
                $filecontent = $draftfile->get_content();

                if (strncmp($filecontent, "\xEF\xBB\xBF", 3) === 0) {
                    $filecontent = substr($filecontent, 3);                  // UTF-8 BOM.
                } else if (strncmp($filecontent, "\xFF\xFE", 2) === 0) {
                    $filecontent = mb_convert_encoding(substr($filecontent, 2), 'UTF-8', 'UTF-16LE');
                } else if (strncmp($filecontent, "\xFE\xFF", 2) === 0) {
                    $filecontent = mb_convert_encoding(substr($filecontent, 2), 'UTF-8', 'UTF-16BE');
                } else if (!mb_check_encoding($filecontent, 'UTF-8')) {
                    $filecontent = \core_text::convert($filecontent, 'Windows-1250', 'UTF-8'); // Magyar ANSI.
                }
                $filecontent = mb_convert_encoding($filecontent, 'UTF-8', 'UTF-8');   // Remaining corrupted bytes.
                $filecontent = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $filecontent);
            } else if ($extension === 'pdf') {
                // PDF: pdftotext if available, otherwise the bundled smalot parser.
                $filecontent = local_aiquestions_m2_pdf_to_text($draftfile);
            } else if ($extension === 'docx') {
                // DOCX: extract paragraphs from word/document.xml using a real XML parser.
                $tmppath = $draftfile->copy_content_to_temp('aiquestions', '.docx');
                $zip = new ZipArchive();
                $filecontent = '';
                if ($zip->open($tmppath) === true) {
                    $xml = $zip->getFromName('word/document.xml');
                    $zip->close();
                    if ($xml !== false) {
                        $dom = new DOMDocument();
                        $prev = libxml_use_internal_errors(true);
                        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
                        libxml_clear_errors();
                        libxml_use_internal_errors($prev);

                        if ($loaded) {
                            $xpath = new DOMXPath($dom);
                            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

                            $paragraphs = [];
                            foreach ($xpath->query('//w:p') as $p) {
                                $text = '';
                                // Walk text runs, tabs and line breaks in document order.
                                foreach ($xpath->query('.//w:t | .//w:tab | .//w:br', $p) as $node) {
                                    if ($node->localName === 't') {
                                        $text .= $node->textContent;
                                    } else if ($node->localName === 'tab') {
                                        $text .= "\t";
                                    } else {
                                        $text .= "\n";
                                    }
                                }
                                if (trim($text) !== '') {
                                    $paragraphs[] = $text;
                                }
                            }
                            $filecontent = implode("\n\n", $paragraphs);
                        }
                    }
                }
                @unlink($tmppath);
            } else if ($extension === 'pptx') {
                // PPTX: unzip and extract text from ppt/slides/slide*.xml.
                // Each slide's text runs (<a:t>) are collected in slide order.
                $tmppath = $draftfile->copy_content_to_temp('aiquestions', '.pptx');
                $zip = new ZipArchive();
                $filecontent = '';
                if ($zip->open($tmppath) === true) {
                    // Collect slide filenames and sort them numerically (slide1, slide2 …).
                    $slidefiles = [];
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entry = $zip->getNameIndex($i);
                        if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $entry, $m)) {
                            $slidefiles[(int)$m[1]] = $entry;
                        }
                    }
                    ksort($slidefiles);

                    $slidetexts = [];
                    foreach ($slidefiles as $slidenum => $slidepath) {
                        $xml = $zip->getFromName($slidepath);
                        if ($xml === false) {
                            continue;
                        }
                        // Insert newline before each paragraph tag so paragraphs don't merge.
                        $xml = preg_replace('/<a:p[ >]/', "\n<a:p>", $xml);
                        // Insert space between adjacent text runs so words don't merge.
                        $xml = preg_replace('/<\/a:t>\s*<a:t[^>]*>/', ' ', $xml);
                        $slidetext = trim(strip_tags($xml));
                        $slidetext = html_entity_decode($slidetext, ENT_QUOTES | ENT_XML1, 'UTF-8');
                        // Collapse runs of blank lines to a single blank line.
                        $slidetext = preg_replace("/(\r?\n){3,}/", "\n\n", $slidetext);
                        if ($slidetext !== '') {
                            $slidetexts[] = "## Slide {$slidenum}\n" . $slidetext;
                        }
                    }
                    $zip->close();
                    $filecontent = implode("\n\n", $slidetexts);
                }
                @unlink($tmppath);
            } else {
                // Unsupported type — skip.
                $filecontent = '';
            }

            $filecontent = trim($filecontent);
            if ($filecontent !== '') {
                // Append file content to whatever was typed in the textarea.
                $storytext = trim($storytext) !== ''
                    ? $storytext . "\n\n" . $filecontent
                    : $filecontent;
            }
        }
    }

    // PDF selected from the course resources (dropdown). It supplements the textarea / uploaded file.
    $coursepdfid = (int)($data->pdf ?? 0);
    if ($coursepdfid > 0) {
        $coursepdf = get_file_storage()->get_file_by_id($coursepdfid);
        if ($coursepdf) {
            $pdftext = trim(local_aiquestions_m2_pdf_to_text($coursepdf));
            if ($pdftext !== '') {
                $storytext = trim($storytext) !== ''
                    ? $storytext . "\n\n" . $pdftext
                    : $pdftext;
            }
        }
    }

    // Call the adhoc task.
    $task = new \local_aiquestions_m2\task\questions();
    if ($task) {
        $uniqid = uniqid($USER->id, true);
        $preset = $data->preset;
        $primer = 'primer' . $preset;
        $instructions = 'instructions' . $preset;
        $example = 'example' . $preset;
        $task->set_custom_data(['category' => $data->category ?? '',
                                'primer' => $data->$primer,
                                'instructions' => $data->$instructions,
                                'example' => $data->$example,
                                'story' => $storytext, // Merged textarea + uploaded file + course PDF.
                                'numofquestions' => $data->numofquestions,
                                'addidentifier' => $data->addidentifier,
                                'customprefix' => $data->customprefix ?? '',
                                'courseid' => $data->courseid,
                                'userid' => $USER->id,
                                'uniqid' => $uniqid ]);
        \core\task\manager::queue_adhoc_task($task);
        $success = get_string('tasksuccess', 'local_aiquestions_m2');
    } else {
        $error = get_string('taskerror', 'local_aiquestions_m2');
    }
    // Check if the cron is overdue.
    $lastcron = get_config('tool_task', 'lastcronstart');
    $cronoverdue = ($lastcron < time() - 3600 * 24);
    // Link to the question bank: on Moodle 5.x the course has question bank modules (list page), on 4.5 one bank.
    if (local_aiquestions_m2_uses_qbank_modules()) {
        $questionbankurl = new moodle_url('/question/banks.php', ['courseid' => $courseid]);
    } else {
        $questionbankurl = new moodle_url('/question/edit.php', ['courseid' => $courseid]);
    }
    // Prepare the data for the template.
    $datafortemplate = [
        'questionbankurl' => $questionbankurl->out(false),
        'courseid' => $courseid,
        'wwwroot' => $CFG->wwwroot,
        'uniqid' => $uniqid,
        'userid' => $USER->id,
        'cron' => $cronoverdue,
    ];
    // Load the ready template.
    echo $OUTPUT->render_from_template('local_aiquestions_m2/loading', $datafortemplate);

    echo html_writer::div('', 'd-none', [
        'id' => 'local_aiquestions_m2_courseid',
        'data-courseid' => $courseid,
    ]);

    // GIFT download button (enabled when the generation has finished successfully).
    $downloadurl = new moodle_url('/local/aiquestions_m2/download.php', ['uniqid' => $uniqid]);
    $checkurl    = new moodle_url('/local/aiquestions_m2/download.php', ['uniqid' => $uniqid, 'check' => 1]);
    echo html_writer::div(
        html_writer::link($downloadurl, get_string('downloadgift', 'local_aiquestions_m2'), [
            'id' => 'aiq-download-gift',
            'class' => 'btn btn-secondary disabled',
            'aria-disabled' => 'true',
            'tabindex' => '-1',
        ]),
        'mt-3'
    );
    echo html_writer::script("
(function() {
    var btn = document.getElementById('aiq-download-gift');
    var checkurl = " . json_encode($checkurl->out(false)) . ";
    var timer = setInterval(function() {
        fetch(checkurl, {credentials: 'same-origin'})
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.status === 'ready') {
                    btn.classList.remove('disabled', 'btn-secondary');
                    btn.classList.add('btn-primary');
                    btn.removeAttribute('aria-disabled');
                    btn.removeAttribute('tabindex');
                    clearInterval(timer);
                } else if (d.status === 'failed') {
                    clearInterval(timer);
                }
            })
            .catch(function() {});
    }, 5000);
})();
");
} else {
    $mform->display();
}
echo $OUTPUT->footer();
