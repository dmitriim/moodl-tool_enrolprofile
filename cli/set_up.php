<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI script to initial set up enrolment configuration.
 *
 * Assumptions:
 *  - Moodle version is 4.4
 *  - tool_dynamic_cohorts is installed
 *  - profile_autocomplete is installed
 *  - there is only on level of categories
 *  - this script can only be run once.
 *
 * This script creates custom field category and custom fields to store courses,
 * categories, tags and unenrolment date.
 * It also creates cohort custom fields "type" and "id" to save metadata information
 * for cohorts.
 * Then it goes through all categories, courses and tags and creates related cohorts. It
 * populates type and id custom fields for each cohort to be able to identify entities
 * cohorts are related to. E.g. if 'type' is course and 'id' is 5, it means that the given cohort
 * created based on a course with id 5.
 * For each cohort it creates a rule for dynamic cohorts plugin so users with matching
 * related fields can be added to cohort. Then it goes through all courses and
 * adds cohort sync enrolment instances: one for course cohort, one for category cohort
 * and if a course have tags, then one for each tag related cohort.
 *
 * Then, once a user is created/updated, based on data in custom fields he would be
 * added to one of cohorts which will enroll the user to all related courses.
 *
 * The script has few options. Please run it with --help to see them all.
 *
 * @package    tool_enrolprofile
 * @copyright  2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_enrolprofile\install_helper;
use tool_enrolprofile\helper;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');

$help = "Command line tool to set up custom enrolment functionality.
The script will clean up before execution.

Options:
    -h --help      Print this help.
    --run          Execute Set up. If this option is not set, then the script will be run in a dry mode.
    --skipcleanup  Skips cleaning up.
    --onlycleanup  Only cleans up: deletes all cohort enrolments, all cohorts, all conditions and rules
                   as well as custom profile fields.

Usage:
    # php set_up.php  --run
";

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'run' => false,
    'skipcleanup' => false,
    'onlycleanup' => false,
], [
    'h' => 'help'
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($help);
    exit(0);
}


// Need admin permissions to use custom fields APIs.
\core\cron::setup_user();

// We want to rollback if anything exploded.
$transaction = $DB->start_delegated_transaction();

