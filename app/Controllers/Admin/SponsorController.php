<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\SponsorRepository;
use App\Services\AuditService;
use App\Support\Uploader;

final class SponsorController extends Controller
{
    private SponsorRepository $sponsors;

    public function __construct()
    {
        $this->sponsors = new SponsorRepository();
    }

    public function index(Request $request): Response
    {
        return $this->view('admin.sponsors.index', [
            'sponsors' => $this->sponsors->allOrdered(),
            'showing'  => \App\Services\SettingsService::bool('show_sponsors', true),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validatePayload($request);
        $logo = $request->file('logo');
        if ($logo !== null) {
            $data['logo_path'] = Uploader::store($logo, 'branding', ['image'], 3 * 1024 * 1024)['url'];
        }
        $id = $this->sponsors->create($data);
        AuditService::log('sponsor.created', 'Added sponsor "' . $data['name'] . '"', 'sponsor', $id);
        $this->success('Sponsor added.');
        return $this->redirect('/admin/sponsors');
    }

    public function update(Request $request): Response
    {
        $id = $request->intParam('id');
        $existing = $this->sponsors->find($id);
        if ($existing === null) {
            $this->error('That sponsor no longer exists.');
            return $this->redirect('/admin/sponsors');
        }

        $data = $this->validatePayload($request);
        $logo = $request->file('logo');
        if ($logo !== null) {
            $data['logo_path'] = Uploader::store($logo, 'branding', ['image'], 3 * 1024 * 1024)['url'];
            Uploader::delete((string) ($existing['logo_path'] ?? ''));
        } elseif ($request->bool('remove_logo', false)) {
            Uploader::delete((string) ($existing['logo_path'] ?? ''));
            $data['logo_path'] = null;
        }

        $this->sponsors->updateById($id, $data);
        AuditService::log('sponsor.updated', 'Updated sponsor #' . $id, 'sponsor', $id);
        $this->success('Sponsor updated.');
        return $this->redirect('/admin/sponsors');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->intParam('id');
        $sponsor = $this->sponsors->find($id);
        if ($sponsor !== null) {
            Uploader::delete((string) ($sponsor['logo_path'] ?? ''));
            $this->sponsors->deleteById($id);
            AuditService::log('sponsor.deleted', 'Deleted sponsor #' . $id, 'sponsor', $id);
            $this->success('Sponsor deleted.');
        }
        return $this->redirect('/admin/sponsors');
    }

    /** @return array<string,mixed> */
    private function validatePayload(Request $request): array
    {
        $data = Validator::validate($request->all(), [
            'name'       => 'required|string|max:150',
            'tagline'    => 'nullable|string|max:255',
            'website'    => 'nullable|string|max:255',
            'tier'       => 'required|in:title,gold,silver,supporter',
            'status'     => 'required|in:active,inactive',
            'sort_order' => 'nullable|int|between:0,9999',
        ]);

        $website = trim((string) ($data['website'] ?? ''));
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }

        return [
            'name'       => $data['name'],
            'tagline'    => $data['tagline'] ?? null,
            'website'    => $website === '' ? null : $website,
            'tier'       => $data['tier'],
            'status'     => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
