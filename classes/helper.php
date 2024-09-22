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

namespace tool_enrolprofile;

use coding_exception;
use core\context\course;
use core\context\system;
use stdClass;
use tool_dynamic_cohorts\cohort_manager;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\rule_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Helper class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Tag item type.
     */
    public const ITEM_TYPE_TAG = 'tag';

    /**
     * Category item type.
     */
    public const ITEM_TYPE_CATEGORY = 'category';

    /**
     * Course item type.
     */
    public const ITEM_TYPE_COURSE = 'course';

    /**
     * Field shortname
     */
    public const FIELD_ENROLLED_UNTIL = 'enrolleduntil';

    /**
     * Course field name.
     */
    public const COURSE_NAME = 'fullname';

    /**
     * Field shortname
     */
    public const COHORT_FIELD_ID = 'id';

    /**
     * Field shortname
     */
    public const COHORT_FIELD_TYPE = 'type';

    /**
     * Role shortname.
     */
    public const STUDENT_ROLE = 'student';

    /**
     * Add a new configuration item.
     *
     * @param int $itemid Item ID number
     * @param string $itemtype Item type (tag, course, category).
     * @param string $itemname Item name.
     * @param stdClass|null $course Course to set up enrolment method. If not set, the no enrolment method will be created.
     * @return void
     */
    public static function add_item(int $itemid, string $itemtype, string $itemname, ?stdClass $course = null): void {
        $cohort = self::get_cohort_by_item($itemid, $itemtype);

        if (empty($cohort)) {
            $cohort = new stdClass();
            $cohort->contextid = system::instance()->id;
            $cohort->name = $itemname;
            $cohort->idnumber = $itemname;
            $cohort->description = ucfirst($itemtype) . ' related';
            $typefieled = 'customfield_' . self::COHORT_FIELD_TYPE;
            $cohort->$typefieled = $itemtype;
            $idfieled = 'customfield_' . self::COHORT_FIELD_ID;
            $cohort->$idfieled = $itemid;

            // Create a new cohort.
            $cohort->id = self::add_cohort($cohort);
        }

        // Create a dynamic cohort rule associated with this cohort.
        self::add_rule($cohort, $itemtype);
        // Add a tag to a custom profile field.
        self::add_profile_field_item($itemtype, $itemname);

        // Create enrolment method for the cohort for a given course.
        if (!empty($course)) {
            self::add_enrolment_method($course, $cohort);
        }
    }

    /**
     * Remove configuration item.
     *
     * @param int $itemid Item ID number
     * @param string $itemtype Item type (tag, course, category).
     * @param string $itemname Item name.
     * @return void
     */
    public static function remove_item(int $itemid, string $itemtype, string $itemname): void {
        global $DB;

        // Find cohort.
        $cohort = self::get_cohort_by_item($itemid, $itemtype);
        if ($cohort) {
            // Find all courses with this cohort and delete enrolment method.
            self::remove_enrolment_method($itemid, $itemtype);
            // Find a rule with this cohort and delete rule and conditions.
            $rules = $DB->get_records('tool_dynamic_cohorts', ['cohortid' => $cohort->id]);
            foreach ($rules as $rule) {
                $rule = rule::get_record(['id' => $rule->id]);
                rule_manager::delete_rule($rule);
            }
            // Delete cohort.
            cohort_delete_cohort($cohort);
            // Delete tag value from custom profile field list.
            self::delete_profile_field_item($itemtype, $itemname);
            // Clean up user data?
        }
    }

    /**
     * Rename item.
     *
     * @param int $itemid Item ID.
     * @param string $itemtype Item type (tag, course, category).
     * @param string $newname Item new name.
     * @return void
     */
    public static function rename_item(int $itemid, string $itemtype, string $newname): void {
        global $DB;

        // Get cohort to figure out an old name.
        $cohort = self::get_cohort_by_item($itemid, $itemtype);

        if ($cohort && $cohort->name != $newname) {
            $oldname = $cohort->name;

            // Update cohort with a new name.
            $cohort->name = $newname;
            unset($cohort->customfields);
            cohort_update_cohort($cohort);

            // Update rule with a new name.
            $rule = rule::get_record(['cohortid' => $cohort->id]);
            if ($rule) {
                $rule->set('name', $newname);
                $rule->save();
            }

            // Update custom profile field.
            self::update_profile_field_item($itemtype, $oldname, $newname);

            // Update users data. TODO: move to adhoc task.
            $field = $DB->get_record('user_info_field', ['shortname' => $itemtype]);

            if ($field) {
                $fieldselect = $DB->sql_like('data', ':value', false, false);
                $value = $DB->sql_like_escape($oldname);
                $params['value'] = "%$value%";
                $params['fieldid'] = $field->id;

                $where = "$fieldselect AND fieldid = :fieldid";
                $usersdata = $DB->get_records_select('user_info_data', $where, $params);

                foreach ($usersdata as $userdata) {
                    $data = explode(', ', $userdata->data);
                    $key = array_search($oldname, $data);
                    $data[$key] = $newname;
                    $userdata->data = implode(', ', $data);
                    $DB->update_record('user_info_data', $userdata);
                }
            }
        }
    }

    /**
     * Get cohort by provided item type and item id.
     *
     * @param int $itemid Item ID.
     * @param string $itemtype Item type.
     *
     * @return stdClass|null
     */
    public static function get_cohort_by_item(int $itemid, string $itemtype): ?stdClass {
        $systemcontext = system::instance();

        $allcohorts = cohort_get_cohorts($systemcontext->id, 0, 0, '', true);
        // Load custom fields data and filter bby custom field type and id.
        $cohorts = array_filter($allcohorts['cohorts'], function ($cohortdata) use ($itemid, $itemtype) {
            foreach ($cohortdata->customfields as $customfield) {
                $name = 'customfield_' . $customfield->get_field()->get('shortname');
                $cohortdata->$name = $customfield->export_value();
            }
            $typefieled = 'customfield_' . self::COHORT_FIELD_TYPE;
            $idfieled = 'customfield_' . self::COHORT_FIELD_ID;

            return $cohortdata->$typefieled == $itemtype && $cohortdata->$idfieled == $itemid;
        });

        if (!empty($cohorts)) {
            return reset($cohorts);
        } else {
            return null;
        }
    }

    /**
     * Helper method to add enrolment method to a course.
     *
     * @param stdClass $course Course.
     * @param stdClass $cohort Cohort.
     *
     * @return void
     */
    public static function add_enrolment_method(stdClass $course, stdClass $cohort): void {
        global $DB;

        $studentrole = self::get_student_role();

        $fields = [
            'enrol' => 'cohort',
            'customint1' => $cohort->id,
            'roleid' => $studentrole->id,
            'courseid' => $course->id,
        ];

        if (!$DB->record_exists('enrol', $fields)) {
            $enrol = enrol_get_plugin('cohort');
            $enrol->add_instance($course, $fields);
        }
    }

    /**
     * A helper  to remove enrolment method from a given course based on item details.
     *
     * @param int $itemid Item ID.
     * @param string $itemtype Item type (tag, course, category).
     * @param int $courseid Optional course ID.
     */
    public static function remove_enrolment_method(int $itemid, string $itemtype, int $courseid = 0): void {
        global $DB;

        $cohort = self::get_cohort_by_item($itemid, $itemtype);

        if ($cohort) {
            $studentrole = self::get_student_role();

            $fields = [
                'enrol' => 'cohort',
                'customint1' => $cohort->id,
                'roleid' => $studentrole->id,
            ];

            if (!empty($courseid)) {
                $fields['courseid'] = $courseid;
            }

            $instances = $DB->get_records('enrol', $fields);

            if ($instances) {
                $enrol = enrol_get_plugin('cohort');

                foreach ($instances as $instance) {
                    // Remove enrolment method.
                    $enrol->delete_instance($instance);
                }
            }
        }
    }

    /**
     * Update profile field with new item.
     *
     * @param string $shortname Field short name.
     * @param string $newitem A new item to add to the field.
     *
     * @return void
     */
    public static function add_profile_field_item(string $shortname, string $newitem): void {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);

        if ($field) {
            $fielddata = [];
            if (!empty($field->param1)) {
                $fielddata = explode("\n", $field->param1);
            }

            if (!in_array($newitem, $fielddata)) {
                $fielddata[] = $newitem;
                sort($fielddata);
                $field->param1 = implode("\n", $fielddata);
                $DB->update_record('user_info_field', $field);
            }
        }
    }

    /**
     * Delete a given item from profile field.
     *
     * @param string $shortname Field short name.
     * @param string $itemname A new item to add to the field.
     *
     * @return void
     */
    public static function delete_profile_field_item(string $shortname, string $itemname): void {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
        if ($field) {
            $fielddata = [];
            if (!empty($field->param1)) {
                $fielddata = explode("\n", $field->param1);
            }

            if (in_array($itemname, $fielddata)) {
                $key = array_search($itemname, $fielddata);
                unset($fielddata[$key]);
                sort($fielddata);
                $field->param1 = implode("\n", $fielddata);
                $DB->update_record('user_info_field', $field);
            }
        }
    }

    /**
     * Delete a given item from profile field.
     *
     * @param string $shortname Field short name.
     * @param string $oldname Old name of the item
     * @param string $newname New name of the item.
     *
     * @return void
     */
    public static function update_profile_field_item(string $shortname, string $oldname, string $newname): void {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
        if ($field) {
            $fielddata = [];
            if (!empty($field->param1)) {
                $fielddata = explode("\n", $field->param1);
            }

            if (in_array($oldname, $fielddata)) {
                $key = array_search($oldname, $fielddata);
                $fielddata[$key] = $newname;
                sort($fielddata);
                $field->param1 = implode("\n", $fielddata);
                $DB->update_record('user_info_field', $field);
            }
        }
    }

    /**
     * Add cohort.
     *
     * @param stdClass $cohort Cohort.
     *
     * @return int
     */
    public static function add_cohort(stdClass $cohort): int {
        global $DB;
        if (!$existingcohort = $DB->get_record('cohort', ['name' => $cohort->name])) {
            return cohort_add_cohort($cohort);
        } else {
            return $existingcohort->id;
        }
    }

    /**
     * A helper method to set up rule for given cohort.
     *
     * @param stdClass $cohort Cohort.
     * @param string $fieldshortname Related profile field shortname.
     *
     * @return void
     */
    public static function add_rule(stdClass $cohort, string $fieldshortname): void {
        if (rule::get_record(['cohortid' => $cohort->id])) {
            return;
        }

        cohort_manager::manage_cohort($cohort->id);
        $rule = new rule(0, (object)[
            'name' => $cohort->name,
            'cohortid' => $cohort->id,
            'description' => $cohort->description,
        ]);
        $rule->save();

        $condition = condition_base::get_instance(0, (object)[
            'classname' => 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_custom_profile',
        ]);

        $fieldname = 'profile_field_' . $fieldshortname;
        $condition->set_config_data([
            'profilefield' => $fieldname,
            $fieldname . '_operator' => condition_base::TEXT_IS_EQUAL_TO,
            $fieldname . '_value' => $cohort->name,
        ]);
        $condition->get_record()->set('ruleid', $rule->get('id'));
        $condition->get_record()->set('sortorder', 0);
        $condition->get_record()->save();

        $condition = condition_base::get_instance(0, (object)[
            'classname' => 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_custom_profile',
        ]);

        $fieldname = 'profile_field_' . self::FIELD_ENROLLED_UNTIL;
        $condition->set_config_data([
            'profilefield' => $fieldname,
            $fieldname . '_operator' => condition_base::DATE_IN_THE_FUTURE,
            $fieldname . '_value' => 0,
        ]);
        $condition->get_record()->set('ruleid', $rule->get('id'));
        $condition->get_record()->set('sortorder', 0);
        $condition->get_record()->save();

        $rule->set('enabled', 1);
        $rule->save();
    }

    /**
     * Returns a student role.
     *
     * @return stdClass
     */
    public static function get_student_role(): stdClass {
        global $DB;

        return $DB->get_record('role', ['shortname' => self::STUDENT_ROLE]);
    }

    /**
     * Processing course category change for a given course.
     *
     * @param int $courseid Given course ID
     * @param int $newcategoryid ID of a new category.
     * @return void
     */
    public static function update_course_category(int $courseid, int $newcategoryid): void {
        global $DB;

        $studentrole = self::get_student_role();

        $params = [
            'enrol' => 'cohort',
            'roleid' => $studentrole->id,
            'courseid' => $courseid,
        ];

        $enrolments = $DB->get_records('enrol', $params);
        foreach ($enrolments as $enrolment) {
            $cohortid = $enrolment->customint1;
            $cohort = cohort_get_cohort($cohortid, course::instance($courseid), true);
            $oldcategoryidid = $type = null;

            foreach ($cohort->customfields as $customfield) {
                if ($customfield->get_field()->get('shortname') == self::COHORT_FIELD_ID) {
                    $oldcategoryidid = $customfield->export_value();
                }
                if ($customfield->get_field()->get('shortname') == self::COHORT_FIELD_TYPE) {
                    $type = $customfield->export_value();
                }
            }

            if ($type == self::ITEM_TYPE_CATEGORY && !empty($oldcategoryidid)) {
                self::remove_enrolment_method($oldcategoryidid, self::ITEM_TYPE_CATEGORY, $courseid);
            }
        }

        $cohort = self::get_cohort_by_item($newcategoryid, self::ITEM_TYPE_CATEGORY);
        if ($cohort) {
            $course = get_course($courseid);
            self::add_enrolment_method($course, $cohort);
        }
    }

    /**
     * Validates custom data.
     *
     * @param stdClass $data Custom data for a given task.
     * @param array $fields A list of required fields.
     * @return void
     */
    public static function validate_task_custom_data(stdClass $data, array $fields = ['itemid', 'itemtype', 'itemname']): void {
        foreach ($fields as $field) {
            if (empty($data->$field)) {
                throw new coding_exception('Missing required field: ' . $field);
            }
        }
    }

    /**
     * Gets a list of tags related to courses.
     *
     * @param int $courseid Optional to filter by course ID.
     * @return array
     */
    public static function get_course_tags(int $courseid = 0): array {
        global $DB;

        $params = [];
        $where = '';
        if (!empty($courseid)) {
            $where = " AND ti.itemid = ?";
            $params[] = $courseid;
        }

        $sql = "SELECT DISTINCT t.id, t.rawname
              FROM {tag} t
              JOIN {tag_instance} ti ON t.id = ti.tagid
             WHERE ti.itemtype = 'course' $where ORDER BY t.id, t.rawname";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns a list of courses.
     *
     * @return array
     */
    public static function get_courses(): array {
        global $DB;

        return $DB->get_records('course', ['visible' => 1], 'fullname');
    }

    /**
     * Get categories.
     *
     * @return array
     */
    public static function get_categories(): array {
        global $DB;

        return $DB->get_records('course_categories', ['visible' => 1], 'name');
    }
}
