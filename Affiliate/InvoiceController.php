<?php
namespace App\Http\Controllers\Affiliate;

use App\Domain\DataTables\AffiliatePanelInvoiceDataTable;
use App\Domain\Repositories\AffiliateInvoiceRepository;
use App\Domain\Services\Affiliate\InvoiceService;
use Illuminate\Http\Request;

/**
 * Class InvoiceController
 *
 * @package App\Http\Controllers\Affiliate
 */
class InvoiceController extends BaseController
{

    /**
     * @var AffiliateInvoiceRepository
     */
    protected $affiliateInvoiceRepository;
    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * InvoiceController constructor.
     *
     * @param AffiliateInvoiceRepository $affiliateInvoiceRepository
     * @param InvoiceService $invoiceService
     */
    public function __construct(AffiliateInvoiceRepository $affiliateInvoiceRepository, InvoiceService $invoiceService)
    {
        $this->affiliateInvoiceRepository = $affiliateInvoiceRepository;
        $this->invoiceService = $invoiceService;
    }

    /**
     * @return mixed
     */
    public function getIndex()
    {
        return view('affiliate.invoices.index');
    }

    /**
     * @param Request $request
     * @param AffiliatePanelInvoiceDataTable $table
     *
     * @return mixed
     */
    public function getInvoiceData(Request $request, AffiliatePanelInvoiceDataTable $table)
    {
        $table->setAffiliate(array_get($this->getCurrentAffiliateAsArray(), 'id'));

        return $table->make($request);
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public function getShow($id)
    {
        $Invoice = $this->affiliateInvoiceRepository->getById($id);

        if (array_get($Invoice->affiliate->toArray(), 'id') === array_get($this->getCurrentAffiliateAsArray(), 'id')) {
            return $this->invoiceService->download($Invoice);
        } else {
            abort(403);
        }

        return null;
    }
}
