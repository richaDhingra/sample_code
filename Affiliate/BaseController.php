<?php

namespace App\Http\Controllers\Affiliate;

use App\Domain\Models\Affiliate\Affiliate;
use App\Domain\Models\Locale\Locale;
use App\Http\Controllers\Controller;

abstract class BaseController extends Controller
{

    /**
     * @return array
     */
    protected function getLocaleCode()
    {
        return Locale::all()->toArray();
    }

    /**
     * @return mixed
     */
    protected function getCurrentAffiliateStatus()
    {
        $user = \Auth::user();

        return $user['account']['status'];
    }

    /**
     * @return null
     */
    protected function getAccountManager()
    {
        $account_manager = [];
        $Affiliate = $this->getCurrentAffiliateAsArray();
        $data = Affiliate::with('accountManager.profile', 'accountManager.contact')
            ->where('id', '=', $Affiliate['id'])
            ->first()
            ->toArray();
        if ($data['account_manager'] != []) {
            $account_manager = $data['account_manager'][0];
        }

        return $account_manager;
    }

    /**
     * @return array
     */
    protected function getCurrentAffiliateAsArray()
    {
        return $this->getCurrentAffiliate()->toArray();
    }

    /**
     * @return Affiliate
     */
    protected function getCurrentAffiliate()
    {
        if (is_null(\Auth::user())) {
            return null;
        }

        return \Auth::user()->affiliate()->first();
    }
}
