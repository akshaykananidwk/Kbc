<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Interface language, separate from the content language.
 *
 * Questions can be in Gujarati while the admin panel stays in English, or
 * the other way round, so the two are configured independently.
 */
return new class {
    public function up(Database $db): void
    {
        $exists = (int) ($db->scalar("SELECT COUNT(*) FROM settings WHERE key_name = 'interface_language'") ?? 0);
        if ($exists > 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $db->insert('settings', [
            'group_name' => 'general',
            'key_name'   => 'interface_language',
            'value'      => (string) ($db->scalar("SELECT value FROM settings WHERE key_name = 'language'") ?: 'en'),
            'type'       => 'string',
            'label'      => 'Admin & Operator Interface Language',
            'description'=> 'The language of buttons and labels. Question content keeps its own language.',
            'sort_order' => 95,
            'is_secret'  => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(Database $db): void
    {
        $db->run("DELETE FROM settings WHERE key_name = 'interface_language'");
    }
};
