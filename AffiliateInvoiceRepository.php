<?php
namespace App\Domain\Repositories;

use App\Domain\Models\Affiliate\AffiliateLead;
use App\Domain\Models\Affiliate\Invoice;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class AffiliateInvoiceRepository
 *
 * @package App\Domain\Repositories
 */
class AffiliateInvoiceRepository extends AbstractRepository implements Interfaces\Repository
{

    /**
     * @var Invoice
     */
    protected $invoice;

    /**
     * @var AffiliateLeadRepository
     */
    protected $leadRepository;

    /**
     * @param Invoice $invoice
     * @param AffiliateLeadRepository $leadRepository
     */
    public function __construct(Invoice $invoice, AffiliateLeadRepository $leadRepository)
    {
        $this->invoice = $invoice;
        $this->leadRepository = $leadRepository;
    }

    /**
     * @param $StartDate
     * @param $EndDate
     */
    public function createDraftInvoices($StartDate, $EndDate)
    {
        $invoices_data = $this->getInvoiceData($StartDate, $EndDate);

        foreach ($invoices_data as $invoice_data) {
            /**
             * @var Invoice $Invoice
             */
            $Invoice = $this->invoice->create([
                'status'                   => Invoice::STATUS_DRAFT,
                'affiliate_id'             => array_get($invoice_data, 'affiliate_id'),
                'amount_ex_vat'            => array_get($invoice_data, 'total_price'),
                'affiliate_invoice_number' => $this->getAffiliateInvoiceNumber(array_get($invoice_data, 'affiliate_id')),
                'purchase_invoice_number'  => $this->getPurchaseInvoiceNumber(),
                'period_start'             => $StartDate,
                'period_end'               => $EndDate
            ])
                ->leads()
                ->saveMany($this->getInvoiceLeads($StartDate, $EndDate, array_get($invoice_data, 'affiliate_id')));

            $this->updateInvoiceAmount(array_get(collect($Invoice->first())->toArray(), 'invoice_id'));
        }
    }

    /**
     * @param $affiliate_id
     *
     * @return string
     */
    protected function getAffiliateInvoiceNumber($affiliate_id)
    {
        $prefix = str_pad($affiliate_id, 4, '0', STR_PAD_LEFT);
        $invoice_nr = $this->invoice->where('affiliate_id', $affiliate_id)->count() + 1;
        $invoice_nr_padded = str_pad($invoice_nr, 5, '0', STR_PAD_LEFT);

        return sprintf('%s-%s', $prefix, $invoice_nr_padded);
    }

    /**
     * @return mixed
     */
    protected function getPurchaseInvoiceNumber()
    {
        $purchase_invoice_number = ($this->invoice->all()->max('purchase_invoice_number') + 1);
        if ($purchase_invoice_number < 5000) {
            return $purchase_invoice_number + 5000;
        }

        return $purchase_invoice_number;
    }

    /**
     * @param $StartDate
     * @param $EndDate
     *
     * @return mixed
     */
    protected function getInvoiceData($StartDate, $EndDate)
    {
        return $this->leadRepository->getDataForInvoices($StartDate, $EndDate);
    }

    /**
     * @param $StartDate
     * @param $EndDate
     * @param $affiliate_id
     *
     * @return mixed
     */
    protected function getInvoiceLeads($StartDate, $EndDate, $affiliate_id)
    {
        return $this->leadRepository->getLeadsForInvoice($StartDate, $EndDate, $affiliate_id);
    }

    /**
     * @return mixed
     */
    public function getDraftInvoices()
    {
        return $this->invoice->whereStatus(Invoice::STATUS_DRAFT)->with([
            'leads',
            'affiliate.owner.profile',
            'affiliate.officeContact',
            'affiliate.officeAddress',
        ])->get();
    }

    /**
     * @param int $invoice_id
     * @param array $with
     *
     * @return Invoice
     */
    public function getById($invoice_id, $with = ['affiliate.owner.profile'])
    {
        return $this->invoice->with($with)->findOrFail($invoice_id);
    }

    /**
     * @param $invoice_id
     */
    public function updateInvoiceAmount($invoice_id)
    {
        $Invoice = $this->getById($invoice_id);
        $amount_ex_vat = $Invoice->leads()->where('status_affiliate', AffiliateLead::STATUS_ACCEPTED)->sum('price');
        $amount_inc_vat = $amount_ex_vat + ((int)$Invoice->affiliate->vat_percentage * ($amount_ex_vat / 100));

        $Invoice->update([
            'amount_ex_vat'  => $amount_ex_vat,
            'amount_inc_vat' => (int)$amount_inc_vat,
            'vat_amount'     => (int)($amount_inc_vat - $amount_ex_vat),
        ]);
    }

    /**
     * @return mixed
     */
    public function getStatuses()
    {
        return $this->invoice->getStatuses();
    }


    /**
     * @param $id
     * @param int $limit
     *
     * @return Collection
     */
    public function getActiveInvoicesByAffiliateId($id, $limit = 5)
    {
        return $this
            ->invoice
            ->where('affiliate_id', $id)
            ->where('status', Invoice::STATUS_SENT)
            ->orderBy('invoice_date', 'DESC')
            ->limit($limit)
            ->get();
    }


    /**
     * @param int $invoice_id
     *
     * @return Invoice
     */
    public function getData($invoice_id)
    {
        return $this
            ->invoice
            ->with(['affiliate.owner.profile', 'affiliate.officeContact', 'affiliate.officeAddress', 'affiliate.config'])
            ->where('id', $invoice_id)
            ->first();
    }
}
