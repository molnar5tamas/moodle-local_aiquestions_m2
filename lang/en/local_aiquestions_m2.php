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
 * Plugin strings are defined here.
 *
 * @package     local_aiquestions_m2
 * @category    string
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com> , Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addidentifier'] = 'Add a "AI_: " prefix to the question name';
$string['aiquestions'] = 'AI Questions';
$string['aisettingsdesc'] = 'This plugin now uses Moodle\'s built-in AI subsystem. To configure AI providers (OpenAI, Azure, etc.), please go to Site administration > Plugins > AI > AI subsystem management. The AI provider settings have been moved there for centralized management across all AI-enabled plugins.';
$string['aisettingsheader'] = 'AI Configuration';
$string['azureapiendpoint'] = 'Azure API Endpoint';
$string['azureapiendpointdesc'] = 'Enter the Azure API endpoint URL here';
$string['backtocourse'] = 'Back to course';
$string['category'] = 'Question category';
$string['category_help'] = 'If the category selection is empty, open the question bank for this course once.';
$string['contentinfo'] = 'The content entered, uploaded or selected by the user is inserted here, between the example and the prompt end.';
$string['contentinfo_title'] = 'User content';
$string['createdquestionssuccess'] = 'Created questions successfully';
$string['createdquestionsuccess'] = 'Created question successfully';
$string['createdquestionwithid'] = 'Created question with id ';
$string['cronoverdue'] = 'The cron task seems not to run,
questions generation rely on AdHoc Tasks that are created by the cron task, please check your cron settings.
See <a href="https://docs.moodle.org/en/Cron#Setting_up_cron_on_your_system">
https://docs.moodle.org/en/Cron#Setting_up_cron_on_your_system
</a> for more information.';
$string['customprefix'] = 'Custom prefix';
$string['customprefix_help'] = 'You can add custom prefix';
$string['downloadgift'] = 'Download GIFT';
$string['editpreset'] = 'Edit the preset before sending it to the AI. This setting is recommended only for advanced users!';
$string['errornostory'] = 'Please enter some text, upload a file, or select a PDF.';
$string['errornotcreated'] = 'Error: questions were not created';
$string['example'] = 'Example';
$string['example_help'] = 'The example shows the AI an example output, to clarify the formatting.';
$string['generate'] = 'Generate questions';
$string['generatemore'] = 'Generate more questions';
$string['generating'] = 'Generating your questions... (You can safely leave this page, and check later on the question bank)';
$string['generationfailed'] = 'The question generation failed after {$a} tries';
$string['generationtries'] = 'Number of tries sent to AI: <b>{$a}</b>';
$string['gotoquestionbank'] = 'Go to question bank';
$string['instructions_help'] = 'The instructions tell the AI what to do.';
$string['model'] = 'Model';
$string['model_desc'] = 'Language model to use. <a href="https://platform.openai.com/docs/models/">More info</a>.';
$string['nopdfselected'] = 'No pdf selected.';
$string['noqbankavailable'] = 'This course has no question bank where you can add questions.';
$string['numofquestions'] = 'Number of questions to generate';
$string['numoftries'] = '<b>{$a}</b> tries';
$string['numoftriesdesc'] = 'Number of tries to send to OpenAI';
$string['numoftriesset'] = 'Number of Tries';
$string['ollama_apikey'] = 'Ollama API key';
$string['ollama_apikey_desc'] = 'Enter your Ollama API key if authentication is required (optional for local instances).';
$string['ollama_apiurl'] = 'Ollama API URL';
$string['ollama_apiurl_desc'] = 'Enter the base endpoint for Ollama (e.g., http://localhost:11434).';
$string['ollama_model'] = 'Ollama model';
$string['ollama_model_desc'] = 'The model tag to run (e.g., llama3, mistral).';
$string['ollama_settings'] = 'Ollama settings';
$string['openaikey'] = 'OpenAI or Azure API key';
$string['openaikeydesc'] = 'You can get an OpenAI API key from <a href="https://platform.openai.com/account/api-keys">https://platform.openai.com/account/api-keys</a><br>
Select the "+ Create New Secret Key" button and copy the key to this field.<br>
Note that you need to have an OpenAI account that includes billing settings to get an API key.';
$string['outof'] = 'out of';
$string['pdf'] = 'Select pdf';
$string['pdf_help'] = 'Select a pdf file from the course.';
$string['pluginname'] = 'AI text to questions generator';
$string['pluginname_desc'] = 'This plugin allows you to automatically generate questions from a text using a language AI (eg chatGPT).';
$string['pluginname_help'] = 'Use this plugin from the course administration menu or the question bank.';
$string['preset'] = 'Preset';
$string['presetexample'] = 'Preset example';
$string['presetexampledefault1'] = 'EXAMPLES (format only; do not reuse it):

