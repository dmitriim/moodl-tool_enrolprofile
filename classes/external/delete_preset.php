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

namespace tool_enrolprofile\external;

use core\context\system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use invalid_parameter_exception;
use tool_enrolprofile\event\preset_deleted;
use tool_enrolprofile\preset;

/**
 * Delete preset API class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_preset extends external_api {

    /**
     * Describes the parameters for validate_form webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Preset ID'),
        ]);
    }

    /**
     * Execute API action.
     *
     * @param int $id record ID.
     */
    public static function execute(int $id): void {
        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        require_admin();

        $preset = preset::get_record(['id' => $params['id']]);
        if (!$preset) {
            throw new invalid_parameter_exception('Invalid preset');
        }

        $presetid = $preset->get('id');
        $preset->delete();

        preset_deleted::create([
            'context' => system::instance(),
            'other' => [
                'presetid' => $presetid,
                'presetname' => $preset->get('name'),
            ]
        ])->trigger();
    }

    /**
     * Returns description of method result value.
     */
    public static function execute_returns() {
        return null;
    }
}
