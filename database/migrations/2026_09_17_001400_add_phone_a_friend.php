<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Lifelines for a hall with a real audience.
 *
 * On a live stage the crowd answers out loud and the friend answers on a
 * phone - invented percentages on the screen would simply be wrong. The
 * Audience Poll therefore gains an "announce" mode that only puts the
 * lifeline on screen, and a Phone a Friend lifeline is added with a
 * countdown for the call.
 */
return new class {
    public function up(Database $db): void
    {
        $now = date('Y-m-d H:i:s');

        // Announce mode becomes the default for a live hall.
        $poll = $db->selectOne("SELECT id, config FROM lifelines WHERE code = 'audience_poll' LIMIT 1");
        if ($poll !== null) {
            $config = json_decode((string) $poll['config'], true);
            $config = is_array($config) ? $config : [];
            if (($config['mode'] ?? '') === 'realistic') {
                $config['mode'] = 'announce';
            }
            $config['announce_seconds'] = (int) ($config['announce_seconds'] ?? 30);
            $db->update('lifelines', [
                'description' => 'Puts the lifeline on screen so the audience in the hall can answer.',
                'config'      => json_encode($config, JSON_UNESCAPED_UNICODE),
                'updated_at'  => $now,
            ], ['id' => (int) $poll['id']]);
        }

        if ($db->selectOne("SELECT id FROM lifelines WHERE code = 'phone_a_friend' LIMIT 1") === null) {
            $db->insert('lifelines', [
                'code'          => 'phone_a_friend',
                'name'          => 'Phone a Friend',
                'description'   => 'Shows a countdown while the contestant calls a friend.',
                'icon'          => 'phone',
                'uses_per_game' => 1,
                'is_enabled'    => 1,
                'sort_order'    => 25,
                'config'        => json_encode([
                    'seconds' => 30,
                    'message' => 'મિત્રને ફોન કરો',
                ], JSON_UNESCAPED_UNICODE),
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    public function down(Database $db): void
    {
        $db->run("DELETE FROM lifelines WHERE code = 'phone_a_friend'");
    }
};
