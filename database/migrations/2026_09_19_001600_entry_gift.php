<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * "દરેક એન્ટ્રી ને સ્યોર ગિફ્ટ" - every entry gets a gift.
 *
 * That is a promise made to everyone who registers, not a prize won on the
 * ladder, so it is tracked on the participant: who has collected theirs and
 * when.
 */
return new class {
    public function up(Database $db): void
    {
        if ($db->selectOne("SHOW COLUMNS FROM participants LIKE 'entry_gift_given_at'") === null) {
            $db->run('ALTER TABLE participants ADD COLUMN entry_gift_given_at DATETIME NULL DEFAULT NULL AFTER status');
        }
    }

    public function down(Database $db): void
    {
        if ($db->selectOne("SHOW COLUMNS FROM participants LIKE 'entry_gift_given_at'") !== null) {
            $db->run('ALTER TABLE participants DROP COLUMN entry_gift_given_at');
        }
    }
};
