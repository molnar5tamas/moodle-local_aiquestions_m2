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
 * Get questions from the API using Moodle's AI subsystem.
 *
 * @param object data data to create questions from
 * @return object questions of generated questions
 */
function local_aiquestions_m2_get_questions($data) {

    global $CFG, $USER;

    // Build primer.
    $primer = $data->primer;
    $primer .= "Write $data->numofquestions questions.";

    // Remove new lines and carriage returns.
    $story = str_replace("\n", " ", $data->story);
    $story = str_replace("\r", " ", $story);
    $instructions = str_replace("\n", " ", $data->instructions);
    $instructions = str_replace("\r", " ", $instructions);
    $example = str_replace("\n", " ", $data->example);
    $example = str_replace("\r", " ", $example);

    // Build the complete prompt for Moodle's AI subsystem.
    $prompttext = $primer . "\n\n";
    $prompttext .= "Instructions: " . $instructions . "\n\n";
    $prompttext .= " " . $example . "\n\n";

    $prompttext .= "Create " . $data->numofquestions . " questions in the GIFT format based on this provided content: <content>\n"
    . $story . "\n</content>\n\nREMINDER: Write your output in the SAME language as the content above.";

    // A stricter formatting guide for the model: this will result in fewer incorrect GIFT entries.
    $prompttext .= "\n\nOUTPUT FORMAT RULES: Output ONLY the GIFT questions, no introduction, no explanation, "
        . "no markdown and no code fences. Separate questions with exactly ONE blank line. "
        . "Do NOT use question titles and never use the :: characters. "
        . "Every question must have exactly ONE correct answer (=) and exactly THREE wrong answers (~). "
        . "Do not use the characters ~ = # { } : inside the question text or answers unless they are escaped with a backslash.";

    // Check prompttext.
    // Check prompttext. file_put_contents(__DIR__ . '/prompttext.txt', $prompttext);.

    // Get the context - use system context if no specific context is available.
    $contextid = context_system::instance()->id;

    // Use Moodle's AI subsystem to generate questions.
    $aianswer = new \local_aiquestions_m2\sendtoai();
    $result = $aianswer->execute($contextid, $prompttext);

    $questions = new stdClass(); // The questions object.
    if ($result['success']) {
        $questions->text = $result['generatedcontent'];
        $questions->prompt = $story;
    } else {
        // Handle error case.
        $questions = new stdClass();
        $questions->error = $result['error'] ?? 'Unknown error occurred';
        $questions->errorcode = $result['errorcode'] ?? 0;
        $questions->prompt = $story;
    }
    return $questions;
}

// Question bank / category helpers (Moodle 4.5 and Moodle 5.x).

/**
 * Does this site use the Moodle 5.0+ question bank model?
 *
 * From Moodle 5.0 question categories can only live in a module context (mod_qbank or quiz),
 * not in the course context any more.
 *
 * @return bool true on Moodle 5.0 or later
 */
function local_aiquestions_m2_uses_qbank_modules() {
    global $CFG;
    // BRANCH $CFG->branch is "405" for 4.5, "500" for 5.0, "501" for 5.1.
    return (int) $CFG->branch >= 500;
}

/**
 * Parse the value of the "questioncategory" form element ("categoryid,contextid").
 *
 * @param mixed $category raw form value
 * @return int[] [categoryid, contextid], 0 where unknown
 */
function local_aiquestions_m2_parse_category($category) {
    if (empty($category)) {
        return [0, 0];
    }
    $parts = explode(',', (string) $category);
    return [(int) $parts[0], isset($parts[1]) ? (int) $parts[1] : 0];
}

/**
 * Contexts offered in the question category selector of the form.
 *
 * Moodle 4.5: the course context.
 * Moodle 5.x: the module contexts of the course question banks where the user may add questions
 * (a default course question bank is created when the course has none yet).
 *
 * @param int $courseid course id
 * @return \context[] contexts
 */
function local_aiquestions_m2_get_target_contexts($courseid) {
    if (!local_aiquestions_m2_uses_qbank_modules()) {
        return [\context_course::instance($courseid)];
    }

    $helper = '\core_question\local\bank\question_bank_helper';
    $list = function () use ($helper, $courseid) {
        $cap = ['moodle/question:add'];
        $banks = array_merge(
            array_values($helper::get_activity_instances_with_shareable_questions([$courseid], [], $cap)),
            array_values($helper::get_activity_instances_with_private_questions([$courseid], [], $cap))
        );
        $contexts = [];
        foreach ($banks as $bank) {
            $contextid = $bank->contextid ?? ($bank->cminfo->context->id ?? 0);
            if ($contextid && ($context = \context::instance_by_id($contextid, IGNORE_MISSING))) {
                $contexts[$context->id] = $context;
            }
        }
        return array_values($contexts);
    };

    $contexts = $list();
    if (empty($contexts)) {
        // No question bank in the course yet: create the default one, then look again.
        local_aiquestions_m2_get_default_category($courseid);
        $contexts = $list();
    }
    return $contexts;
}

