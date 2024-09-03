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

use advanced_testcase;
use core_customfield\field_controller;
use core_tag_tag;
use context_course;

/**
 * Unit tests for observer class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.comt>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_enrolprofile\observer
 */
class observer_test extends advanced_testcase {

    /**
     * A helper function to create a custom profile field.
     *
     * @param string $shortname Short name of the field.
     * @param string $datatype Type of the field, e.g. text, checkbox, datetime, menu and etc.
     * @param array $extras A list of extra fields for the field (e.g. forceunique, param1 and etc)
     *
     * @return \stdClass
     */
    protected function add_user_profile_field(string $shortname, string $datatype, array $extras = []): \stdClass {
        global $DB;

        $data = new \stdClass();
        $data->shortname = $shortname;
        $data->datatype = $datatype;
        $data->name = 'Test ' . $shortname;
        $data->description = 'This is a test field';
        $data->required = false;
        $data->locked = false;
        $data->forceunique = false;
        $data->signup = false;
        $data->visible = '0';
        $data->categoryid = '0';

        foreach ($extras as $name => $value) {
            $data->{$name} = $value;
        }

        $data->id = $DB->insert_record('user_info_field', $data);

        return $data;
    }

    /**
     * Create cohort custom field for testing.
     *
     * @param string $shortname Field shortname
     * @param string $datatype $field data type.
     *
     * @return field_controller
     */
    protected function create_cohort_custom_field(string $shortname, string $datatype = 'text'): field_controller {
        $fieldcategory = self::getDataGenerator()->create_custom_field_category([
            'component' => 'core_cohort',
            'area' => 'cohort',
            'name' => 'Other fields',
        ]);

        return self::getDataGenerator()->create_custom_field([
            'shortname' => $shortname,
            'name' => 'Custom field ' . $shortname,
            'type' => $datatype,
            'categoryid' => $fieldcategory->get('id'),
        ]);
    }

    /**
     * Check logic when adding a tag.
     * @return void
     */
    public function test_tag_added() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $prodilefield = $this->add_user_profile_field(helper::FIELD_TAG, 'autocomplete');
        $this->create_cohort_custom_field(helper::COHORT_FIELD_ID);
        $this->create_cohort_custom_field(helper::COHORT_FIELD_TYPE);
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $tagname = 'A tag';

        $this->assertEmpty($DB->get_record('cohort', ['name' => $tagname]));
        $this->assertEmpty($DB->get_field('user_info_field', 'param1', ['id' => $prodilefield->id]));
        $this->assertEmpty($DB->get_record('tag', ['rawname' => $tagname]));
        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['name' => $tagname]));
        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['name' => $tagname]));
        $this->assertCount(0, $DB->get_records('enrol', ['courseid' => $course1->id, 'enrol' => 'cohort']));

        core_tag_tag::set_item_tags('core', 'course', $course1->id, context_course::instance($course1->id), [$tagname]);

        $tag = $DB->get_record('tag', ['rawname' => $tagname]);
        $this->assertNotEmpty($tag);

        $cohort = $DB->get_record('cohort', ['name' => $tagname]);
        $this->assertNotEmpty($cohort);

        $cohort = cohort_get_cohort($cohort->id, context_course::instance($course1->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($tag->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame('tag', $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $prodilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($tagname, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $tagname]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);
        $this->assertEquals(1, $rule->enabled);
        $conditions = $DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]);
        $this->assertCount(2, $conditions);

        $this->assertCount(1, $DB->get_records('enrol', ['courseid' => $course1->id, 'enrol' => 'cohort']));
        $enrol = $DB->get_record('enrol', ['courseid' => $course1->id, 'enrol' => 'cohort']);
        $this->assertNotEmpty($enrol);
        $this->assertEquals($cohort->id, $enrol->customint1);

        core_tag_tag::set_item_tags('core', 'course', $course2->id, \context_course::instance($course1->id), [$tagname]);
    }
}
