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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');

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
}
