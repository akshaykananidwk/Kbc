<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\ParticipantRepository;
use App\Services\AuditService;
use App\Support\Uploader;

final class ParticipantController extends Controller
{
    private ParticipantRepository $participants;

    public function __construct()
    {
        $this->participants = new ParticipantRepository();
    }

    public function index(Request $request): Response
    {
        $perPage = 20;
        $page = $this->page($request);
        $filters = ['search' => $request->string('search'), 'status' => $request->string('status')];
        $result = $this->participants->paginate($filters, $page, $perPage);

        return $this->view('admin.participants.index', [
            'participants' => $result['rows'],
            'pagination'   => $this->pagination($result['total'], $page, $perPage, $filters),
            'filters'      => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin.participants.form', [
            'participant'    => null,
            'nextRegistration' => $this->participants->nextRegistrationNumber(),
        ]);
    }

    public function edit(Request $request): Response
    {
        $participant = $this->participants->find($request->intParam('id'));
        if ($participant === null) {
            $this->error('That participant no longer exists.');
            return $this->redirect('/admin/participants');
        }
        return $this->view('admin.participants.form', [
            'participant'      => $participant,
            'nextRegistration' => (string) $participant['registration_no'],
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validatePayload($request, null);
        $photo = $request->file('photo');
        if ($photo !== null) {
            $data['photo_path'] = Uploader::store($photo, 'participants', ['image'], 4 * 1024 * 1024)['url'];
        }

        $id = $this->participants->create($data);
        AuditService::log('participant.created', 'Added participant "' . $data['name'] . '"', 'participant', $id);
        $this->success('Participant saved.');
        return $this->redirect('/admin/participants');
    }

    public function update(Request $request): Response
    {
        $id = $request->intParam('id');
        $existing = $this->participants->find($id);
        if ($existing === null) {
            $this->error('That participant no longer exists.');
            return $this->redirect('/admin/participants');
        }

        $data = $this->validatePayload($request, $id);

        $photo = $request->file('photo');
        if ($photo !== null) {
            $data['photo_path'] = Uploader::store($photo, 'participants', ['image'], 4 * 1024 * 1024)['url'];
            Uploader::delete((string) ($existing['photo_path'] ?? ''));
        } elseif ($request->bool('remove_photo', false)) {
            Uploader::delete((string) ($existing['photo_path'] ?? ''));
            $data['photo_path'] = null;
        }

        $this->participants->updateById($id, $data);
        AuditService::log('participant.updated', 'Updated participant #' . $id, 'participant', $id);
        $this->success('Participant updated.');
        return $this->redirect('/admin/participants');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->intParam('id');
        $participant = $this->participants->find($id);
        if ($participant === null) {
            $this->error('That participant no longer exists.');
            return $this->redirect('/admin/participants');
        }

        $games = (int) (Database::instance()->scalar('SELECT COUNT(*) FROM games WHERE participant_id = ?', [$id]) ?? 0);
        if ($games > 0) {
            $this->participants->updateById($id, ['status' => 'inactive']);
            AuditService::log('participant.deactivated', 'Participant #' . $id . ' has game history so was deactivated.', 'participant', $id);
            $this->success('This participant has played ' . $games . ' game(s), so they were deactivated instead of deleted (history is preserved).');
            return $this->redirect('/admin/participants');
        }

        Uploader::delete((string) ($participant['photo_path'] ?? ''));
        $this->participants->deleteById($id);
        AuditService::log('participant.deleted', 'Deleted participant #' . $id, 'participant', $id);
        $this->success('Participant deleted.');
        return $this->redirect('/admin/participants');
    }

    /** @return array<string,mixed> */
    private function validatePayload(Request $request, ?int $ignoreId): array
    {
        $data = Validator::validate($request->all(), [
            'name'            => 'required|string|max:150',
            'registration_no' => 'nullable|string|max:40',
            'mobile'          => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:190',
            'city'            => 'nullable|string|max:120',
            'age'             => 'nullable|int|between:1,120',
            'gender'          => 'nullable|in:male,female,other,unspecified',
            'notes'           => 'nullable|string|max:2000',
            'status'          => 'required|in:active,inactive,played',
        ], ['registration_no' => 'Registration number']);

        $registration = trim((string) ($data['registration_no'] ?? ''));
        if ($registration === '') {
            $registration = $this->participants->nextRegistrationNumber();
        }
        if ($this->participants->registrationExists($registration, $ignoreId)) {
            throw new \App\Core\Exceptions\ValidationException(
                ['registration_no' => 'That registration number is already in use.'],
                'That registration number is already in use.'
            );
        }

        return [
            'name'            => $data['name'],
            'registration_no' => $registration,
            'mobile'          => $data['mobile'] ?? null,
            'email'           => $data['email'] ?? null,
            'city'            => $data['city'] ?? null,
            'age'             => ($data['age'] ?? 0) > 0 ? (int) $data['age'] : null,
            'gender'          => $data['gender'] ?? 'unspecified',
            'notes'           => $data['notes'] ?? null,
            'status'          => $data['status'],
        ];
    }
}
