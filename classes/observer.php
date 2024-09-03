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

use core\event\tag_added;
use stdClass;
use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Event observer class.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.comt>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Process tag_added event.
     *
     * @param tag_added $event The event.
     */
    public static function tag_added(tag_added $event): void {
        // Check context is course context.
        $context = $event->get_context();
        if ($context->contextlevel != CONTEXT_COURSE && $event->other['itemtype'] != 'course') {
            return;
        }

        $tagid = $event->other['tagid'];
        $tagname = $event->other['tagrawname'];

        $courseid = $event->other['itemid'];
        $course = get_course($courseid);

        $cohort = new stdClass();
        $cohort->contextid = context_system::instance()->id;
        $cohort->name = $tagname;
        $cohort->idnumber = $tagname;
        $cohort->description = 'Tag related';
        $typefieled = 'customfield_' . helper::COHORT_FIELD_TYPE;
        $cohort->$typefieled = 'tag';
        $idfieled = 'customfield_' . helper::COHORT_FIELD_ID;
        $cohort->$idfieled = $tagid;

        // Check if cohort already exists.
        $existingcohort = helper::get_cohort_by_item($tagid, 'tag');

        // If not.
        if (empty($existingcohort)) {
            // Create a new cohort.
            $cohort->id = helper::add_cohort($cohort);
            // Create a dynamic cohort rule associated with this cohort.
            helper::add_rule($cohort, helper::FIELD_TAG);
            // Add a tag to a custom profile field.
            helper::update_profile_field(helper::FIELD_TAG, $tagname);
            // Create enrolment method for the cohort for a given course.
            helper::add_enrolment_method($course, $cohort);
        } else {
            // If yes, create enrolment method for the cohort for a given course.
            helper::add_enrolment_method($course, $existingcohort);
        }
    }
}