try {
    if (!$options['skipcleanup']) {
        // Cleaning up stuff.
        if ($options['run']) {
            install_helper::delete_enrolments();
            cli_writeln("Deleted all cohort enrolments");
        } else {
            cli_writeln("Will deleted all cohort enrolments");
        }

        if ($options['run']) {
            install_helper::delete_rules_and_conditions();
            cli_writeln("Deleted all dynamic cohorts rules and conditions");
        } else {
            cli_writeln("Will delete all dynamic cohorts rules and conditions");
        }

        if ($options['run']) {
            install_helper::delete_cohorts();
            cli_writeln("Deleted all cohorts");
        } else {
            cli_writeln("Will delete all cohorts");
        }

        if ($options['run']) {
            install_helper::delete_cohort_custom_fields();
            cli_writeln("Deleted all cohort custom fields");
        } else {
            cli_writeln("Will delete all cohort custom fields");
        }

        if ($options['run']) {
            install_helper::delete_profile_custom_fields();
            cli_writeln("Deleted required custom profile fields");
        } else {
            cli_writeln("Will delete required custom profile fields");
        }
    }

    if (!$options['onlycleanup']) {
        // Create cohort custom fields.
        $categoryid = install_helper::add_cohort_custom_field_category();
        foreach (['type', 'id'] as $shortname) {
            if ($options['run']) {
                install_helper::add_cohort_custom_field($categoryid, $shortname);
                cli_writeln("Added cohort custom field '$shortname'");
            } else {
                cli_writeln("Will add cohort custom field '$shortname'");
            }
        }

        // Create custom profile fields category.
        $profilefieldcategory = $DB->get_record('user_info_category', ['name' => 'Profile field']);
        if (empty($profilefieldcategory)) {
            if ($options['run']) {
                $profilefieldcategory = install_helper::add_profile_field_category();
                cli_writeln("Created profile field category with name '{$profilefieldcategory->name}'");
            } else {
                cli_writeln("Will create profile field category with name '{$profilefieldcategory->name}'");
            }
        } else {
            cli_writeln("Profile field category with name '{$profilefieldcategory->name}' already exists. Skipping.");
        }

        // Create autocomplete user profile field Course.
        $coursefield = $DB->get_record('user_info_field', ['shortname' => helper::ITEM_TYPE_COURSE]);
        if (empty($coursefield)) {
            if ($options['run']) {
                // Fill the field options with a list of courses.
                $param1 = [];
                foreach (install_helper::get_courses() as $course) {
                    if ($course->id == SITEID) {
                        continue;
                    }
                    $param1[$course->fullname] = $course->fullname;
                }
                $extras = [];
                $extras['categoryid'] = $profilefieldcategory->id;
                $extras['param1'] = implode("\n", $param1);
                $extras['param2'] = 1; // Enable multi-selection.
                $extras['sortorder'] = 1;
                install_helper::add_user_profile_field('Course', helper::ITEM_TYPE_COURSE, 'autocomplete', $extras);
                cli_writeln("Created profile field with shortname '" . helper::ITEM_TYPE_COURSE . "'");
            } else {
                cli_writeln("Will create profile field with shortname '" . helper::ITEM_TYPE_COURSE . "'");
            }
        } else {
            cli_writeln("Profile field with shortname '" . helper::ITEM_TYPE_COURSE . "' already exists. Skipping.");
        }

        // Create autocomplete user profile field Category.
        $categoryfield = $DB->get_record('user_info_field', ['shortname' => helper::ITEM_TYPE_CATEGORY]);
        if (empty($categoryfield)) {
            if ($options['run']) {
                // Fill the field options with a list of categories.
                $param1 = [];
                foreach (install_helper::get_categories() as $category) {
                    $param1[$category->name] = $category->name;
                }
                $extras = [];
                $extras['categoryid'] = $profilefieldcategory->id;
                $extras['param1'] = implode("\n", $param1);
                $extras['param2'] = 1; // Enable multi-selection.
                $extras['sortorder'] = 0;
                install_helper::add_user_profile_field('Category', helper::ITEM_TYPE_CATEGORY, 'autocomplete', $extras);
                cli_writeln("Created profile field with shortname '" . helper::ITEM_TYPE_CATEGORY . "'");
            } else {
                cli_writeln("Will create profile field with shortname '" . helper::ITEM_TYPE_CATEGORY . "'");
            }
        } else {
            cli_writeln("Profile field with shortname '" . helper::ITEM_TYPE_CATEGORY . "' already exists. Skipping.");
        }

        // Create date user profile field active until.
        $enrolleduntilfield = $DB->get_record('user_info_field', ['shortname' => helper::FIELD_ENROLLED_UNTIL]);
        if (empty($enrolleduntilfield)) {
            if ($options['run']) {
                $extras = [];
                $extras['categoryid'] = $profilefieldcategory->id;
                $extras['param1'] = '2023'; // Min year.
                $extras['param2'] = '2050'; // Max year.
                $extras['sortorder'] = 3;
                install_helper::add_user_profile_field('Keep enrolment until', helper::FIELD_ENROLLED_UNTIL, 'datetime', $extras);
                cli_writeln("Created profile field with shortname '" . helper::FIELD_ENROLLED_UNTIL . "'");
            } else {
                cli_writeln("Will create profile field with shortname '" . helper::FIELD_ENROLLED_UNTIL . "'");
            }
        } else {
            cli_writeln("Profile field with shortname '" . helper::FIELD_ENROLLED_UNTIL . "' already exists. Skipping.");
        }

        // Create autocomplete user profile field for tags.
        $tagfield = $DB->get_record('user_info_field', ['shortname' => helper::ITEM_TYPE_TAG]);
        if (empty($tagfield)) {
            if ($options['run']) {
                // Fill the field options with a list of course related tags.
                $tags = install_helper::get_course_tags();
                $param1 = [];
                foreach ($tags as $tag) {
                    $param1[$tag->rawname] = $tag->rawname;
                }
                $extras = [];
                $extras['categoryid'] = $profilefieldcategory->id;
                $extras['param1'] = implode("\n", $param1);
                $extras['param2'] = 1; // Enable multi-selection.
                $extras['sortorder'] = 2;
                install_helper::add_user_profile_field('Tags', helper::ITEM_TYPE_TAG, 'autocomplete', $extras);
                cli_writeln("Created profile field with shortname '" . helper::ITEM_TYPE_TAG . "'");
            } else {
                cli_writeln("Will create profile field with shortname '" . helper::ITEM_TYPE_TAG . "'");
            }
        } else {
            cli_writeln("Profile field with shortname '" . helper::ITEM_TYPE_TAG . "' already exists. Skipping.");
        }

        // Go through all tags and create cohort for each tag.
        $tags = install_helper::get_course_tags();
        foreach ($tags as $tag) {
            $cohort = new stdClass();
            $cohort->contextid = context_system::instance()->id;
            $cohort->name = $tag->rawname;
            $cohort->idnumber = $tag->rawname;
            $cohort->description = 'Tag related';
            $cohort->customfield_type = 'tag';
            $cohort->customfield_id = $tag->id;

            install_helper::set_up_add_cohort($cohort, $options);
            install_helper::add_rule($cohort, helper::ITEM_TYPE_TAG, $options);
        }

        // Go through all categories and for each category create a cohort.
        foreach (install_helper::get_categories() as $category) {
            $cohort = new stdClass();
            $cohort->contextid = context_system::instance()->id;
            $cohort->name = $category->name;
            $cohort->idnumber = $category->name;
            $cohort->description = 'Category related';
            $cohort->customfield_type = 'category';
            $cohort->customfield_id = $category->id;

            install_helper::set_up_add_cohort($cohort, $options);
            install_helper::add_rule($cohort, helper::ITEM_TYPE_CATEGORY, $options);
        }

        // Go through each course from each category and create cohort.
        foreach (install_helper::get_courses() as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $cohort = new stdClass();
            $cohort->contextid = context_system::instance()->id;
            $cohort->name = $course->fullname;
            $cohort->idnumber = $course->fullname;
            $cohort->description = 'Course related';
            $cohort->customfield_type = 'course';
            $cohort->customfield_id = $course->id;

            install_helper::set_up_add_cohort($cohort, $options);
            install_helper::add_rule($cohort, helper::ITEM_TYPE_COURSE, $options);
        }

        // Cohorts are set up. Let's manage course enrolment methods and rules.

        // Go through all courses:
        // - get related course cohort and add an enrolment method for that cohort.
        // - get related category cohort and add an enrolment method for that cohort.
        // - get all tags for the course and for each tag create an enrolment method.
        foreach (install_helper::get_courses() as $course) {

            if ($course->id == SITEID) {
                continue;
            }

            // Course cohort.
            if ($cohort = $DB->get_record('cohort', ['name' => $course->fullname])) {
                install_helper::add_enrolment_method($course, $cohort, $options);
            } else {
                if ($options['run']) {
                    cli_writeln("Course ID $course->id: cohort for course '$course->fullname}}' is not found. Skipping.");
                }
            }

            // Category cohort.
            $category = $DB->get_record('course_categories', ['id' => $course->category]);
            if ($cohort = $DB->get_record('cohort', ['name' => $category->name])) {
                install_helper::add_enrolment_method($course, $cohort, $options);
            } else {
                if ($options['run']) {
                    cli_writeln("Course ID $course->id: cohort for category '$category->name' is not found. Skipping.");
                }
            }

            // Tags cohorts.
            foreach (install_helper::get_course_tags($course->id) as $tag) {
                if ($cohort = $DB->get_record('cohort', ['name' => $tag->rawname])) {
                    install_helper::add_enrolment_method($course, $cohort, $options);
                } else {
                    if ($options['run']) {
                        cli_writeln("Course ID $course->id: cohort for tag '$tag->rawname' is not found. Skipping.");
                    }
                }
            }
        }
    }
    // Finally commit everything.
    $transaction->allow_commit();
} catch (Exception $exception) {
    $transaction->rollback($exception);
    cli_error($exception->getMessage());
}

if ($options['run']) {
    purge_all_caches();
}
cli_writeln('Done');
exit(0);
