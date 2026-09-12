<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\LifelineRepository;
use App\Services\AuditService;
use App\Support\Uploader;

final class LifelineController extends Controller
{
    private LifelineRepository $lifelines;

    public function __construct()
    {
        $this->lifelines = new LifelineRepository();
    }

    public function index(Request $request): Response
    {
        return $this->view('admin.lifelines.index', [
            'lifelines' => $this->lifelines->allDecoded(),
            'usage'     => $this->lifelines->usageStats(),
        ]);
    }

    public function update(Request $request): Response
    {
        $id = $request->intParam('id');
        $lifeline = $this->lifelines->find($id);
        if ($lifeline === null) {
            $this->error('That lifeline no longer exists.');
            return $this->redirect('/admin/lifelines');
        }

        $data = Validator::validate($request->all(), [
            'name'          => 'required|string|max:120',
            'description'   => 'nullable|string|max:300',
            'uses_per_game' => 'required|int|between:0,10',
            'sort_order'    => 'nullable|int|between:0,9999',
        ], ['uses_per_game' => 'Uses per game']);

        $config = $this->lifelines->decodeConfig($lifeline['config'] ?? null);
        $code = (string) $lifeline['code'];

        if ($code === 'fifty_fifty') {
            $config['keep_options'] = max(2, min(3, $request->int('keep_options', 2)));
        }

        if ($code === 'audience_poll') {
            $mode = $request->string('poll_mode', 'realistic');
            $config['mode'] = in_array($mode, ['realistic', 'manual'], true) ? $mode : 'realistic';
            $config['correct_bias_min'] = max(25, min(95, $request->int('correct_bias_min', 45)));
            $config['correct_bias_max'] = max($config['correct_bias_min'], min(95, $request->int('correct_bias_max', 75)));
            $manual = [];
            foreach (['A', 'B', 'C', 'D'] as $key) {
                $manual[$key] = max(0, min(100, $request->int('manual_' . $key, 25)));
            }
            $config['manual_percentages'] = $manual;
        }

        if ($code === 'expert_advice') {
            $config['expert_name'] = $request->string('expert_name', 'Quiz Expert');
            $config['mode'] = $request->string('expert_mode', 'auto') === 'manual' ? 'manual' : 'auto';
            $suggested = strtoupper($request->string('suggested_option', ''));
            $config['suggested_option'] = in_array($suggested, ['A', 'B', 'C', 'D'], true) ? $suggested : '';
            $config['confidence'] = max(1, min(100, $request->int('confidence', 80)));
            $config['message'] = $request->string('expert_message', '');

            $photo = $request->file('expert_photo');
            if ($photo !== null) {
                $stored = Uploader::store($photo, 'branding', ['image'], 3 * 1024 * 1024);
                if (($config['expert_photo'] ?? '') !== '') {
                    Uploader::delete((string) $config['expert_photo']);
                }
                $config['expert_photo'] = $stored['url'];
            } elseif ($request->bool('remove_expert_photo', false)) {
                Uploader::delete((string) ($config['expert_photo'] ?? ''));
                $config['expert_photo'] = '';
            }
        }

        if ($code === 'skip_question') {
            $config['keep_prize'] = $request->bool('keep_prize', true);
        }

        $this->lifelines->updateById($id, [
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'is_enabled'    => $request->bool('is_enabled', false) ? 1 : 0,
            'uses_per_game' => (int) $data['uses_per_game'],
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
            'config'        => json_encode($config, JSON_UNESCAPED_UNICODE),
        ]);

        AuditService::log('lifeline.updated', 'Updated lifeline "' . $data['name'] . '"', 'lifeline', $id);
        $this->success('Lifeline updated.');
        return $this->redirect('/admin/lifelines');
    }
}