What is the capital of France? {
~Lyon, the second-largest city in France
=Paris, which has been the capital since the 10th century
~Marseille, the main port city on the Mediterranean coast
~Toulouse, the center of the French aerospace industry
}

Mi a magaságyás előnye a zöldségtermesztésben? {
~Csökkenti a vízfogyasztást
~Kizárja a gyomok megtelepedésének lehetőségét
=Növeli a termés minőségét és mennyiségét
~Csökkenti a termés mennyiségét
}';
$string['presetinstructions'] = 'Preset instructions';
$string['presetinstructionsdefault1'] = 'Your task is to create multiple-choice questions in Moodle GIFT format, based only on the content provided inside the "content" tags.

LANGUAGE RULES (CRITICAL)
1. DETECT the language of the text inside the <content> tags.
2. WRITE all question texts and answers in that SAME language.
3. NEVER translate the content and NEVER switch to another language.

FORMAT RULES:
- Every question must follow this exact structure:
Question text {
=Correct answer
~Wrong answer
~Wrong answer
~Wrong answer
}
- Do NOT include question titles. Never use double colons (::) anywhere in the output.
- Start each question directly with the complete, non-empty question sentence, followed by a space and the opening brace "{".
- Each question has exactly one correct answer, marked with "=", and exactly three incorrect answers, each marked with "~".
- Put a blank line between questions.
- Do NOT number the questions.
- Do NOT include feedback, explanations, comments or "#" in the answers.
- Do NOT use the special characters ~ = # { } : inside questions or answers. If unavoidable, escape them with a backslash.
- Make sure only one answer is clearly correct, and the wrong answers are plausible but definitely incorrect.

ESCAPING (very important):
In question text and answers, the characters ~ = # { } : are special in GIFT. If you need to use one of them as normal text, put a backslash before it, e.g. \= \~ \# \{ \} \:';
$string['presetname'] = 'Preset name';
$string['presetnamedefault1'] = "Multiple choice question";
$string['presetnamedesc'] = 'Name that will be shown to the user';
$string['presetprimer'] = 'Preset primer';
$string['presetprimerdefault1'] = "You are a helpful teaching assistant that creates multiple choice questions based on the content provided below.";
$string['presetpromptend'] = 'Prompt end';
$string['presetpromptend_desc'] = 'Text added to the prompt AFTER the content (e.g. final checks, output format and language reminders).';
$string['presetpromptenddefault'] = 'Before answering, silently verify each question against the rules above (no ::title:: tags, exactly one correct answer (=) and three wrong answers (~), special characters escaped).
Output ONLY the raw GIFT questions: no introduction, no explanation, no markdown, no code fences. Separate questions with exactly ONE blank line.
LANGUAGE (highest priority): write every question and every answer in the SAME language as the text inside the <content> tags, not in the language of the examples. Do not translate.';
$string['presets'] = 'Prompt settings';
$string['presetsdesc'] = 'The prompt sent to the AI is built as: primer + instructions + example + content + prompt end. Only administrators can edit these parts.';
$string['preview'] = 'Preview question in new tab';
$string['primer_help'] = 'The primer is the first information to be sent to the AI, priming it for its task.';
$string['privacy:metadata'] = 'AI text to questions generator does not store any personal data.';
$string['provider'] = 'AI provider';
$string['provider_desc'] = 'Select the AI provider to use for question generation.';
$string['providerdesc'] = 'Select if you are using Azure of OpenAI';
$string['shareyourprompts'] = 'You can find more prompt ideas or share yours at <a target="_blank" href="https://docs.moodle.org/402/en/AI_Text_to_questions_generator">the Moodle Docs page for this plugin</a>.';
$string['story'] = 'Content';
$string['story_help'] = 'The content of your questions. You can also copy/paste whole articles.';
$string['storyfile'] = 'File';
$string['storyfile_help'] = 'You can drag and drop files here to add them.';
$string['taskerror'] = 'The question generation task could not be created.';
$string['tasksuccess'] = 'The question generation task was successfully created';
