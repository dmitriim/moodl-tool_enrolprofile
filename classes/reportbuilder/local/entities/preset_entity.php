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

namespace tool_enrolprofile\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\column;
use lang_string;
use tool_enrolprofile\helper;
use stdClass;

/**
 * Report builder entity for presets.
 *
 * @package     tool_enrolprofile
 * @copyright   2024 Dmitrii Metelkin <dnmetelk@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preset_entity extends base {

    /**
     * Returns the default table aliases.
     * @return array
     */
    protected function get_default_tables(): array {
        return [
            'tool_enrolprofile_presets',
        ];
    }

    /**
     * Returns the default table name.
     * @return \lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('preset_entity', 'tool_enrolprofile');
    }

    /**
     * Initialises the entity.
     * @return \core_reportbuilder\local\entities\base
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }

        return $this;
    }

    /**
     * Returns list of available columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('tool_enrolprofile_presets');
        $categories = helper::get_categories();
        $courses = helper::get_courses();
        $tags = helper::get_course_tags();

        $columns[] = (new column(
            'id',
            new lang_string('preset_entity.id', 'tool_enrolprofile'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$alias}.id")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'name',
            new lang_string('preset_entity.name', 'tool_enrolprofile'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.name")
            ->add_field("{$alias}.id")
            ->set_is_sortable(true)
            ->add_callback(function ($value, $row) {
                return $value;
            });

        $columns[] = (new column(
            'category',
            new lang_string('preset_entity.categories', 'tool_enrolprofile'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.category")
            ->set_is_sortable(false)
            ->add_callback([self::class, 'render_list'], ['entities' => $categories, 'name' => 'name']);

        $columns[] = (new column(
            'course',
            new lang_string('preset_entity.courses', 'tool_enrolprofile'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.course")
            ->set_is_sortable(false)
            ->add_callback([self::class, 'render_list'], ['entities' => $courses, 'name' => 'fullname']);

        $columns[] = (new column(
            'tag',
            new lang_string('preset_entity.tags', 'tool_enrolprofile'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.tag")
            ->set_is_sortable(false)
            ->add_callback([self::class, 'render_list'], ['entities' => $tags, 'name' => 'rawname']);

        return $columns;
    }

    /**
     * A callback function to render list of categories, courses or tags in the table.
     *
     * @param string|null $ids List of IDs from DB column.
     * @param stdClass|null $row Row from DB.
     * @param array $arguments Extra arguments.
     * @return string
     */
    public static function render_list(?string $ids, ?stdClass $row, array $arguments): string {
        $names = [];
        if (!empty($ids)) {

            $name = $arguments['name'];
            $entities = $arguments['entities'];

            $ids = explode(',', $ids);
            foreach ($ids as $id) {
                $names[] = $entities[$id]->$name;
            }

            return implode(', ', $names);
        }

        return '';
    }
}