/**
 * Get (or create) the default question category of a course.
 *
 * Moodle 4.5: default category of the course context (sortorder 999).
 * Moodle 5.x: default category of the course "system" question bank (created if missing),
 * question_make_default_categories() is deprecated there.
 *
 * @param int $courseid course id
 * @return \stdClass|false question_categories record, or false
 */
function local_aiquestions_m2_get_default_category($courseid) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/questionlib.php');

    if (!local_aiquestions_m2_uses_qbank_modules()) {
        $coursecontext = \context_course::instance($courseid);
        $contexts = new \core_question\local\bank\question_edit_contexts($coursecontext);
        if (!$category = $DB->get_record('question_categories', ['contextid' => $coursecontext->id, 'sortorder' => 999])) {
            $category = question_make_default_categories($contexts->all());
        }
        return $category ?: false;
    }

    try {
        $helper = '\core_question\local\bank\question_bank_helper';
        $qbank = $helper::get_default_open_instance_system_type(get_course($courseid), true);
        if ($qbank) {
            $category = question_get_default_category($qbank->context->id, true);
            if ($category) {
                return $category;
            }
        }
    } catch (\Throwable $e) {
        debugging('Could not get the default question bank: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
    return false;
}

/**
 * Resolve the question category for the running Moodle version.
 *
 * Moodle 4.5: the category must match BOTH ids of "categoryid,contextid"; otherwise the course default is used.
 * Moodle 5.x: the category id is authoritative (the context is derived from the category), the category
 * must be in a module context, and a top category (parent = 0) is replaced by the default category of its bank;
 * otherwise the default course question bank category is used.
 *
 * @param int $courseid course id
 * @param mixed $category form value ("categoryid,contextid")
 * @return \stdClass|false question_categories record, or false
 */
function local_aiquestions_m2_get_category($courseid, $category) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/questionlib.php');

    [$categoryid, $contextid] = local_aiquestions_m2_parse_category($category);
    $record = false;

    if (!local_aiquestions_m2_uses_qbank_modules()) {
        if ($categoryid && $contextid) {
            $record = $DB->get_record('question_categories', ['id' => $categoryid, 'contextid' => $contextid]);
        }
    } else if ($categoryid) {
        $record = $DB->get_record('question_categories', ['id' => $categoryid]);
        if ($record) {
            $context = \context::instance_by_id($record->contextid, IGNORE_MISSING);
            if (!$context || $context->contextlevel != CONTEXT_MODULE) {
                debugging('AI questions: category ' . $record->id . ' is not in a module context.', DEBUG_DEVELOPER);
                $record = false;
            } else if ((int) $record->parent === 0) {
                $record = question_get_default_category($context->id, true);
            }
        }
    }

    return $record ?: local_aiquestions_m2_get_default_category($courseid);
}

// Helper functions for robust GIFT processing.

/**
 * Normalize the raw AI output: line endings, code fences, BOM, whitespace-only lines.
 *
 * The original code only split based on "\n\n", so even a valid question appeared to be
 * invalid due to "\r\n" line breaks, markdown code fences, blank lines consisting of spaces,
 * or introductory text, causing the entire batch to be discarded.
 *
 * @param string $gift raw output
 * @return string normalized text
 */
function local_aiquestions_m2_normalize_gift($gift) {
    $gift = (string) $gift;
    $gift = preg_replace('/^\xEF\xBB\xBF/', '', $gift);            // UTF-8 BOM.
    $gift = str_replace(["\r\n", "\r"], "\n", $gift);              // Uniform line breaks.
    $gift = preg_replace('/^[ \t]*```[a-zA-Z]*[ \t]*$/m', '', $gift); // Markdown  (gift).
    $gift = preg_replace('/^[ \t]*\/\/.*$/m', '', $gift);          // GIFT comments rows.
    $gift = preg_replace('/^[ \t]+$/m', '', $gift);                // Lines containing only spaces.
    $gift = preg_replace("/\n{3,}/", "\n\n", $gift);               // Multiple blank lines -> one.

    return trim($gift);
}

