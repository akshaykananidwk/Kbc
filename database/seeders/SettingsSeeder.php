<?php
declare(strict_types=1);

namespace Database\Seeders;

/**
 * Every configurable value the admin panel exposes. Nothing here is
 * hard-coded anywhere else in the application.
 */
final class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // --- General -------------------------------------------------
            ['general', 'site_name', 'ગણપતિ બાપા ક્વિઝ શો', 'string', 'Website Name', 10],
            ['general', 'site_tagline', 'Ganpati Bapa Quiz Show', 'string', 'Tagline', 20],
            ['general', 'site_logo', '', 'string', 'Site Logo', 30],
            ['general', 'ganpati_image', '', 'string', 'Ganpati Image', 40],
            ['general', 'favicon', '', 'string', 'Favicon', 50],
            ['general', 'footer_text', '© Ganpati Bapa Quiz Show', 'string', 'Footer Text', 60],
            ['general', 'contact_phone', '', 'string', 'Contact Phone', 70],
            ['general', 'contact_email', '', 'string', 'Contact Email', 80],
            ['general', 'language', 'gu', 'string', 'Language', 90],
            ['general', 'timezone', 'Asia/Kolkata', 'string', 'Timezone', 100],
            ['general', 'meta_description', 'A festive Ganpati Bapa themed live quiz show.', 'text', 'Meta Description', 110],

            // --- Theme ---------------------------------------------------
            ['theme', 'primary_color', '#b3141a', 'string', 'Primary Colour', 10],
            ['theme', 'secondary_color', '#f5a623', 'string', 'Secondary Colour', 20],
            ['theme', 'accent_color', '#ffd76e', 'string', 'Accent Colour', 30],
            ['theme', 'currency_symbol', '₹', 'string', 'Currency Symbol', 40],
            ['theme', 'indian_number_format', '1', 'boolean', 'Indian Number Grouping', 50],

            // --- Game ----------------------------------------------------
            ['game', 'default_time_limit', '30', 'integer', 'Default Question Time (seconds)', 10],
            ['game', 'max_questions', '10', 'integer', 'Maximum Questions per Game', 20],
            ['game', 'auto_next', '0', 'boolean', 'Automatically Move to Next Question', 30],
            ['game', 'auto_reveal', '0', 'boolean', 'Automatically Reveal Result After Lock', 40],
            ['game', 'allow_quit', '1', 'boolean', 'Allow Participant to Quit', 50],
            ['game', 'allow_answer_change', '1', 'boolean', 'Allow Answer Change Before Lock', 60],
            ['game', 'question_order', 'fixed', 'string', 'Question Order (fixed/random/category/difficulty)', 70],
            ['game', 'repeat_questions', '0', 'boolean', 'Allow Reusing Questions Across Games', 80],
            ['game', 'wrong_answer_ends_game', '1', 'boolean', 'Wrong Answer Ends the Game', 90],
            ['game', 'timeup_ends_game', '1', 'boolean', 'Time Up Ends the Game', 100],
            ['game', 'require_lock_before_reveal', '1', 'boolean', 'Require Answer Lock Before Reveal', 110],

            // --- Display -------------------------------------------------
            ['display', 'fullscreen_default', '0', 'boolean', 'Open Display in Full Screen', 10],
            ['display', 'animations_enabled', '1', 'boolean', 'Enable Animations', 20],
            ['display', 'sound_enabled', '1', 'boolean', 'Enable Sound Effects', 30],
            ['display', 'timer_style', 'ring', 'string', 'Timer Style (ring/bar/digits)', 40],
            ['display', 'font_scale', '100', 'integer', 'Font Size Scale (%)', 50],
            ['display', 'show_prize_ladder', '1', 'boolean', 'Show Prize Ladder', 60],
            ['display', 'show_lifelines', '1', 'boolean', 'Show Lifeline Status', 70],
            ['display', 'poll_interval_ms', '700', 'integer', 'Display Refresh Interval (ms)', 80],
            ['display', 'welcome_heading', 'ગણપતિ બાપા મોરિયા', 'string', 'Welcome Heading', 90],
            ['display', 'welcome_subheading', 'Welcome to the Ganpati Bapa Quiz Show', 'string', 'Welcome Subheading', 100],

            // --- Sound ---------------------------------------------------
            ['sound', 'sound_question_start', '', 'string', 'Question Start Sound', 10],
            ['sound', 'sound_timer_start', '', 'string', 'Timer Start Sound', 20],
            ['sound', 'sound_answer_lock', '', 'string', 'Answer Lock Sound', 30],
            ['sound', 'sound_correct_answer', '', 'string', 'Correct Answer Sound', 40],
            ['sound', 'sound_wrong_answer', '', 'string', 'Wrong Answer Sound', 50],
            ['sound', 'sound_prize_won', '', 'string', 'Prize Won Sound', 60],
            ['sound', 'sound_lifeline_used', '', 'string', 'Lifeline Used Sound', 70],
            ['sound', 'sound_final_win', '', 'string', 'Final Win Sound', 80],
            ['sound', 'sound_game_over', '', 'string', 'Game Over Sound', 90],

            // --- Security ------------------------------------------------
            ['security', 'session_timeout', '7200', 'integer', 'Session Timeout (seconds)', 10],
            ['security', 'login_attempt_limit', '5', 'integer', 'Login Attempt Limit', 20],
            ['security', 'lockout_minutes', '15', 'integer', 'Lockout Duration (minutes)', 30],
            ['security', 'audit_retention_days', '365', 'integer', 'Audit Log Retention (days)', 40],

            // --- Updates -------------------------------------------------
            ['updates', 'github_owner', '', 'string', 'GitHub Repository Owner', 10],
            ['updates', 'github_repo', '', 'string', 'GitHub Repository Name', 20],
            ['updates', 'github_branch', 'main', 'string', 'Branch', 30],
            ['updates', 'github_token', '', 'string', 'GitHub Token', 40, 1],
            ['updates', 'current_version', '1.0.0', 'string', 'Current Version', 50],
            ['updates', 'current_commit_sha', '', 'string', 'Installed Commit SHA', 60],
            ['updates', 'auto_backup_before_update', '1', 'boolean', 'Always Backup Before Updating', 70],
            ['updates', 'last_checked_at', '', 'string', 'Last Update Check', 80],
        ];

        foreach ($settings as $setting) {
            [$group, $key, $value, $type, $label, $order] = $setting;
            $isSecret = $setting[6] ?? 0;
            $this->firstOrCreate('settings', ['key_name' => $key], [
                'group_name' => $group,
                'value'      => $value,
                'type'       => $type,
                'label'      => $label,
                'sort_order' => $order,
                'is_secret'  => $isSecret,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }
}
