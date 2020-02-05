<?php

namespace App\Http\Controllers\Affiliate;

use App\Domain\Models\Affiliate\CampaignProduct;
use App\Domain\Repositories\AffiliateRepository;

class ApiController extends BaseController
{
    /**
     * @param AffiliateRepository $affiliateRepository
     * @param CampaignProduct $campaignProductModel
     * @return $this
     */
    public function getIndex(AffiliateRepository $affiliateRepository, CampaignProduct $campaignProductModel)
    {
        $affiliate = $affiliateRepository->getById($this->getCurrentAffiliate()->id, ['tokens', 'campaigns']);

        $campaignProducts = $campaignProductModel
            ->with(['product', 'campaign'])
            ->whereIn('campaign_id', (array)collect($affiliate['campaigns'])->pluck('id')->all())
            ->get();

        return view('affiliate.api.index')
            ->with([
                'affiliate' => $affiliate->toArray(),
                'campaignProducts' => $campaignProducts
            ]);
    }
}