/**
 * Split the normalized GIFT text into separate question blocks.
 * If there are ::title:: lines, it splits along those lines (so even an empty
 * line within a question won't cause a problem); otherwise, it splits along empty lines.
 *
 * @param string $gift raw or normalized GIFT text
 * @return array of strings, one per question candidate
 */
function local_aiquestions_m2_split_gift($gift) {
    $gift = local_aiquestions_m2_normalize_gift($gift);
    if ($gift === '') {
        return [];
    }
    if (preg_match('/^::/m', $gift)) {
        $parts = preg_split('/^(?=::)/m', $gift, -1, PREG_SPLIT_NO_EMPTY);
    } else {
        $parts = preg_split('/\n{2,}/', $gift, -1, PREG_SPLIT_NO_EMPTY);
    }
    $blocks = [];
    foreach ($parts as $part) {
        // Removing blank lines within a block, trimming lines.
        $lines = array_values(array_filter(array_map('trim', explode("\n", $part)), 'strlen'));
        if ($lines) {
            $blocks[] = implode("\n", $lines);
        }
    }

    return $blocks;
}

/**
 * Mask backslash-escaped characters with 'x', so that structural characters ({ } ~ = #) can be found without false hits.
 *
 * @param string $text text
 * @return string masked text of identical length
 */
function local_aiquestions_m2_mask_escaped($text) {
    $masked = preg_replace_callback('/\\\\./su', function ($m) {
        return str_repeat('x', strlen($m[0]));
    }, $text);
    return $masked === null ? $text : $masked;
}

/**
 * Common question text extraction. Both the validation and the save functions use this,
 * so whatever passes validation will have the same text saved.
 *
 * @param string $block one question
 * @return string question text (empty string if not found)
 */
function local_aiquestions_m2_extract_question_text($block) {
    $flat = trim(preg_replace('/\s*\n\s*/', ' ', $block));
    $safe = local_aiquestions_m2_mask_escaped($flat);

    $offset = 0;
    if (preg_match('/^::(.+?)::/su', $safe, $m)) {
        $offset = strlen($m[0]);
    }
    $open = strpos($safe, '{', $offset);
    $close = strrpos($safe, '}');
    if ($open === false || $close === false || $close < $open) {
        return '';
    }

    // Text before { and after }.
    $text = trim(substr($flat, $offset, $open - $offset) . ' ' . substr($flat, $close + 1));
    // Trim the format marker: html, markdown, plain, moodle.
    $text = preg_replace('/^\s*\[(html|markdown|plain|moodle)\]/i', '', $text);
    // Unescape GIFT special characters (colon, equals, tilde, braces, and hash) and keep original text if UTF-8 is invalid.
    $unescaped = preg_replace('/\\\\(.)/su', '$1', $text);
    return trim($unescaped ?? $text);
}

/**
 * Does the question contain actual, readable text?
 * Without HTML tags, it must contain at least 10 characters and at least one letter.
 * (“<p></p><br>” or “........” no longer count as question text.)
 *
 * @param string $text question text
 * @return bool true if there is real text
 */
function local_aiquestions_m2_has_question_text($text) {
    $plain = trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES, 'UTF-8'));
    return mb_strlen($plain) >= 10 && preg_match('/\p{L}/u', $plain) === 1;
}

/**
 * Validate ONE GIFT multichoice question (1 right, 3 wrong answers).
 *
 * The original check counted the ~ and = characters in the entire section enclosed in curly braces.
 * This was disrupted by the feedback (#...), the \= \~ escape character, the
 * weighted response (~%50%...), and the question without a title. We'll address these issues here.
 *
 * @param string $block one question
 * @param int $expectedwrong expected number of wrong answers
 * @return bool true if valid
 */
function local_aiquestions_m2_is_valid_gift_question($block, $expectedwrong = 3) {
    $flat = trim(preg_replace('/\s*\n\s*/', ' ', $block));
    $safe = local_aiquestions_m2_mask_escaped($flat);

    // Title (optional).
    $offset = 0;
    if (preg_match('/^::(.+?)::/su', $safe, $m)) {
        $offset = strlen($m[0]);
    }

    $open = strpos($safe, '{', $offset);
    $close = strrpos($safe, '}');
    if ($open === false || $close === false || $close < $open) {
        return false; // There is no response block.
    }

    // Validate the question text using the common function
    // (at least 10 characters and at least one letter, excluding HTML).
    if (!local_aiquestions_m2_has_question_text(local_aiquestions_m2_extract_question_text($block))) {
        return false;
    }

    // Answers: Exclude feedback (#...), then count the ~ and = characters.
    $answers = substr($safe, $open + 1, $close - $open - 1);
    $answers = preg_replace('/#[^~=]*/u', '', $answers);
    if ($answers === null) {
        return false;
    }

    $wrong = substr_count($answers, '~');
    $right = substr_count($answers, '=');
    if ($right != 1 || $wrong != $expectedwrong) {
        return false;
    }

    // No answer should be left blank (weighting %..% omitted).
    $items = preg_split('/[~=]/u', $answers);
    array_shift($items); // The first element is the part before the symbol.
    foreach ($items as $item) {
        $item = preg_replace('/^\s*%-?\d+(\.\d+)?%/u', '', $item);
        if (trim($item) === '') {
            return false;
        }
    }
    return true;
}

