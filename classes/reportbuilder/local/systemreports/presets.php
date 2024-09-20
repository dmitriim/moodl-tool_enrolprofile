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

namespace tool_enrolprofile\reportbuilder\local\systemreports;

use context;
use context_system;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use tool_enrolprofile\preset;
use tool_enrolprofile\reportbuilder\local\entities\preset_entity;

/**
 * Presets admin table.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presets extends system_report {

    /**
     * Initialise the report.
     *
     * @return void
     */
    protected function initialise(): void {
        $entity = new preset_entity();
        $alias = $entity->get_table_alias(preset::TABLE);
        $this->set_main_table(preset::TABLE, $alias);
        $this->add_entity($entity);

        $this->add_base_fields("{$alias}.id");
        $this->add_columns();
        $this->add_actions();
    }

    /**
     * Returns report context.
     *
     * @return \context
     */
    public function get_context(): context {
        return context_system::instance();
    }

    /**
     * Check if can view this system report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return is_siteadmin();
    }

    /**
     * Adds the columns we want to display in the report
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_columns(): void {
        $this->add_column_from_entity('preset_entity:id');
        $this->add_column_from_entity('preset_entity:name');
        $this->add_column_from_entity('preset_entity:categories');
        $this->add_column_from_entity('preset_entity:courses');
        $this->add_column_from_entity('preset_entity:tags');
        $this->set_initial_sort_column('preset_entity:id', SORT_ASC);
    }

    /**
     * Add the system report actions.
     */
    protected function add_actions(): void {
        $this->add_action((new action(
            new \moodle_url('#'),
            new \pix_icon('t/edit', ''),
            ['data-action' => 'edit', 'data-id' => ':id'],
            false,
            new lang_string('edit')
        )));

        $this->add_action((new action(
            new \moodle_url('#'),
            new \pix_icon('t/delete', ''),
            ['data-action' => 'delete', 'data-id' => ':id'],
            false,
            new lang_string('delete')
        )));
    }
}
