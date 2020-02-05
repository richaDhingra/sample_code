<?php

namespace App\Http\Controllers\Affiliate;

use App\Domain\DataTables\AffiliateCampaignDataTable;
use App\Domain\Repositories\EloquentProductRepository;
use App\Modules\Media\EntityImageManager;
use App\Domain\Models\Affiliate\Campaign;
use App\Events\EntityWasUpdated;
use Illuminate\Http\Request;

class CampaignController extends BaseController
{

    /**
     * @return $this
     */
    public function index()
    {
        return view('affiliate.campaign.index');
    }

    /**
     * @param Request $request
     * @param AffiliateCampaignDataTable $table
     *
     * @return mixed
     */
    public function getCampaignData(Request $request, AffiliateCampaignDataTable $table)
    {
        return $table->make($request);
    }
}
