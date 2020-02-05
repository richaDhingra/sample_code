<?php
namespace App\Http\Controllers\Affiliate;

use App\Domain\Repositories\AffiliateRepository;
use App\Http\Requests\Affiliate\AffiliateProfile;
use Illuminate\Http\Request;
use App\Domain\Models\User\Account;
use Validator;
use App\Domain\Services\Affiliate\AffiliateService;

class ProfileController extends BaseController
{
    /**
     * @var AffiliateRepository
     */
    protected $affiliateRepository;

    public function __construct(AffiliateRepository $affiliateRepository)
    {
        $this->affiliateRepository = $affiliateRepository;
    }

    /**
     * @return $this
     */
    public function getProfile()
    {
        $affiliate = $this->getCurrentAffiliate();
        if (is_null($affiliate)) {
            return redirect()->route('affiliate.auth.login');
        }
        $affiliate = $this->affiliateRepository->getById($affiliate->id, ['officeContact', 'officeAddress', 'owner', 'config']);
        $locale_code = array_pluck($this->getLocaleCode(), 'code', 'code');
        $UserStatus = $this->getCurrentAffiliateStatus();
        $vatRules = $affiliate->getVatRules();

        return view('affiliate.profile.index')->with([
            'affiliate'   => $affiliate->toArray(),
            'locale_code' => $locale_code,
            'UserStatus'  => $UserStatus,
            'vatRules'    => $vatRules,
        ]);
    }

    /**
     * @param AffiliateProfile $Request
     * @param AffiliateService $service
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function profileSave(AffiliateProfile $Request, AffiliateService $service)
    {
        $data = $Request->all();
        $status = $service->postProfileData($data);

        switch ($status) {
            case AffiliateService::UPDATE_SUCCESSFUL:
                return redirect()->route('affiliate.dashboard');
                break;
        }
    }

    /**
     * @param AffiliateProfile $Request
     * @param AffiliateService $service
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function updateAddress(AffiliateProfile $Request, AffiliateService $service)
    {

        $data = $Request->all();
        $status = $service->postUpdateAddressData($data);
        switch ($status) {
            case AffiliateService::UPDATE_SUCCESSFUL:
                flash()->success(trans($status));

                return redirect()->route('affiliate.profile');
                break;
        }
    }

    /**
     * @param Request $Request
     * @param AffiliateService $service
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function updatePassword(Request $Request, AffiliateService $service)
    {

        $validator = Validator::make($Request->all(), [
            'current_password' => 'required',
            'password'         => 'required|confirmed|min:8|max:255',
        ]);

        if ($validator->fails()) {
            return redirect('affiliate/profile')
                ->withErrors($validator)
                ->withInput();
        }
        else {

            $data = $Request->all();
            $response = $service->postUpdatePasswordData($data);
            switch ($response) {
                case $service::PASSWORD_UPDATED_SUCCESSFULLY:
                    flash()->success(trans($response));

                    return redirect()->route('affiliate.profile');
                    break;

                case $service::INVALID_OLD_PASSWORD:
                    $validator->errors()->add('password.invalid_current_password', trans($response));

                    return redirect('affiliate/profile')
                        ->withErrors($validator)
                        ->withInput();
            }
        }
    }

    /**
     * @param AffiliateProfile $Request
     * @param AffiliateService $service
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function updatePaymentDetails(AffiliateProfile $Request, AffiliateService $service)
    {

        $data = $Request->all();
        $status = $service->postUpdatePaymentDetailsData($data);
        switch ($status) {
            case AffiliateService::UPDATE_SUCCESSFUL:
                flash()->success(trans($status));

                return redirect()->route('affiliate.profile');
                break;
        }
    }
}
