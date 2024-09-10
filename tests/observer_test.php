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
use core\context\coursecat;
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
     * Tag profile field for testing.
     * @var \stdClass
     */
    protected $tagprofilefield;

    /**
     * Course profile field for testing.
     * @var \stdClass
     */
    protected $courseprofilefield;

    /**
     * Category profile field for testing.
     * @var \stdClass
     */
    protected $categoryprofilefield;

    /**
     * Set up before every test.
     *
     * @return void
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->tagprofilefield = $this->add_user_profile_field(helper::ITEM_TYPE_TAG, 'autocomplete');
        $this->courseprofilefield = $this->add_user_profile_field(helper::ITEM_TYPE_COURSE, 'autocomplete');
        $this->categoryprofilefield = $this->add_user_profile_field(helper::ITEM_TYPE_CATEGORY, 'autocomplete');

        $this->create_cohort_custom_field(helper::COHORT_FIELD_ID);
        $this->create_cohort_custom_field(helper::COHORT_FIELD_TYPE);
    }

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
     */
    public function test_tag_added(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $tagname = 'A tag';

        $this->assertEmpty($DB->get_record('cohort', ['name' => $tagname]));
        $this->assertEmpty($DB->get_field('user_info_field', 'param1', ['id' => $this->tagprofilefield->id]));
        $this->assertEmpty($DB->get_record('tag', ['rawname' => $tagname]));
        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['name' => $tagname]));

        // Should be already course and category cohorts.
        $this->assertCount(2, $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'cohort']));

        core_tag_tag::set_item_tags('core', 'course', $course->id, context_course::instance($course->id), [$tagname]);

        $tag = $DB->get_record('tag', ['rawname' => $tagname]);
        $this->assertNotEmpty($tag);

        $cohort = $DB->get_record('cohort', ['name' => $tagname]);
        $this->assertNotEmpty($cohort);

        $cohort = cohort_get_cohort($cohort->id, context_course::instance($course->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($tag->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame(helper::ITEM_TYPE_TAG, $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->tagprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($tagname, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $tagname]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);
        $this->assertEquals(1, $rule->enabled);
        $conditions = $DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]);
        $this->assertCount(2, $conditions);

        $this->assertCount(3, $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'cohort']));
        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertNotEmpty($enrol);
    }

    /**
     * Check logic when updating a tag.
     */
    public function test_tag_updated(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $profilefield = 'profile_field_' . helper::ITEM_TYPE_TAG;
        $tagname = 'A tag';

        core_tag_tag::set_item_tags('core', 'course', $course->id, context_course::instance($course->id), [
            $tagname,
            'Not tag',
            'Another tag',
        ]);

        $tag = $DB->get_record('tag', ['rawname' => $tagname]);
        $this->assertNotEmpty($tag);

        $cohort = $DB->get_record('cohort', ['name' => $tagname]);
        $this->assertNotEmpty($cohort);

        $cohort = cohort_get_cohort($cohort->id, context_course::instance($course->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($tag->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame(helper::ITEM_TYPE_TAG, $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->tagprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($tagname, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $tagname]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);

        $user1 = $this->getDataGenerator()->create_user();
        profile_save_data((object)[
            'id' => $user1->id,
            $profilefield => [
                'Not tag',
                $tagname,
                'Another tag',
            ]
        ]);

        $user2 = $this->getDataGenerator()->create_user();
        profile_save_data((object)[
            'id' => $user2->id,
            $profilefield => [
                $tagname,
                'Another tag',
            ]
        ]);

        profile_load_data($user1);
        $this->assertSame([
                'Not tag',
                $tagname,
                'Another tag',
            ], $user1->$profilefield
        );
        profile_load_data($user2);
        $this->assertSame([
                $tagname,
                'Another tag',
            ], $user2->$profilefield
        );

        // Update name of the tag.
        $newtagname = 'A new tag name';
        core_tag_tag::get($tag->id, '*')->update(array('rawname' => $newtagname));

        $tag = $DB->get_record('tag', ['rawname' => $tagname]);
        $this->assertEmpty($tag);

        $tag = $DB->get_record('tag', ['rawname' => $newtagname]);
        $this->assertNotEmpty($tag);

        $cohort = $DB->get_record('cohort', ['name' => $tagname]);
        $this->assertEmpty($cohort);

        $cohort = $DB->get_record('cohort', ['name' => $newtagname]);
        $this->assertNotEmpty($cohort);

        $cohort = cohort_get_cohort($cohort->id, context_course::instance($course->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($tag->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame(helper::ITEM_TYPE_TAG, $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->tagprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertFalse(in_array($tagname, explode("\n", $profilefielddata)));
        $this->assertTrue(in_array($newtagname, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $tagname]);
        $this->assertEmpty($rule);

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $newtagname]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);

        profile_load_data($user1);
        $this->assertSame([
                'Not tag',
                $newtagname,
                'Another tag',
            ], $user1->$profilefield
        );
        profile_load_data($user2);
        $this->assertSame([
                $newtagname,
                'Another tag',
            ], $user2->$profilefield
        );
    }

    /**
     * Check logic when removing a tag.
     */
    public function test_tag_removed(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $tagname = 'A tag';

        core_tag_tag::set_item_tags('core', 'course', $course->id, context_course::instance($course->id), [$tagname]);

        $cohort = $DB->get_record('cohort', ['name' => $tagname]);
        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertNotEmpty($enrol);

        core_tag_tag::remove_item_tag('core', 'course', $course->id, $tagname);

        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertEmpty($enrol);
    }

    /**
     * Check logic when deleting a tag.
     */
    public function test_tag_deleted(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $tagname = 'A tag';

        core_tag_tag::set_item_tags('core', 'course', $course->id, context_course::instance($course->id), [$tagname]);

        $cohort = $DB->get_record('cohort', ['name' => $tagname]);
        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertNotEmpty($enrol);

        $tag = $DB->get_record('tag', ['rawname' => $tagname]);
        $this->assertNotEmpty($tag);

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->tagprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($tagname, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $tagname]);
        $this->assertNotEmpty($rule);
        $conditions = $DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]);
        $this->assertCount(2, $conditions);

        core_tag_tag::delete_tags([$tag->id]);

        // Tag deleted.
        $this->assertEmpty($DB->get_record('tag', ['rawname' => $tagname]));
        // Cohort deleted.
        $this->assertEmpty($DB->get_record('cohort', ['name' => $tagname]));
        // Enrolment methods deleted.
        $this->assertEmpty($DB->get_records('enrol', ['enrol' => 'cohort', 'customint1' => $cohort->id]));
        // Rule and conditions deleted.
        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['name' => $tagname]));
        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['cohortid' => $cohort->id]));
        $this->assertEmpty($DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]));
        // Profile field data updated.
        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->tagprofilefield->id]);
        $this->assertTrue(!in_array($tagname, explode("\n", $profilefielddata)));
    }

    /**
     * Check logic when creating a course.
     */
    public function test_course_created(): void {
        global $DB;

        $coursename = 'Course name';
        $this->assertEmpty($DB->get_record('cohort', ['name' => $coursename]));
        $this->assertEmpty($DB->get_field('user_info_field', 'param1', ['id' => $this->courseprofilefield->id]));
        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['name' => $coursename]));

        $course = $this->getDataGenerator()->create_course(['fullname' => $coursename]);

        // Should be course and category cohorts.
        $this->assertCount(2, $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'cohort']));

        // Check everything about course cohort.
        $coursecohort = $DB->get_record('cohort', ['name' => $coursename]);
        $this->assertNotEmpty($coursecohort);

        $cohort = cohort_get_cohort($coursecohort->id, context_course::instance($course->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($course->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame(helper::ITEM_TYPE_COURSE, $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->courseprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($coursename, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $coursename]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);
        $this->assertEquals(1, $rule->enabled);
        $conditions = $DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]);
        $this->assertCount(2, $conditions);

        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertNotEmpty($enrol);

        // Check everything about category cohort.
        $category = $DB->get_record('course_categories', ['id' => $course->category]);
        $categorycohort = $DB->get_record('cohort', ['name' => $category->name]);
        $this->assertNotEmpty($categorycohort);

        $cohort = cohort_get_cohort($categorycohort->id, coursecat::instance($category->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($category->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame(helper::ITEM_TYPE_CATEGORY, $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->categoryprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($category->name, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $category->name]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);
        $this->assertEquals(1, $rule->enabled);
        $conditions = $DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]);
        $this->assertCount(2, $conditions);

        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertNotEmpty($enrol);
    }

    /**
     * Check logic when moving a course to a different category.
     */
    public function test_course_moved_to_different_category(): void {
        global $DB;

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category1->id]);

        // Should be course and category cohorts.
        $this->assertCount(2, $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'cohort']));

        // Check everything about category cohort.
        $categorycohort1 = $DB->get_record('cohort', ['name' => $category1->name]);
        $this->assertNotEmpty($categorycohort1);

        $categorycohort2 = $DB->get_record('cohort', ['name' => $category2->name]);
        $this->assertNotEmpty($categorycohort2);

        $enrol1 = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $categorycohort1->id]);
        $this->assertNotEmpty($enrol1);

        $enrol2 = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $categorycohort2->id]);
        $this->assertEmpty($enrol2);

        $course->category = $category2->id;
        update_course($course);

        $enrol1 = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $categorycohort1->id]);
        $this->assertEmpty($enrol1);

        $enrol2 = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $categorycohort2->id]);
        $this->assertNotEmpty($enrol2);
    }

    /**
     * Check logic when updating a name of the course.
     */
    public function test_course_name_updated(): void {
        global $DB;

        $coursename = 'Course name';
        $profilefield = 'profile_field_' . helper::ITEM_TYPE_COURSE;

        $course = $this->getDataGenerator()->create_course(['fullname' => $coursename]);
        $this->getDataGenerator()->create_course(['fullname' => 'Not course']);

        // Check everything about course cohort.
        $coursecohort = $DB->get_record('cohort', ['name' => $coursename]);
        $this->assertNotEmpty($coursecohort);

        $cohort = cohort_get_cohort($coursecohort->id, context_course::instance($course->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($course->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame(helper::ITEM_TYPE_COURSE, $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->courseprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($coursename, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $coursename]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);

        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertNotEmpty($enrol);

        $user1 = $this->getDataGenerator()->create_user();
        profile_save_data((object)[
            'id' => $user1->id,
            $profilefield => [
                'Not course',
                $coursename,
            ]
        ]);

        $user2 = $this->getDataGenerator()->create_user();
        profile_save_data((object)[
            'id' => $user2->id,
            $profilefield => [
                $coursename,
                'Not course',
            ]
        ]);

        profile_load_data($user1);
        $this->assertSame([
                'Not course',
                $coursename,
            ], $user1->$profilefield
        );
        profile_load_data($user2);
        $this->assertSame([
                $coursename,
                'Not course',
            ], $user2->$profilefield
        );

        // Update course full name.
        $newcoursename = 'New course name';
        $course->fullname = $newcoursename;
        update_course($course);

        $coursecohort = $DB->get_record('cohort', ['name' => $newcoursename]);
        $this->assertNotEmpty($coursecohort);
        $this->assertEmpty($DB->get_record('cohort', ['name' => $coursename]));

        $cohort = cohort_get_cohort($coursecohort->id, context_course::instance($course->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($course->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame(helper::ITEM_TYPE_COURSE, $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->courseprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($newcoursename, explode("\n", $profilefielddata)));
        $this->assertFalse(in_array($coursename, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $newcoursename]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);
        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['name' => $coursename]));

        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertNotEmpty($enrol);

        profile_load_data($user1);
        $this->assertSame([
            'Not course',
            $newcoursename,
        ], $user1->$profilefield
        );
        profile_load_data($user2);
        $this->assertSame([
            $newcoursename,
            'Not course',
        ], $user2->$profilefield
        );
    }

    /**
     * Check logic when deleting a course.
     */
    public function test_course_deleted(): void {
        global $DB;

        $coursename = 'Course name';
        $course = $this->getDataGenerator()->create_course(['fullname' => $coursename]);

        // Should be course and category cohorts.
        $this->assertCount(2, $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'cohort']));

        $coursecohort = $DB->get_record('cohort', ['name' => $coursename]);
        $this->assertNotEmpty($coursecohort);

        $cohort = cohort_get_cohort($coursecohort->id, context_course::instance($course->id), true);

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->courseprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($coursename, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $coursename]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);
        $this->assertEquals(1, $rule->enabled);
        $conditions = $DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]);
        $this->assertCount(2, $conditions);

        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertNotEmpty($enrol);

        delete_course($course->id, false);

        $coursecohort = $DB->get_record('cohort', ['name' => $coursename]);
        $this->assertEmpty($coursecohort);

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->courseprofilefield->id]);
        $this->assertFalse(in_array($coursename, explode("\n", $profilefielddata)));

        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['name' => $coursename]));
        $conditions = $DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]);
        $this->assertCount(0, $conditions);

        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertEmpty($enrol);
    }

    /**
     * Check logic when creating a course category.
     */
    public function test_course_category_created(): void {
        global $DB;

        $categoryname = 'Category name';
        $this->assertEmpty($DB->get_record('cohort', ['name' => $categoryname]));
        $this->assertEmpty($DB->get_field('user_info_field', 'param1', ['id' => $this->categoryprofilefield->id]));
        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['name' => $categoryname]));

        $category = $this->getDataGenerator()->create_category(['name' => $categoryname]);

        // Check everything about category cohort.
        $categorycohort = $DB->get_record('cohort', ['name' => $category->name]);
        $this->assertNotEmpty($categorycohort);

        $cohort = cohort_get_cohort($categorycohort->id, coursecat::instance($category->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($category->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame(helper::ITEM_TYPE_CATEGORY, $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->categoryprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($categoryname, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $categoryname]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);
        $this->assertEquals(1, $rule->enabled);
        $conditions = $DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]);
        $this->assertCount(2, $conditions);
    }

    /**
     * Check logic when updating a name of the course category.
     */
    public function test_course_category_name_updated(): void {
        global $DB;

        $categoryname = 'Category name';
        $profilefield = 'profile_field_' . helper::ITEM_TYPE_CATEGORY;

        $category = $this->getDataGenerator()->create_category(['name' => $categoryname]);
        $this->getDataGenerator()->create_category(['name' => 'Not category']);

        // Check everything about category cohort.
        $categorycohort = $DB->get_record('cohort', ['name' => $categoryname]);
        $this->assertNotEmpty($categorycohort);

        $cohort = cohort_get_cohort($categorycohort->id, \context_coursecat::instance($category->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($category->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame(helper::ITEM_TYPE_CATEGORY, $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->categoryprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($categoryname, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $categoryname]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);

        $user1 = $this->getDataGenerator()->create_user();
        profile_save_data((object)[
            'id' => $user1->id,
            $profilefield => [
                'Not category',
                $categoryname,
            ]
        ]);

        $user2 = $this->getDataGenerator()->create_user();
        profile_save_data((object)[
            'id' => $user2->id,
            $profilefield => [
                $categoryname,
                'Not category',
            ]
        ]);

        profile_load_data($user1);
        $this->assertSame([
                'Not category',
                $categoryname,
            ], $user1->$profilefield
        );
        profile_load_data($user2);
        $this->assertSame([
                $categoryname,
                'Not category',
            ], $user2->$profilefield
        );

        // Update category name.
        $newcategoryname = 'New category name';
        $categoryrecord = $DB->get_record('course_categories', ['id' => $category->id]);
        $categoryrecord->name = $newcategoryname;
        $category->update($categoryrecord);

        $categorycohort = $DB->get_record('cohort', ['name' => $newcategoryname]);
        $this->assertNotEmpty($categorycohort);
        $this->assertEmpty($DB->get_record('cohort', ['name' => $categoryname]));

        $cohort = cohort_get_cohort($categorycohort->id, \context_coursecat::instance($category->id), true);
        foreach ($cohort->customfields as $customfield) {
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_ID) {
                $this->assertSame($category->id, $customfield->export_value());
            }
            if ($customfield->get_field()->get('shortname') == helper::COHORT_FIELD_TYPE) {
                $this->assertSame(helper::ITEM_TYPE_CATEGORY, $customfield->export_value());
            }
        }

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->categoryprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($newcategoryname, explode("\n", $profilefielddata)));
        $this->assertFalse(in_array($categoryname, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $newcategoryname]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);
        $this->assertEmpty($DB->get_record('cohort', ['name' => $categoryname]));

        profile_load_data($user1);
        $this->assertSame([
                'Not category',
                $newcategoryname,
            ], $user1->$profilefield
        );
        profile_load_data($user2);
        $this->assertSame([
                $newcategoryname,
                'Not category',
            ], $user2->$profilefield
        );
    }

    /**
     * Check logic when deleting a course category.
     */
    public function test_course_category_deleted(): void {
        global $DB;

        $categoryname = 'Category name';
        $this->assertEmpty($DB->get_record('cohort', ['name' => $categoryname]));
        $this->assertEmpty($DB->get_field('user_info_field', 'param1', ['id' => $this->categoryprofilefield->id]));
        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['name' => $categoryname]));

        $category = $this->getDataGenerator()->create_category(['name' => $categoryname]);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);

        // Check everything about category cohort.
        $categorycohort = $DB->get_record('cohort', ['name' => $category->name]);
        $this->assertNotEmpty($categorycohort);

        $cohort = cohort_get_cohort($categorycohort->id, \context_coursecat::instance($category->id), true);

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->categoryprofilefield->id]);
        $this->assertNotEmpty($profilefielddata);
        $this->assertTrue(in_array($categoryname, explode("\n", $profilefielddata)));

        $rule = $DB->get_record('tool_dynamic_cohorts', ['name' => $categoryname]);
        $this->assertNotEmpty($rule);
        $this->assertEquals($cohort->id, $rule->cohortid);
        $this->assertEquals(1, $rule->enabled);
        $conditions = $DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]);
        $this->assertCount(2, $conditions);

        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertNotEmpty($enrol);

        $category->delete_full(false);

        $categorycohort = $DB->get_record('cohort', ['name' => $categoryname]);
        $this->assertEmpty($categorycohort);

        $profilefielddata = $DB->get_field('user_info_field', 'param1', ['id' => $this->categoryprofilefield->id]);
        $this->assertFalse(in_array($categoryname, explode("\n", $profilefielddata)));
        $this->assertEmpty($DB->get_record('tool_dynamic_cohorts', ['name' => $categoryname]));
        $this->assertEmpty($DB->get_records('tool_dynamic_cohorts_c', ['ruleid' => $rule->id]));

        $enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohort->id]);
        $this->assertEmpty($enrol);
    }
}
