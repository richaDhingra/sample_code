<?php
namespace App\Http\Controllers\Affiliate;

use App\Domain\Repositories\AffiliateInvoiceRepository;
use App\Modules\Affiliate\Statistics\Service;
use Carbon\Carbon;

/**
 * Class DashboardController
 *
 * @package App\Http\Controllers\Affiliate
 */
class DashboardController extends BaseController
{
    /**
     * @var Service
     */
    protected $statisticsService;
    /**
     * @var AffiliateInvoiceRepository
     */
    protected $affiliateInvoiceRepository;

    /**
     * DashboardController constructor.
     *
     * @param Service $statisticsService
     * @param AffiliateInvoiceRepository $affiliateInvoiceRepository
     */
    public function __construct(Service $statisticsService, AffiliateInvoiceRepository $affiliateInvoiceRepository)
    {
        $this->statisticsService = $statisticsService;
        $this->affiliateInvoiceRepository = $affiliateInvoiceRepository;
    }

    /**
     * @return $this
     */
    public function index()
    {
        $account_manager = $this->getAccountManager();
        $jsonStatisticsToday = $this->statisticsService->getGraphData($this->getCurrentAffiliate(), [], Carbon::now()->startOfDay(), Carbon::now()->endOfDay());

        $jsonStatisticsMonth = $this->statisticsService->getGraphData($this->getCurrentAffiliate(), [], Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

        $invoices = $this->affiliateInvoiceRepository->getActiveInvoicesByAffiliateId(array_get($this->getCurrentAffiliate(), 'id'));

        return view('affiliate.dashboard.index')->with([
            'account_manager' => $account_manager,
            'dataDay'         => $jsonStatisticsToday,
            'dataMonth'       => $jsonStatisticsMonth,
            'invoices'        => $invoices->toArray(),
        ]);
    }
}
