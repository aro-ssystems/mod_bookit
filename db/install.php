<?php

function xmldb_bookit_install() {
    global $DB;
    $category = new \mod_bookit\local\entity\category('Rooms', 'Examrooms');
    $category->save(2);
    $resource = new \mod_bookit\local\entity\resource('Exam room 1', 'Capacity 255 seats', 1, $category->id);
    $resource->save(2);
    $event = new \mod_bookit\local\entity\event('Exam Biologie 1', 20241, 'IT', 1725436800, 1725444000, 90, 85,
            '3 Zeitverlängerungen', 1, 2, 'Prof. Superprof', 'superprof@example.com', 4, 'Internal lorem ipsum',
            'Lorem Ipsum dolor...', 'Susi Support', [
                    (object) ['resourceid' => $resource->id, 'amount' => 2]
                    ], null, 2);
    $event->save(2);

}