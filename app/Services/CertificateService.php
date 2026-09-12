<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Repositories\GameRepository;

/**
 * Issues and reprints winner certificates.
 *
 * A certificate is issued once per game and keeps its serial number, so a
 * reprint is the same document rather than a new one.
 */
final class CertificateService
{
    public function __construct(private Database $db)
    {
    }

    public static function make(?Database $db = null): self
    {
        return new self($db ?? Database::instance());
    }

    /**
     * @return array<string,mixed> The certificate row plus everything the
     *                             template needs to render it.
     */
    public function issue(int $gameId, ?int $userId = null): array
    {
        if (!SettingsService::bool('certificate_enabled', true)) {
            throw new HttpException(403, 'Certificates are switched off in Settings.');
        }

        $games = new GameRepository($this->db);
        $game = $games->findDetailed($gameId);
        if ($game === null) {
            throw new HttpException(404, 'That game could not be found.');
        }
        if (!in_array((string) $game['status'], ['completed', 'wrong_answer', 'time_up', 'quit'], true)) {
            throw new HttpException(409, 'The game has to finish before a certificate can be issued.');
        }
        if ((int) ($game['is_rehearsal'] ?? 0) === 1) {
            throw new HttpException(409, 'Rehearsal games do not get certificates.');
        }

        $minimum = (float) SettingsService::int('certificate_min_prize', 0);
        if ((float) $game['final_prize'] < $minimum) {
            throw new HttpException(409, sprintf(
                'Certificates are only issued above %s. This game finished on %s.',
                SettingsService::money($minimum),
                SettingsService::money((float) $game['final_prize'])
            ));
        }

        $gifts = $games->giftsWon($gameId);
        $giftNames = implode(', ', array_map(static fn ($g) => (string) $g['name'], $gifts));

        $existing = $this->db->selectOne('SELECT * FROM certificates WHERE game_id = ? LIMIT 1', [$gameId]);

        if ($existing === null) {
            $serial = $this->nextSerial();
            $this->db->insert('certificates', [
                'game_id'          => $gameId,
                'participant_id'   => $game['participant_id'] === null ? null : (int) $game['participant_id'],
                'serial_no'        => $serial,
                'participant_name' => (string) ($game['participant_name'] ?? 'Participant'),
                'prize_amount'     => (float) $game['final_prize'],
                'gifts'            => $giftNames === '' ? null : substr($giftNames, 0, 500),
                'issued_by'        => $userId,
                'issued_at'        => date('Y-m-d H:i:s'),
                'print_count'      => 0,
            ]);
            AuditService::log('certificate.issued', 'Issued certificate ' . $serial . ' for ' . ($game['participant_name'] ?? 'a participant'), 'certificate', $gameId);
            $existing = $this->db->selectOne('SELECT * FROM certificates WHERE game_id = ? LIMIT 1', [$gameId]);
        }

        $this->db->run('UPDATE certificates SET print_count = print_count + 1 WHERE id = ?', [(int) $existing['id']]);

        return [
            'certificate' => $existing,
            'game'        => $game,
            'gifts'       => $gifts,
            'design'      => $this->design(),
        ];
    }

    /** @return array<string,mixed>|null */
    public function findByGame(int $gameId): ?array
    {
        return $this->db->selectOne('SELECT * FROM certificates WHERE game_id = ? LIMIT 1', [$gameId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 50): array
    {
        return $this->db->select(
            'SELECT c.*, g.game_code, u.name AS issued_by_name
             FROM certificates c
             LEFT JOIN games g ON g.id = c.game_id
             LEFT JOIN users u ON u.id = c.issued_by
             ORDER BY c.id DESC LIMIT ' . max(1, $limit)
        );
    }

    /** Everything the certificate template needs, all admin-configurable. */
    public function design(): array
    {
        return [
            'title'      => SettingsService::string('certificate_title', 'વિજેતા પ્રમાણપત્ર'),
            'org'        => SettingsService::string('certificate_org', SettingsService::string('site_name', '')),
            'signatory'  => SettingsService::string('certificate_signatory', ''),
            'role'       => SettingsService::string('certificate_role', ''),
            'signature'  => \App\Core\Application::uploadUrl(SettingsService::string('certificate_signature', '')),
            'seal'       => \App\Core\Application::uploadUrl(SettingsService::string('certificate_seal', '')),
            'message'    => SettingsService::string('certificate_message', ''),
            'logo'       => \App\Core\Application::uploadUrl(SettingsService::string('site_logo', '')),
            'ganpati'    => \App\Core\Application::uploadUrl(SettingsService::string('ganpati_image', '')),
            'primary'    => SettingsService::string('primary_color', '#b3141a'),
            'secondary'  => SettingsService::string('secondary_color', '#f5a623'),
            'accent'     => SettingsService::string('accent_color', '#ffd76e'),
        ];
    }

    private function nextSerial(): string
    {
        $prefix = 'GQC-' . date('Y') . '-';
        $last = (string) ($this->db->scalar(
            'SELECT serial_no FROM certificates WHERE serial_no LIKE ? ORDER BY id DESC LIMIT 1',
            [$prefix . '%']
        ) ?? '');

        $next = $last === '' ? 1 : ((int) substr($last, strlen($prefix))) + 1;
        return $prefix . sprintf('%04d', $next);
    }
}
