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
 * Presets page.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_reportbuilder\system_report_factory;
use tool_enrolprofile\reportbuilder\local\systemreports\presets;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_enrolprofile_presets');

$presets = system_report_factory::create(presets::class, context_system::instance(), 'tool_enrolprofile');
$PAGE->requires->js_call_amd('tool_enrolprofile/presets', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepresets', 'tool_enrolprofile'));

echo $OUTPUT->render_from_template('tool_enrolprofile/addpresetbutton', []);
echo $presets->output();
echo $OUTPUT->footer();
