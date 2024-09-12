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

namespace tool_enrolprofile\task;

use core\task\adhoc_task;
use coding_exception;
use Exception;
use stdClass;
use tool_enrolprofile\helper;

/**
 * Add item adhoc task.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_item extends adhoc_task {

    /**
     * Task execution
     */
    public function execute() {
        global $DB;

        $course = null;
        $data = $this->get_custom_data();
        $this->validate_custom_data($data);

        $transaction = $DB->start_delegated_transaction();

        try {
            if (!empty($data->courseid)) {
                $course = get_course($data->courseid);
            }
            helper::add_item($data->itemid, $data->itemtype, $data->itemname, $course);
            $transaction->allow_commit();
        } catch (Exception $exception) {
            $transaction->rollback($exception);
        }
    }

    /**
     * Validates custom data.
     *
     * @param stdClass $data Custom data for a given task.
     * @return void
     */
    private function validate_custom_data(stdClass $data): void {
        $requiredfields = ['itemid', 'itemtype', 'itemname'];

        foreach ($requiredfields as $field) {
            if (empty($data->$field)) {
                throw new coding_exception('Missing required field: ' . $field);
            }
        }
    }
}
