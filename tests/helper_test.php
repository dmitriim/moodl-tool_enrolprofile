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
use core\context\course;
use core_customfield\field_controller;
use core_tag_tag;

/**
 * Unit tests for helper class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \tool_enrolprofile\observer
 */
class helper_test extends advanced_testcase {

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
     * Test getting a cohort by item id.
     */
    public function test_get_cohort_by_item(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->create_cohort_custom_field(helper::COHORT_FIELD_ID);
        $this->create_cohort_custom_field(helper::COHORT_FIELD_TYPE);

        $typefieled = 'customfield_' . helper::COHORT_FIELD_TYPE;
        $idfieled = 'customfield_' . helper::COHORT_FIELD_ID;

        $cohort1 = self::getDataGenerator()->create_cohort([
            $typefieled => 'tag',
            $idfieled => 12,
        ]);

        $cohort2 = self::getDataGenerator()->create_cohort([
            $typefieled => 'tag',
            $idfieled => 13,
        ]);

        $cohort3 = self::getDataGenerator()->create_cohort([
            $typefieled => 'course',
            $idfieled => 13,
        ]);

        $this->assertNull(helper::get_cohort_by_item(1, 'tag'));
        $this->assertNull(helper::get_cohort_by_item(1, 'course'));

        $this->assertEquals($cohort1->id, helper::get_cohort_by_item(12, 'tag')->id);
        $this->assertEquals($cohort2->id, helper::get_cohort_by_item(13, 'tag')->id);
        $this->assertEquals($cohort3->id, helper::get_cohort_by_item(13, 'course')->id);
    }

    /**
     * Test getting courses by categories.
     */
    public function test_get_courses_by_categories() {
        $this->resetAfterTest();

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();
        $category3 = $this->getDataGenerator()->create_category();

        $course11 = $this->getDataGenerator()->create_course(['category' => $category1->id]);
        $course12 = $this->getDataGenerator()->create_course(['category' => $category1->id]);
        $course13 = $this->getDataGenerator()->create_course(['category' => $category1->id]);

        $course21 = $this->getDataGenerator()->create_course(['category' => $category2->id]);
        $course22 = $this->getDataGenerator()->create_course(['category' => $category2->id]);

        $courses = helper::get_courses_by_categories([$category1->id]);
        $this->assertCount(3, $courses);
        $this->assertArrayHasKey($course11->id, $courses);
        $this->assertArrayHasKey($course12->id, $courses);
        $this->assertArrayHasKey($course13->id, $courses);

        $courses = helper::get_courses_by_categories([$category2->id]);
        $this->assertCount(2, $courses);
        $this->assertArrayHasKey($course21->id, $courses);
        $this->assertArrayHasKey($course22->id, $courses);

        $courses = helper::get_courses_by_categories([$category3->id]);
        $this->assertCount(0, $courses);

        $courses = helper::get_courses_by_categories([$category2->id, $category1->id]);
        $this->assertCount(5, $courses);
        $this->assertArrayHasKey($course11->id, $courses);
        $this->assertArrayHasKey($course12->id, $courses);
        $this->assertArrayHasKey($course13->id, $courses);
        $this->assertArrayHasKey($course21->id, $courses);
        $this->assertArrayHasKey($course12->id, $courses);
    }

    /**
     * Test getting courses by tags.
     */
    public function test_get_courses_by_tags() {
        global $DB;

        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();
        $course4 = $this->getDataGenerator()->create_course();
        $course5 = $this->getDataGenerator()->create_course();

        core_tag_tag::set_item_tags('core', 'course', $course1->id, course::instance($course1->id), [
            'tag1',
            'tag2',
            'tag3',
            'tag4',
        ]);

        core_tag_tag::set_item_tags('core', 'course', $course2->id, course::instance($course2->id), [
            'tag2',
            'tag4',
        ]);

        core_tag_tag::set_item_tags('core', 'course', $course3->id, course::instance($course3->id), [
            'tag1',
            'tag2',
            'tag4',
        ]);

        core_tag_tag::set_item_tags('core', 'course', $course4->id, course::instance($course4->id), [
            'tag5',
        ]);

        $tag1 = $DB->get_record('tag', ['rawname' => 'tag1']);
        $tag2 = $DB->get_record('tag', ['rawname' => 'tag2']);
        $tag3 = $DB->get_record('tag', ['rawname' => 'tag3']);
        $tag4 = $DB->get_record('tag', ['rawname' => 'tag4']);
        $tag5 = $DB->get_record('tag', ['rawname' => 'tag5']);

        $courses = helper::get_courses_by_tags([77777]);
        $this->assertCount(0, $courses);

        $courses = helper::get_courses_by_tags([$tag1->id]);
        $this->assertCount(2, $courses);
        $this->assertArrayHasKey($course1->id, $courses);
        $this->assertArrayHasKey($course3->id, $courses);

        $courses = helper::get_courses_by_tags([$tag1->id, $tag2->id]);
        $this->assertCount(3, $courses);
        $this->assertArrayHasKey($course1->id, $courses);
        $this->assertArrayHasKey($course2->id, $courses);
        $this->assertArrayHasKey($course3->id, $courses);

        $courses = helper::get_courses_by_tags([$tag4->id, $tag5->id]);
        $this->assertCount(4, $courses);
        $this->assertArrayHasKey($course1->id, $courses);
        $this->assertArrayHasKey($course2->id, $courses);
        $this->assertArrayHasKey($course3->id, $courses);
        $this->assertArrayHasKey($course4->id, $courses);

        $courses = helper::get_courses_by_tags([$tag3->id]);
        $this->assertCount(1, $courses);
        $this->assertArrayHasKey($course1->id, $courses);
    }

    /**
     * Data provider for test_validate_task_custom_data.
     *
     * @return array[]
     */
    public function validate_task_custom_data_data_provider(): array {
        return [
            [['itemid' => 1, 'itemtype' => 'type'], ['itemid', 'itemtype'], ''],
            [['itemtype' => 'type'], ['itemid', 'itemtype'], 'Missing required field: itemid'],
            [['itemid' => null, 'itemtype' => 'type'], ['itemid', 'itemtype'], ''],
            [['itemid' => 0, 'itemtype' => 'type'], ['itemid', 'itemtype'], ''],
            [['itemid' => '', 'itemtype' => 'type'], ['itemid', 'itemtype'], ''],
            [['itemid' => ''], ['itemid', 'itemtype'], 'Missing required field: itemtype'],

        ];
    }

    /**
     * Test validate_task_custom_data.
     *
     * @dataProvider validate_task_custom_data_data_provider
     *
     * @param array $data Data to validate.
     * @param array $fields Fields to validate against
     * @param string $message Expected exception message. If empty, then no exception is expected,
     * @return void
     */
    public function test_validate_task_custom_data(array $data, array $fields, string $message = '') {
        if (!empty($message)) {
            $this->expectException(\coding_exception::class);
            $this->expectExceptionMessage($message);
        }

        helper::validate_task_custom_data((object)$data, $fields);
    }
}
