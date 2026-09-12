<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Application;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\SeederService;
use App\Services\SettingsService;
use App\Support\Uploader;

final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $groups = [];
        foreach (SettingsService::groups() as $group) {
            $rows = SettingsService::group($group);
            foreach ($rows as $i => $row) {
                $key = (string) $row['key_name'];
                // Secrets are shown masked and never round-trip to the browser.
                $rows[$i]['display_value'] = (bool) $row['is_secret']
                    ? (SettingsService::hasValue($key) ? str_repeat('*', 16) : '')
                    : (string) ($row['value'] ?? '');
                $rows[$i]['is_configured'] = SettingsService::hasValue($key);
            }
            $groups[$group] = $rows;
        }

        return $this->view('admin.settings.index', [
            'groups'     => $groups,
            'groupNames' => [
                'general'  => 'General',
                'theme'    => 'Theme & Currency',
                'game'     => 'Game Rules',
                'display'  => 'Display Screen',
                'sound'    => 'Sounds',
                'sound'    => 'Sound Effects',
                'music'    => 'Music',
                'certificate' => 'Winner Certificate',
                'security' => 'Security',
                'updates'  => 'GitHub Updates',
            ],
            'uploadFields' => $this->uploadFields(),
            'groupIcons'   => [
                'general' => '⚙', 'theme' => '🎨', 'game' => '▶', 'display' => '▣',
                'sound' => '🔔', 'music' => '♪', 'certificate' => '🏅',
                'security' => '🔒', 'updates' => '⇧',
            ],
            'timezones'    => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): Response
    {
        $submitted = $request->array('settings');
        if ($submitted === []) {
            $this->error('Nothing was submitted.');
            return $this->redirect('/admin/settings');
        }

        // Build the allow-list from the database so unknown keys are ignored.
        $known = [];
        foreach (Database::instance()->select('SELECT key_name, type, is_secret FROM settings') as $row) {
            $known[(string) $row['key_name']] = [
                'type'      => (string) $row['type'],
                'is_secret' => (bool) $row['is_secret'],
            ];
        }

        $changes = [];
        foreach ($submitted as $key => $value) {
            $key = (string) $key;
            if (!isset($known[$key]) || is_array($value)) {
                continue;
            }
            $value = trim((string) $value);

            // A masked secret left untouched must not overwrite the real one.
            if ($known[$key]['is_secret'] && preg_match('/^\*+$/', $value)) {
                continue;
            }
            if ($known[$key]['type'] === 'integer' && $value !== '' && !preg_match('/^-?\d+$/', $value)) {
                continue;
            }
            $changes[$key] = $value;
        }

        // Checkboxes only post when ticked, so unticked booleans must be zeroed.
        foreach ($known as $key => $meta) {
            if ($meta['type'] === 'boolean' && !isset($submitted[$key]) && $request->has('settings_boolean_keys')) {
                $booleanKeys = explode(',', $request->string('settings_boolean_keys'));
                if (in_array($key, $booleanKeys, true)) {
                    $changes[$key] = '0';
                }
            }
        }

        if ($changes === []) {
            $this->error('No valid settings were submitted.');
            return $this->redirect('/admin/settings');
        }

        SettingsService::setMany($changes);

        // Keep the .env timezone in step so CLI tasks agree with the panel.
        if (isset($changes['timezone']) && in_array($changes['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            \App\Core\Env::write(Application::instance()->rootPath('.env'), ['APP_TIMEZONE' => $changes['timezone']]);
        }
        if (isset($changes['site_name'])) {
            \App\Core\Env::write(Application::instance()->rootPath('.env'), ['APP_NAME' => $changes['site_name']]);
        }

        AuditService::log('settings.updated', 'Updated ' . count($changes) . ' setting(s): ' . implode(', ', array_slice(array_keys($changes), 0, 20)), 'settings');
        $this->success(count($changes) . ' setting(s) saved.');
        return $this->back('/admin/settings');
    }

    /**
     * Upload a file straight into a media setting.
     *
     * Answers JSON when called from the inline widget so the page does not
     * have to reload, and falls back to a redirect for plain form posts.
     */
    public function upload(Request $request): Response
    {
        $key = $request->string('key');
        $fields = $this->uploadFields();
        $wantsJson = $request->expectsJson() || $request->isAjax();

        if (!isset($fields[$key])) {
            return $wantsJson
                ? $this->fail('That setting does not accept a file upload.', 422)
                : $this->backWithError('That setting does not accept a file upload.');
        }

        $file = $request->file('file');
        if ($file === null) {
            return $wantsJson
                ? $this->fail('Choose a file to upload.', 422)
                : $this->backWithError('Choose a file to upload.');
        }

        $spec = $fields[$key];

        try {
            $stored = Uploader::store($file, 'branding', $spec['types'], $spec['max']);
        } catch (\App\Core\Exceptions\ValidationException $e) {
            return $wantsJson ? $this->fail($e->getMessage(), 422) : $this->backWithError($e->getMessage());
        }

        $previous = SettingsService::string($key, '');
        if ($previous !== '') {
            Uploader::delete($previous);
        }

        SettingsService::set($key, $stored['url']);
        Database::instance()->insert('media', [
            'disk_path'     => $stored['url'],
            'original_name' => substr((string) $file['name'], 0, 255),
            'mime_type'     => $stored['mime'],
            'extension'     => $stored['extension'],
            'size_bytes'    => $stored['size'],
            'type'          => $spec['kind'] === 'audio' ? 'audio' : 'image',
            'collection'    => 'branding',
            'uploaded_by'   => \App\Services\AuthService::id(),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('settings.file_uploaded', 'Uploaded a new file for "' . $key . '"', 'settings');

        if ($wantsJson) {
            return $this->ok('File uploaded.', [
                'key'       => $key,
                'url'       => \App\Core\Application::uploadUrl($stored['url']),
                'stored'    => $stored['url'],
                'kind'      => $spec['kind'],
                'filename'  => (string) $file['name'],
                'size'      => \App\Support\Str::humanBytes($stored['size']),
            ]);
        }

        $this->success('File uploaded.');
        return $this->back('/admin/settings');
    }

    /** Clear a media setting and delete the file behind it. */
    public function removeFile(Request $request): Response
    {
        $key = $request->string('key');
        $fields = $this->uploadFields();
        $wantsJson = $request->expectsJson() || $request->isAjax();

        if (!isset($fields[$key])) {
            return $wantsJson
                ? $this->fail('That setting does not hold a file.', 422)
                : $this->backWithError('That setting does not hold a file.');
        }

        $current = SettingsService::string($key, '');
        if ($current !== '') {
            Uploader::delete($current);
            SettingsService::set($key, '');
            AuditService::log('settings.file_removed', 'Removed the file for "' . $key . '"', 'settings');
        }

        if ($wantsJson) {
            return $this->ok('File removed.', ['key' => $key]);
        }
        $this->success('File removed.');
        return $this->back('/admin/settings');
    }

    private function backWithError(string $message): Response
    {
        $this->error($message);
        return $this->back('/admin/settings');
    }

    public function removeDemo(Request $request): Response
    {
        $removed = SeederService::make()->removeDemo();
        AuditService::log('settings.demo_removed', 'Removed ' . $removed . ' demo record(s).', 'settings');
        $this->success($removed > 0
            ? $removed . ' demo record(s) removed. Anything already used in a game was kept.'
            : 'No removable demo data was found.');
        return $this->back('/admin/settings');
    }

    /**
     * Every setting that holds a file, with what it accepts.
     *
     * The settings form renders an upload control inline for each of these,
     * so nobody has to type a path into a text box.
     *
     * @return array<string,array{label:string,types:array<int,string>,max:int,kind:string}>
     */
    public function uploadFields(): array
    {
        $image = ['label' => 'Image', 'types' => ['image'], 'max' => 4 * 1024 * 1024, 'kind' => 'image'];
        $audio = ['label' => 'Sound', 'types' => ['audio'], 'max' => 8 * 1024 * 1024, 'kind' => 'audio'];
        $music = ['label' => 'Music', 'types' => ['audio'], 'max' => 20 * 1024 * 1024, 'kind' => 'audio'];

        return [
            // Branding
            'site_logo'            => $image,
            'ganpati_image'        => $image,
            'favicon'              => ['label' => 'Favicon', 'types' => ['icon'], 'max' => 512 * 1024, 'kind' => 'image'],

            // Short effects
            'sound_question_start' => $audio,
            'sound_timer_start'    => $audio,
            'sound_answer_lock'    => $audio,
            'sound_correct_answer' => $audio,
            'sound_wrong_answer'   => $audio,
            'sound_prize_won'      => $audio,
            'sound_lifeline_used'  => $audio,
            'sound_final_win'      => $audio,
            'sound_game_over'      => $audio,

            // Longer music beds
            'music_intro'          => $music,
            'music_background'     => $music,
            'music_suspense'       => $music,
            'music_victory'        => $music,

            // Certificate artwork
            'certificate_signature' => $image,
            'certificate_seal'      => $image,
        ];
    }
}
