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

use context_system;
use stdClass;
use tool_dynamic_cohorts\cohort_manager;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\rule;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Helper class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.comt>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Profile field category.
     */
    public const PROFILE_CATEGORY = 'Profile field';

    /**
     * Field shortname
     */
    public const FIELD_TAG = 'tag';

    /**
     * Field shortname
     */
    public const FIELD_CATEGORY = 'category';

    /**
     * Field shortname
     */
    public const FIELD_COURSE = 'course';

    /**
     * Field shortname
     */
    public const FIELD_ENROLLED_UNTIL = 'enrolleduntil';

    /**
     * Course fiel name.
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
     * Get cohort by provided item type and item id.
     *
     * @param int $itemid Item ID.
     * @param string $itemtype Item type.
     *
     * @return stdClass|null
     */
    public static function get_cohort_by_item(int $itemid, string $itemtype): ?stdClass {
        $systemcontext = context_system::instance();

        $allcohorts = cohort_get_cohorts($systemcontext->id, 0, 0, '', true);
        // Load custom fields data and filter bby custom field type and id.
        $cohorts = array_filter($allcohorts['cohorts'], function ($cohortdata) use ($itemid, $itemtype) {
            foreach ($cohortdata->customfields as $customfield) {
                $name = 'customfield_' . $customfield->get_field()->get('shortname');
                $cohortdata->$name = $customfield->export_value();
            }
            $typefieled = 'customfield_' . helper::COHORT_FIELD_TYPE;
            $idfieled = 'customfield_' . helper::COHORT_FIELD_ID;

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

        $studentrole = $DB->get_record('role', ['shortname' => self::STUDENT_ROLE]);

        $fields = [
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
     * Update profile field with new item.
     *
     * @param string $shortname Field short name.
     * @param string $newitem A new item to add to the field.
     *
     * @return void
     */
    public static function update_profile_field(string $shortname, string $newitem): void {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
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
}
