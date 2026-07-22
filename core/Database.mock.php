<?php
declare(strict_types=1);

class NuDatabase {
    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function query($sql, $params = []) {
        return new class {
            public function fetch() { return null; }
            public function fetchAll() { return []; }
            public function execute() { return true; }
        };
    }
    public function fetchAll($sql, $params = []) {
        // Mock events for Month/Week/Day/Agenda Views
        return [
            [
                'event_id' => 1,
                'event_title' => 'Sprint Planning Meeting',
                'event_description' => 'Prioritize items for the next sprint and align team.',
                'event_start' => '2026-08-10 10:00:00',
                'event_end' => '2026-08-10 11:30:00',
                'event_type' => 'meeting',
                'event_color' => '#10b981',
                'event_category' => 'work',
                'event_rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO',
                'event_exdate' => null,
                'event_recurrence_id' => null,
                'event_original_start' => null,
                'event_user_id' => '1',
                'event_active' => 1
            ],
            [
                'event_id' => 2,
                'event_title' => 'Design System Review',
                'event_description' => 'Go through theme variables and visual feedback.',
                'event_start' => '2026-08-11 14:00:00',
                'event_end' => '2026-08-11 15:30:00',
                'event_type' => 'meeting',
                'event_color' => '#8b5cf6',
                'event_category' => 'work',
                'event_rrule' => null,
                'event_exdate' => null,
                'event_recurrence_id' => null,
                'event_original_start' => null,
                'event_user_id' => '1',
                'event_active' => 1
            ],
            [
                'event_id' => 3,
                'event_title' => 'Release Deployment',
                'event_description' => 'Deploy the next-gen interactive Calendar scheduler!',
                'event_start' => '2026-08-12 11:00:00',
                'event_end' => '2026-08-12 12:00:00',
                'event_type' => 'task',
                'event_color' => '#ef4444',
                'event_category' => 'personal',
                'event_rrule' => null,
                'event_exdate' => null,
                'event_recurrence_id' => null,
                'event_original_start' => null,
                'event_user_id' => '1',
                'event_active' => 1
            ]
        ];
    }
    public function fetchOne($sql, $params = []) { return null; }
    public function insert($table, $data) { return 123; }
    public function update($table, $data, $where, $params = []) { return 1; }
    public function delete($table, $where, $params = []) { return 1; }
}
if (!class_exists('Database')) {
    class_alias('NuDatabase', 'Database');
}
