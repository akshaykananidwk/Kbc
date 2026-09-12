<?php
declare(strict_types=1);

use App\Core\Database;

/**
 * Music, certificate, sponsor, leaderboard and rehearsal settings.
 *
 * Settings are seeded here rather than in a seeder so that existing
 * installations pick them up through the normal update path.
 */
return new class {
    /** @var array<int,array{0:string,1:string,2:string,3:string,4:string,5:int}> */
    private array $settings = [
        // group, key, value, type, label, sort
        ['music', 'music_enabled',            '1',  'boolean', 'Enable Music',                         10],
        ['music', 'music_intro',              '',   'string',  'Intro / Welcome Song',                 20],
        ['music', 'music_intro_loop',         '1',  'boolean', 'Loop the Intro Song',                  30],
        ['music', 'music_background',         '',   'string',  'Background Music During Questions',    40],
        ['music', 'music_background_volume',  '25', 'integer', 'Background Music Volume (%)',          50],
        ['music', 'music_suspense',           '',   'string',  'Suspense Music (after answer locked)',  60],
        ['music', 'music_victory',            '',   'string',  'Victory Song (final win)',             70],
        ['music', 'music_duck_on_question',   '1',  'boolean', 'Lower Background Music During Effects', 80],
        ['music', 'sound_synth_fallback',     '1',  'boolean', 'Use Built-in Sounds When No File Is Uploaded', 90],
        ['music', 'sound_volume',             '80', 'integer', 'Sound Effect Volume (%)',             100],

        ['certificate', 'certificate_enabled',   '1', 'boolean', 'Enable Winner Certificates',       10],
        ['certificate', 'certificate_title',     'વિજેતા પ્રમાણપત્ર', 'string', 'Certificate Heading', 20],
        ['certificate', 'certificate_org',       '', 'string',  'Organisation / Mandal Name',        30],
        ['certificate', 'certificate_signatory', '', 'string',  'Signatory Name',                    40],
        ['certificate', 'certificate_role',      'President', 'string', 'Signatory Designation',     50],
        ['certificate', 'certificate_signature', '', 'string',  'Signature Image',                   60],
        ['certificate', 'certificate_seal',      '', 'string',  'Seal / Stamp Image',                70],
        ['certificate', 'certificate_message',   'ગણપતિ બાપા ક્વિઝ શોમાં ઉત્સાહપૂર્વક ભાગ લેવા બદલ અભિનંદન.', 'text', 'Certificate Message', 80],
        ['certificate', 'certificate_min_prize', '0', 'integer', 'Only Issue Above This Prize Amount', 90],

        ['display', 'show_leaderboard',      '1',  'boolean', 'Show Leaderboard When Idle',        110],
        ['display', 'leaderboard_count',     '8',  'integer', 'Leaderboard Entries',               120],
        ['display', 'leaderboard_rotate_ms', '9000', 'integer', 'Idle Screen Rotation (ms)',       130],
        ['display', 'show_sponsors',         '1',  'boolean', 'Show Sponsors When Idle',           140],
        ['display', 'cheque_animation',      '1',  'boolean', 'Big Cheque Animation On Final Win',  150],
        ['display', 'show_qr_poll',          '1',  'boolean', 'Show QR Code For Audience Poll',    160],

        ['game', 'rehearsal_mode',   '0', 'boolean', 'Rehearsal Mode (games are not recorded)',    120],
        ['game', 'fff_enabled',      '1', 'boolean', 'Enable Fastest Finger First Round',          130],
        ['game', 'fff_time_limit',   '25', 'integer', 'Fastest Finger First Time Limit (seconds)', 140],
        ['game', 'audience_poll_live', '1', 'boolean', 'Use Real Audience Votes When Available',   150],
        ['game', 'audience_poll_window', '30', 'integer', 'Audience Voting Window (seconds)',      160],

        ['general', 'public_registration', '1', 'boolean', 'Allow Public QR Registration',          120],
    ];

    public function up(Database $db): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($this->settings as [$group, $key, $value, $type, $label, $sort]) {
            $exists = (int) ($db->scalar('SELECT COUNT(*) FROM settings WHERE key_name = ?', [$key]) ?? 0);
            if ($exists > 0) {
                continue;
            }
            $db->insert('settings', [
                'group_name' => $group,
                'key_name'   => $key,
                'value'      => $value,
                'type'       => $type,
                'label'      => $label,
                'sort_order' => $sort,
                'is_secret'  => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(Database $db): void
    {
        foreach ($this->settings as [, $key]) {
            $db->run('DELETE FROM settings WHERE key_name = ?', [$key]);
        }
    }
};
