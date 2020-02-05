<?php

namespace App\Http\Controllers\Affiliate;

use App\Domain\Models\System\DirectLogin;
use App\Domain\Services\Registration\RegistrationService;
use App\Domain\Repositories\UserRepository;
use App\Domain\Services\Auth\AuthService;
use App\Http\Traits\ThrottlesLogins;
use App\Modules\DirectLogin\DirectLoginManager;
use Auth;
use Cookie;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    use ThrottlesLogins;

    /**
     * @var AuthService
     */
    protected $authService;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @param Request $request
     * @param AuthService $authService
     */
    public function __construct(Request $request, AuthService $authService)
    {
        $this->request = $request;
        $this->authService = $authService;
    }

    /**
     * @return $this
     */
    public function login()
    {
        $login_view = true;

        return view('affiliate.auth.login')->with(['login_view' => $login_view]);
    }

    /**
     * @param Request $request
     * @param UserRepository $userRepository
     *
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function postLogin(Request $request, UserRepository $userRepository)
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            return $this->sendLockoutResponse($request);
        }

        $blocked = $userRepository->checkIfUserOrAffiliateIsBlocked($request->get('email'));

        if ($blocked !== null) {
            if ($blocked === true) {
                alert()->error(trans('client/general.login_account_blocked'), trans('client/general.login_account_blocked_title'))->autoclose(10000);

                return redirect()->back();
            }
        }

        if ($this->authService->attempt($this->request->get('email'), $this->request->get('password'), $this->request->get('remember'))) {
            $this->clearLoginAttempts($request);

            return redirect()->intended('/');
        }

        $this->incrementLoginAttempts($request);
        flash()->error('Invalid login credentials.');

        return redirect()->back();
    }


    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout()
    {
        $this->authService->logout();
        flash()->message(trans('affiliate.login.login_logout'));

        return redirect()->route('affiliate.auth.login');
    }

    /**
     * @param Request $request
     * @param RegistrationService $service
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getConfirm(Request $request, RegistrationService $service)
    {
        $token = $request->get('token');
        $status = (!$request->get('redirect')) ? $service->confirmAffiliateAccount($token, true) : $service->confirmAccount($token, true);

        switch ($status) {
            case RegistrationService::CONFIRMATION_ERROR:
                return redirect()->route('affiliate.auth.login');

                break;

            case RegistrationService::ACCOUNT_EXISTED:
                flash()->success(trans($status));

                return redirect()->route('affiliate.profile');
                break;

            case RegistrationService::ACCOUNT_CONFIRMED:
                $service->deleteToken();

                return redirect()->route('affiliate.dashboard');
                break;
        }
    }

    /**
     * @return string
     */
    public function loginUsername()
    {
        return 'email';
    }


    /**
     * @param $token
     * @param DirectLogin $directLogin
     * @param UserRepository $userRepository
     * @param DirectLoginManager $directLoginManager
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     */
    public function directLoginAffiliateDuplicate($type_id, $token, DirectLogin $directLogin, UserRepository $userRepository, DirectLoginManager $directLoginManager)
    {
        return $this->directLoginAffiliate($token, $directLogin, $userRepository, $directLoginManager);
    }

    /**
     * @param $token
     * @param DirectLogin $directLogin
     * @param UserRepository $userRepository
     * @param DirectLoginManager $directLoginManager
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     */
    public function directLoginAffiliate($token, DirectLogin $directLogin, UserRepository $userRepository, DirectLoginManager $directLoginManager)
    {
        $login = $directLoginManager->directLoginAffiliate($directLogin, DirectLogin::TYPE_AFFILIATE, $token, $userRepository);

        if ($login === false) {
            $directLoginManager->deleteDirectLogin($directLogin, $token, DirectLogin::TYPE_AFFILIATE);

            return redirect(route('affiliate.auth.login'));
        }

        $affiliate = Auth::user()->affiliate()->first();

        $route = 'affiliate.auth.direct_login';
        $params = [
            'token' => $token
        ];


        if ($affiliate->locale_code == app()->getLocale()) {
            $route = 'affiliate.dashboard';
            $params = [];

            $directLoginManager->deleteDirectLogin($directLogin, $token, DirectLogin::TYPE_AFFILIATE);
        }

        return redirect(route_to_app_with_prefix($route, $params, 'affiliate', $affiliate->locale_code));
    }
}
