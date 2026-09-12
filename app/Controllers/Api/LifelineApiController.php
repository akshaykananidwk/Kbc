<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\LifelineRepository;

final class LifelineApiController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = (new LifelineRepository())->enabled();
        $lifelines = [];
        foreach ($rows as $row) {
            $lifelines[] = [
                'code'          => (string) $row['code'],
                'name'          => (string) $row['name'],
                'description'   => (string) ($row['description'] ?? ''),
                'icon'          => (string) $row['icon'],
                'uses_per_game' => (int) $row['uses_per_game'],
            ];
        }
        return $this->ok('Enabled lifelines.', ['lifelines' => $lifelines]);
    }
}
