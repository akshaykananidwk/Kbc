<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\QuestionRepository;

final class QuestionApiController extends Controller
{
    public function index(Request $request): Response
    {
        $repository = new QuestionRepository();
        $perPage = min(100, max(5, $request->int('per_page', 20)));
        $result = $repository->paginate([
            'search'      => $request->string('search'),
            'category_id' => $request->int('category_id'),
            'difficulty'  => $request->string('difficulty'),
            'status'      => $request->string('status'),
            'prize_level' => $request->int('prize_level'),
        ], $this->page($request), $perPage);

        return $this->ok('Questions.', [
            'questions'  => $result['rows'],
            'pagination' => $this->pagination($result['total'], $this->page($request), $perPage),
        ]);
    }

    public function show(Request $request): Response
    {
        $question = (new QuestionRepository())->findWithOptions($request->intParam('id'));
        if ($question === null) {
            return $this->fail('Question not found.', 404);
        }
        return $this->ok('Question.', ['question' => $question]);
    }
}