/**
 * Return only the valid question blocks of a GIFT text.
 *
 * We'll skip the invalid questions and keep the valid ones.
 *
 * @param string $gift GIFT text
 * @return array of valid question blocks
 */
function local_aiquestions_m2_get_valid_gift_blocks($gift) {
    $valid = [];
    foreach (local_aiquestions_m2_split_gift($gift) as $block) {
        if (local_aiquestions_m2_is_valid_gift_question($block)) {
            $valid[] = $block;
        }
    }
    return $valid;
}


/**
 * Create questions from data got from ChatGPT output.
 *
 * @param int $courseid course id
 * @param int|string $category question category ("categoryid,contextid" as sent by the form)
 * @param string $gift questions in GIFT format
 * @param int $numofquestions number of questions to generate
 * @param int $userid user id
 * @param bool $addidentifier add an GPT prefix to question names.
 * @param string $customprefix custom prefix added to question names.
 * @return array of objects of created questions
 */
function local_aiquestions_m2_create_questions(
    $courseid,
    $category,
    $gift,
    $numofquestions,
    $userid,
    $addidentifier,
    $customprefix = ''
) {
    global $CFG, $USER, $DB;

    require_once($CFG->libdir . '/questionlib.php');
    require_once($CFG->dirroot . '/question/format.php');
    require_once($CFG->dirroot . '/question/format/gift/format.php');

    $qformat = new \qformat_gift();

    // Resolve the question category - version dependent (Moodle 4.5 vs Moodle 5.x).
    $category = local_aiquestions_m2_get_category($courseid, $category);
    if (!$category) {
        debugging('AI questions: no question category found for course ' . $courseid, DEBUG_DEVELOPER);
        return false;
    }

    // Moodle 5.x: id of the question bank module that holds the category (0 on Moodle 4.5).
    $qbankcmid = 0;
    $categorycontext = \context::instance_by_id($category->contextid, IGNORE_MISSING);
    if ($categorycontext && $categorycontext->contextlevel == CONTEXT_MODULE) {
        $qbankcmid = (int) $categorycontext->instanceid;
    }

    // We will only keep the valid GIFT questions; we will omit the incorrect ones.
    $blocks = local_aiquestions_m2_get_valid_gift_blocks($gift);

    // 2)  If none of them are valid, an error occurs (the caller can try again).
    if (empty($blocks)) {
        return false;
    }

    // 3) If there are more valid entries than requested, we will discard the excess.
    if ($numofquestions > 0 && count($blocks) > $numofquestions) {
        $blocks = array_slice($blocks, 0, $numofquestions);
    }

    $qtype = 'multichoice';
    $createdquestions = []; // Array of objects of created questions.
    foreach ($blocks as $block) {
        try {
            $singlequestion = explode("\n", $block);

            // Same extraction as in the validation.
            $questiontext = local_aiquestions_m2_extract_question_text($block);
            if (!local_aiquestions_m2_has_question_text($questiontext)) {
                continue; // We didn't go with a blank question.
            }

            $q = $qformat->readquestion($singlequestion);

            // If the GIFT reader was unable to interpret it, we'll skip this question (we won't discard all of them).
            if (!$q) {
                continue;
            }
            // We only did multiple-choice questions (using the `save_question` type with the `multichoice` option).
            if (!empty($q->qtype) && $q->qtype !== 'multichoice') {
                continue;
            }

            $q->category = $category->id;
            $q->createdby = $userid;
            $q->modifiedby = $userid;
            $q->timecreated = time();
            $q->timemodified = time();
            $q->questiontext = ['text' => "<p>" . $questiontext . "</p>"];
            $q->questiontextformat = 1;

            $prefix = '';
            if ($addidentifier == 1) {
                $prefix .= 'AI_: ';
            }
            $customprefix = trim((string) $customprefix);
            if ($customprefix !== '') {
                $prefix .= $customprefix . ' ';
            }
            $q->name = $prefix . $q->name;

            $created = question_bank::get_qtype($qtype)->save_question($q, $q);
            if ($created) {
                // FIX: The `save_question()` function sets the global `$USER` (for cron jobs: `portaladmin`) as the creator,
                // overwriting the value of `$q->createdby`. We'll set the actual user later.
                local_aiquestions_m2_set_question_creator($created, $userid);
                $created->cmid = $qbankcmid;
                $createdquestions[] = $created;
            }
        } catch (\Throwable $e) {
            // Don't let an error on one question derail the rest.
            debugging('AI question creation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            continue;
        }
    }

    // If at least one question has been created, we'll return them.
    if (!empty($createdquestions)) {
        return $createdquestions;
    }
    return false;
}
/**
 * Set the creator/modifier/owner of a freshly saved question.
 *
 * Moodle's question_type::save_question() always uses the global $USER, which in an
 * adhoc task (cron) is the site admin. This sets the real user (who submitted the form).
 *
 * @param stdClass $question the saved question object (must have ->id)
 * @param int $userid id of the user who should be recorded as creator
 * @return void
 */
function local_aiquestions_m2_set_question_creator($question, $userid) {
    global $DB;

    $userid = (int)$userid;
    if (empty($question->id) || $userid <= 0 || !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
        return;
    }

    // Question table: "Created by" / "Last modified by" columns of the question bank.
    $DB->set_field('question', 'createdby', $userid, ['id' => $question->id]);
    $DB->set_field('question', 'modifiedby', $userid, ['id' => $question->id]);

    // Question bank entry owner (Moodle 4.x).
    $entryid = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $question->id]);
    if ($entryid) {
        $DB->set_field('question_bank_entries', 'ownerid', $userid, ['id' => $entryid]);
    }

    $question->createdby = $userid;
    $question->modifiedby = $userid;
}

