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
}
