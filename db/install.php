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

/**
 * Install the plugin.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_enrolprofile\install_helper;
use tool_enrolprofile\helper;

/**
 * Perform the post-install procedures.
 */
function xmldb_tool_enrolprofile_install() {

    if (!PHPUNIT_TEST) {
        $categoryid = install_helper::add_cohort_custom_field_category();
        foreach (['type', 'id'] as $shortname) {
            install_helper::add_cohort_custom_field($categoryid, $shortname);
        }

        $profilefieldcategory = install_helper::add_profile_field_category();

        // Add autocomplete fields.
        $extras['categoryid'] = $profilefieldcategory->id;
        $profilefields = [
            helper::ITEM_TYPE_COURSE => 'Course',
            helper::ITEM_TYPE_CATEGORY => 'Category',
            helper::ITEM_TYPE_TAG => 'Tag',
        ];
        foreach ($profilefields as $shortname => $name) {
            install_helper::add_user_profile_field(
                $name,
                $shortname,
                'autocomplete',
                $extras
            );
        }

        // Add date time field.
        $extras = [];
        $extras['categoryid'] = $profilefieldcategory->id;
        $extras['param1'] = '2023'; // Min year.
        $extras['param2'] = '2050'; // Max year.
        $extras['sortorder'] = 3;

        install_helper::add_user_profile_field(
            'Keep enrolment until',
            helper::FIELD_ENROLLED_UNTIL,
            'datetime',
            $extras
        );
    }
}
