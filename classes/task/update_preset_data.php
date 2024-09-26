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
use stdClass;

/**
 * Update preset adhoc task.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_preset_data extends adhoc_task {

    /**
     * Cohort bases on preset.
     *
     * @var stdClass
     */
    private $cohort;

    /**
     * A list of courses to add cohort enrolment method to.
     * @var array
     */
    private $coursesadd = [];

    /**
     * A list of courses to remove cohort enrolment method from.
     * @var array
     */
    private $coursesremove = [];

    /**
     * Task execution
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $this->validate_custom_data($data);

        $transaction = $DB->start_delegated_transaction();

        try {
            $this->cohort = helper::get_cohort_by_item($data->itemid, $data->itemtype);

            if (empty($this->cohort)) {
                throw new \moodle_exception('Cohort not found for item type ' . $data->itemtype
                    . ' id ' . $data->itemid);
            }

            $this->process_categories($data);
            $this->process_courses($data);
            $this->process_tags($data);

            $this->update_enrolments($data->itemid, $data->itemtype);

            $transaction->allow_commit();
        } catch (Exception $exception) {
            $transaction->rollback($exception);
        }
    }

    /**
     * Validate data.
     *
     * @param stdClass $data Task data,
     * @return void
     */
    private function validate_custom_data(stdClass $data): void {
        helper::validate_task_custom_data(
            $data,
            [
                'itemid', 'itemtype', 'itemname', 'categories', 'oldcategories',
                'courses', 'oldcourses', 'tags', 'oldtags'
            ]
        );
    }

    /**
     * Process categories items.
     *
     * @param stdClass $data Categories data.
     */
    private function process_categories(stdClass $data): void {
        $categories = $this->explode_data($data->categories);
        $oldcategories = $this->explode_data($data->oldcategories);

        $this->update_course_lists($categories, $oldcategories, 'get_courses_by_categories');
    }

    /**
     * Process courses items.
     *
     * @param stdClass $data Courses data.
     */
    private function process_courses(stdClass $data): void {
        $courses = $this->explode_data($data->courses);
        $oldcourses = $this->explode_data($data->oldcourses);

        $this->update_course_lists($courses, $oldcourses);
    }

    /**
     * Process tag items.
     *
     * @param \stdClass $data Tags data.
     */
    private function process_tags(stdClass $data): void {
        $tags = $this->explode_data($data->tags);
        $oldtags = $this->explode_data($data->oldtags);

        $this->update_course_lists($tags, $oldtags, 'get_courses_by_tags');
    }

    /**
     * A tiny helper method to convert list of items from string to array.
     *
     * @param string $data
     * @return array
     */
    private function explode_data(string $data): array {
        return !empty($data) ? explode(',', $data) : [];
    }

    /**
     * Update list of courses based on new and old items.
     *
     * @param array $newitems A list of new items.
     * @param array $olditems A list of old items.
     * @param string|null $helpermethod A helper method to call to get courses based on items ids.
     */
    private function update_course_lists(array $newitems, array $olditems, string $helpermethod = null): void {
        $removeditems = array_diff($olditems, $newitems);
        $addeditems = array_diff($newitems, $olditems);

        if (!empty($removeditems)) {
            $removedcourses = $helpermethod ? array_keys(helper::$helpermethod($removeditems)) : $removeditems;
            $this->coursesremove = array_unique(array_merge($this->coursesremove, $removedcourses));
        }

        if (!empty($addeditems)) {
            $addedcourses = $helpermethod ? array_keys(helper::$helpermethod($addeditems)) : $addeditems;
            $this->coursesadd = array_unique(array_merge($this->coursesadd, $addedcourses));
        }
    }

    /**
     * Update enrolment methods.
     *
     * @param int $itemid Item ID
     * @param string $itemtype Item type.
     */
    private function update_enrolments(int $itemid, string $itemtype): void {
        foreach ($this->coursesremove as $courseid) {
            helper::remove_enrolment_method($itemid, $itemtype, $courseid);
        }

        foreach ($this->coursesadd as $courseid) {
            $course = get_course($courseid);
            helper::add_enrolment_method($course, $this->cohort);
        }
    }
}
