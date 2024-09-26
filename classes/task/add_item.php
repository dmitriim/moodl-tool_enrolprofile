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
use Exception;
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

        $courses = [];
        $data = $this->get_custom_data();
        helper::validate_task_custom_data($data);

        $transaction = $DB->start_delegated_transaction();

        try {
            // Covert to a new format if there is existing task with old format of data.
            if (!empty($data->courseid)) {
                $data->courseids = [$data->courseid];
            }

            if (!empty($data->courseids) && is_array($data->courseids)) {
                list($sql, $params) = $DB->get_in_or_equal($data->courseids, SQL_PARAMS_NAMED);
                $select = 'id ' . $sql;
                $courses = $DB->get_records_select('course', $select, $params);
            }

            helper::add_item($data->itemid, $data->itemtype, $data->itemname, $courses);
            $transaction->allow_commit();
        } catch (Exception $exception) {
            $transaction->rollback($exception);
        }
    }
}
