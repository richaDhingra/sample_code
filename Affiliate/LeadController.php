<?php

namespace App\Http\Controllers\Affiliate;

use App\Domain\DataTables\AffiliateLeadDataTable;
use Illuminate\Http\Request;

class LeadController extends BaseController
{

    /**
     * @return $this
     */
    public function index()
    {
        $affiliate = $this->getCurrentAffiliate();

        return view('affiliate.lead.overview', [
            'affiliate' => $affiliate
        ]);
    }

    /**
     * @param Request $request
     * @param AffiliateLeadDataTable $table
     *
     * @return mixed
     */
    public function getOverview($affiliate_id, Request $request, AffiliateLeadDataTable $table)
    {
        $table->affiliate_id = $affiliate_id;

        return $table->make($request);
    }
}
