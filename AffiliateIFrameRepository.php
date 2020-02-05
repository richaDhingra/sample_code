<?php
namespace App\Domain\Repositories;

use App\Domain\Models\Affiliate\Iframe;

class AffiliateIFrameRepository extends AbstractRepository implements Interfaces\Repository
{
    /**
     * @param Iframe $iframe
     */
    public function __construct(Iframe $iframe)
    {
        $this->model = $iframe;
    }
}
