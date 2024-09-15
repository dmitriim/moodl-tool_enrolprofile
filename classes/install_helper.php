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

use stdClass;
use core_customfield\field_controller;
use core_customfield\category_controller;
use core_customfield\handler;
use tool_dynamic_cohorts\cohort_manager;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\rule;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/profile/definelib.php');

/**
 * Install helper class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class install_helper {

    /**
     * A helper function to add custom profile field.
     *
     * @param string $name Field name.
     * @param string $shortname Field shortname.
     * @param string $datatype Field data type.
     * @param array $extras Extra data.
     *
     * @return int ID of the record.
     */
    public static function add_user_profile_field(string $name, string $shortname, string $datatype, array $extras = []): int {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);

        if (empty($field)) {
            $data = new \stdClass();
            $data->shortname = $shortname;
            $data->datatype = $datatype;
            $data->name = $name;
            $data->required = false;
            $data->locked = false;
            $data->forceunique = false;
            $data->signup = false;
            $data->visible = '0';
            $data->categoryid = 1;

            foreach ($extras as $name => $value) {
                $data->{$name} = $value;
            }

            return $DB->insert_record('user_info_field', $data);
        }

        return $field->id;
    }

    /**
     * Created profile fields category.
     *
     * @return stdClass
     */
    public static function add_profile_field_category(): stdClass {
        global $DB;

        $profilefieldcategory = $DB->get_record('user_info_category', ['name' => 'Profile field']);
        if (empty($profilefieldcategory)) {
            $profilefieldcategory = new stdClass();
            $profilefieldcategory->name = 'Profile field';
            $profilefieldcategory->id = $DB->insert_record('user_info_category', $profilefieldcategory);
        }

        return $profilefieldcategory;
    }

    /**
     * Create a custom fields category.
     *
     * @return int
     */
    public static function add_cohort_custom_field_category() {
        $handler = handler::get_handler('core_cohort', 'cohort', 0);
        $categories = $handler->get_categories_with_fields();

        foreach ($categories as $category) {
            if ($category->get('name') == 'Metadata') {
                return $category->get('id');
            }
        }

        return $handler->create_category('Metadata');
    }

    /**
     * Add cohort custom field.
     *
     * @param int $categoryid Custom field category id.
     * @param string $shortname Shor name of the field.
     * @param string $type Field type.
     * @param array $configdata Config data for a field.
     * @return void
     */
    public static function add_cohort_custom_field(int $categoryid, string $shortname, string $type = 'text', array $configdata = []): void {
        $category = category_controller::create($categoryid);
        $handler = $category->get_handler();

        foreach ($handler->get_fields() as $field) {
            // Field already exists.
            if ($field->get('shortname') === $shortname) {
                return;
            }
        }

        $record = new stdClass();
        $record->categoryid = $categoryid;
        $record->name = ucfirst($shortname);
        $record->shortname = $shortname;
        $record->type = $type;

        $configdata += [
            'required' => 0,
            'uniquevalues' => 0,
            'locked' => 0,
            'visibility' => 2,
            'defaultvalue' => '',
            'defaultvalueformat' => FORMAT_MOODLE,
            'displaysize' => 0,
            'maxlength' => 0,
            'ispassword' => 0,
        ];

        $record->configdata = json_encode($configdata);
        $field = field_controller::create(0, (object)['type' => $record->type], $category);
        $handler->save_field_configuration($field, $record);
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

    /**
     * Clean up cohort enrolments.
     */
    public static function delete_enrolments(): void {
        global $DB;

        $instances = $DB->get_records('enrol', ['enrol' => 'cohort']);

        foreach ($instances as $instance) {
            $cohortplugin = enrol_get_plugin('cohort');
            $cohortplugin->delete_instance($instance);
        }
    }

    /**
     * Clean up rules and conditions.
     */
    public static function delete_rules_and_conditions(): void {
        global $DB;

        $DB->execute("UPDATE {cohort} SET component = '' WHERE component = :component", [
            'component' => 'tool_dynamic_cohorts',
        ]);
        $DB->delete_records('tool_dynamic_cohorts_c');
        $DB->delete_records('tool_dynamic_cohorts');
    }

    /**
     * Delete cohorts.
     */
    public static function delete_cohorts(): void {
        foreach (cohort_get_all_cohorts(0, 0)['cohorts'] as $cohort) {
            cohort_delete_cohort($cohort);
        }
    }

    /**
     * Delete custom profile fields.
     */
    public static function delete_profile_custom_fields(): void {
        global $DB;

        $shortnames = ['category', 'course', 'tag', 'enrolleduntil'];

        foreach ($shortnames as $shortname) {
            $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
            if ($field) {
                profile_delete_field($field->id);
            }
        }
    }

    /**
     * Add cohort.
     *
     * @param stdClass $cohort Cohort.
     * @param array $options Pass in CLI options.
     *
     * @return void
     */
    public static function set_up_add_cohort(stdClass $cohort, array $options): void {
        global $DB;
        if (!$existingcohort = $DB->get_record('cohort', ['name' => $cohort->name])) {
            if ($options['run']) {
                cohort_add_cohort($cohort);
                cli_writeln("Created cohort '$cohort->name'.");
            } else {
                cli_writeln("Will create cohort '$cohort->name'.");
            }
        } else {
            cli_writeln("Cohort '$cohort->name' already exists. Skipping.");
            $cohort->id = $existingcohort->id;
        }
    }

    /**
     * A helper method to set up rule for given cohort.
     *
     * @param stdClass $cohort Cohort.
     * @param string $fieldshortname Related profile field shortname.
     * @param array $options Pass in CLI options.
     *
     * @return void
     */
    public static function add_rule(stdClass $cohort, string $fieldshortname, array $options): void {
        if ($options['run']) {
            if (!rule::get_record(['cohortid' => $cohort->id])) {
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

                $fieldname = 'profile_field_' . helper::FIELD_ENROLLED_UNTIL;
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

                cli_writeln("Created rule for cohort '$cohort->name'.");
            } else {
                cli_writeln("Rule for $cohort->id already exists. Skipping.");
            }
        } else {
            cli_writeln("Will create rule for cohort '$cohort->name'.");
        }
    }

    /**
     * Helper method to add enrolment method to a course.
     *
     * @param stdClass $course Course.
     * @param stdClass $cohort Cohort.
     * @param array $options Pass in CLI options.
     *
     * @return void
     */
    public static function add_enrolment_method(stdClass $course, stdClass $cohort, array $options): void {
        global $DB;

        $studentrole = $DB->get_record('role', ['shortname' => helper::STUDENT_ROLE]);

        $fields = [
            'customint1' => $cohort->id,
            'roleid' => $studentrole->id,
            'courseid' => $course->id,
        ];

        if (!$DB->record_exists('enrol', $fields)) {
            if ($options['run']) {
                $enrol = enrol_get_plugin('cohort');
                $enrol->add_instance($course, $fields);
                cli_writeln("Added enrolment method for cohort ID $cohort->id to course ID $course->id");
            } else {
                cli_writeln("Will add enrolment method for cohort ID $cohort->id to course ID $course->id");
            }
        } else {
            cli_writeln("Course ID $course->id already have enrolment method for cohort ID $cohort->id. Skipping.");
        }
    }

    /**
     * Delete all cohorts custom fields.
     * @return void
     */
    public static function delete_cohort_custom_fields(): void {
        $handler = \core_customfield\handler::get_handler('core_cohort', 'cohort', 0);

        $categories = $handler->get_categories_with_fields();
        foreach ($categories as $category) {
            if ($category->get('name') == 'Metadata') {
                $handler->delete_category($category);
            }
        }
    }
}
