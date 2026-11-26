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

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aiquestions_m2_settings', new lang_string('pluginname', 'local_aiquestions_m2'));

    // Provider selection.
    $settings->add(new admin_setting_configselect(
        'local_aiquestions_m2/provider',
        get_string('provider', 'local_aiquestions_m2'),
        get_string('provider_desc', 'local_aiquestions_m2'),
        'moodle_ai',
        [
            'moodle_ai' => 'Moodle AI Subsystem',
            'ollama' => 'Ollama',
        ]
    ));

    // Add info about using Moodle's AI settings.
    $settings->add(new admin_setting_heading(
        'local_aiquestions_m2/aiinfo_heading',
        get_string('aisettingsheader', 'local_aiquestions_m2'),
        get_string('aisettingsdesc', 'local_aiquestions_m2')
    ));

    $settings->add(new admin_setting_heading(
        'local_aiquestions_m2/ollama_settings',
        get_string('ollama_settings', 'local_aiquestions_m2'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiquestions_m2/ollama_apiurl',
        get_string('ollama_apiurl', 'local_aiquestions_m2'),
        get_string('ollama_apiurl_desc', 'local_aiquestions_m2'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_aiquestions_m2/ollama_apikey',
        get_string('ollama_apikey', 'local_aiquestions_m2'),
        get_string('ollama_apikey_desc', 'local_aiquestions_m2'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiquestions_m2/ollama_model',
        get_string('ollama_model', 'local_aiquestions_m2'),
        get_string('ollama_model_desc', 'local_aiquestions_m2'),
        'llama3',
        PARAM_TEXT
    ));

    // Number of tries.
    $settings->add(new admin_setting_configtext(
        'local_aiquestions_m2/numoftries',
        get_string('numoftriesset', 'local_aiquestions_m2'),
        get_string('numoftriesdesc', 'local_aiquestions_m2'),
        10,
        PARAM_INT,
        10
    ));

    // Presets.
    $settings->add(new admin_setting_heading(
        'local_aiquestions_m2/presets',
        get_string('presets', 'local_aiquestions_m2'),
        get_string('presetsdesc', 'local_aiquestions_m2') .
        get_string('shareyourprompts', 'local_aiquestions_m2'),
    ));

    // Prompt parts (set by the admin only).
    $settings->add(new admin_setting_configtextarea(
        'local_aiquestions_m2/presettprimer1',
        get_string('presetprimer', 'local_aiquestions_m2'),
        get_string('primer_help', 'local_aiquestions_m2'),
        get_string('presetprimerdefault1', 'local_aiquestions_m2'),
        PARAM_TEXT,
        4000
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_aiquestions_m2/presetinstructions1',
        get_string('presetinstructions', 'local_aiquestions_m2'),
        get_string('instructions_help', 'local_aiquestions_m2'),
        get_string('presetinstructionsdefault1', 'local_aiquestions_m2'),
        PARAM_RAW,
        4000
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_aiquestions_m2/presetexample1',
        get_string('presetexample', 'local_aiquestions_m2'),
        get_string('example_help', 'local_aiquestions_m2'),
        get_string('presetexampledefault1', 'local_aiquestions_m2'),
        PARAM_RAW,
        4000
    ));

    // Highlighted block between the example and the prompt end.
    $settings->add(new admin_setting_description(
        'local_aiquestions_m2/contentinfo',
        '',
        html_writer::div(
            html_writer::tag('strong', get_string('contentinfo_title', 'local_aiquestions_m2')) .
            html_writer::empty_tag('br') .
            get_string('contentinfo', 'local_aiquestions_m2'),
            'alert alert-info'
        )
    ));

    // Text added AFTER the content.
    $settings->add(new admin_setting_configtextarea(
        'local_aiquestions_m2/presetpromptend1',
        get_string('presetpromptend', 'local_aiquestions_m2'),
        get_string('presetpromptend_desc', 'local_aiquestions_m2'),
        get_string('presetpromptenddefault', 'local_aiquestions_m2'),
        PARAM_RAW,
        4000
    ));

    $ADMIN->add('localplugins', $settings);
}
