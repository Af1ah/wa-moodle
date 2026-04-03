<?php
namespace message_wamoodle\local;

defined('MOODLE_INTERNAL') || die();

final class session_state_repository {
    public function get(string $instancename): ?\stdClass {
        global $DB;
        return $DB->get_record('message_wamoodle_session', ['instancename' => $instancename]) ?: null;
    }

    public function upsert(string $instancename, array $state): \stdClass {
        global $DB;

        $record = $this->get($instancename);
        $now = time();

        if (!$record) {
            $record = (object)[
                'instancename' => $instancename,
                'connectionstatus' => 'missing',
                'ownerjid' => null,
                'profilename' => null,
                'phonenumber' => null,
                'qrcode' => null,
                'qrbase64' => null,
                'pairingcode' => null,
                'lasterror' => null,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('message_wamoodle_session', $record);
        }

        foreach ($state as $key => $value) {
            $record->{$key} = $value;
        }

        $record->timemodified = $now;
        $DB->update_record('message_wamoodle_session', $record);

        return $record;
    }
}
