<?php
declare(strict_types=1);

namespace App\Repositories;

final class SponsorRepository extends Repository
{
    protected string $table = 'sponsors';

    /** @return array<int,array<string,mixed>> */
    public function active(): array
    {
        return $this->db->select(
            "SELECT * FROM sponsors WHERE status = 'active'
             ORDER BY FIELD(tier, 'title', 'gold', 'silver', 'supporter'), sort_order, name"
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function allOrdered(): array
    {
        return $this->db->select(
            "SELECT * FROM sponsors
             ORDER BY FIELD(tier, 'title', 'gold', 'silver', 'supporter'), sort_order, name"
        );
    }
}