/**
 * Escape json.
 *
 * @param string $value json to escape
 * @return string result escaped json
 */
function local_aiquestions_m2_escape_json($value) {
    $escapers = ["\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c"];
    $replacements = ["\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b"];
    $result = str_replace($escapers, $replacements, $value);
    return $result;
}

/**
 * Check if the gift format is valid.
 *
 * Default: true if there is AT LEAST ONE valid question (incorrect ones are
 * skipped by local_aiquestions_m2_create_questions() anyway). If all questions must be correct,
 * call it with $requireall = true.
 *
 * @param string $gift questions in GIFT format
 * @param bool $requireall true: Every question must be valid
 * @return bool true if valid, false if not
 */
function local_aiquestions_m2_check_gift($gift, $requireall = false) {
    $blocks = local_aiquestions_m2_split_gift($gift);
    if (empty($blocks)) {
        return false;
    }

    $validcount = 0;
    foreach ($blocks as $block) {
        if (local_aiquestions_m2_is_valid_gift_question($block)) {
            $validcount++;
        } else if ($requireall) {
            return false;
        }
    }
    return $validcount > 0;
}

/**
 * Extract text from a PDF file using the file ID
 *
 * @param int $fileid File ID from the files table
 * @return string Extracted text or empty string if extraction fails
 */
function local_aiquestions_m2_extract_pdf_text($fileid) {
    global $DB, $CFG;

    try {
        // Get file information.
        $file = $DB->get_record('files', ['id' => $fileid], '*', MUST_EXIST);

        // Get the file storage.
        $fs = get_file_storage();
        $storedfile = $fs->get_file_by_id($fileid);

        if (!$storedfile) {
            return '';
        }

        // Create a temporary file to work with.
        $tempfile = tempnam($CFG->dataroot . '/temp/', 'moodle_pdf_');
        $storedfile->copy_content_to($tempfile);

        // Include the PDF parser.
        require_once($CFG->dirroot . '/local/aiquestions_m2/smalot_pdfparser/alt_autoload.php');

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($tempfile);
        $text = $pdf->getText();

        // Clean up the temporary file.
        unlink($tempfile);

        // UTF-8 safe, keeps á é í ó ö ő ú ü ű).
        // Make sure the text is valid UTF-8 (invalid bytes are dropped); otherwise /u regexes return null.
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Normalize whitespace (/u is important, so UTF-8 bytes are never treated as spaces).
        $text = preg_replace('/\s+/u', ' ', $text);

        // Remove only control characters and the replacement character, keep all letters.
        // $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{FFFD}]/u', '', $text);.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F\x{00AD}\x{FFFD}]/u', '', $text);

        $text = trim($text);
        return $text;
    } catch (Exception $e) {
        // Log the error for debugging.
        debugging('PDF extraction failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return '';
    }
}
