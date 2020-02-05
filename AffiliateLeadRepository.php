<?php
namespace App\Domain\Repositories;

use App\Domain\Models\Affiliate\AffiliateLead;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class AffiliateLeadRepository extends AbstractRepository implements Interfaces\Repository
{
    /**
     * @var AffiliateLead
     */
    protected $lead;

    /**
     * @param AffiliateLead $lead
     */
    public function __construct(AffiliateLead $lead)
    {
        $this->lead = $lead;
    }

    /**
     * @param $StartDate
     * @param $EndDate
     *
     * @return Collection
     */
    public function getBetweenTimeSpan($StartDate, $EndDate)
    {
        return $this->lead
            ->where('created_at', '>=', $StartDate)
            ->where('created_at', '<=', $EndDate)
            ->get();
    }

    /**
     * @param $StartDate
     * @param $EndDate
     *
     * @return mixed
     */
    public function getDataForInvoices($StartDate, $EndDate)
    {
        $sql = "
                  SELECT affiliate_id, sum(price) AS total_price
                  FROM s_affiliates.affiliate_lead l
                  WHERE CASE WHEN type ='PRODUCT' THEN created_at between :start_date AND :end_date ELSE accepted_at BETWEEN :start_date AND :end_date END
                  AND invoice_id IS NULL
                  GROUP BY affiliate_id
                ";
        $params = [
            'start_date' => $StartDate,
            'end_date'   => $EndDate,
        ];

        $results = \DB::select($sql, $params);
        return json_decode(json_encode($results), true);
    }

    /**
     * @param $StartDate
     * @param $EndDate
     * @param $affiliate_id
     */
    public function getLeadsForInvoice($StartDate, $EndDate, $affiliate_id)
    {
        return $this->lead
            ->where('created_at', '>=', $StartDate)
            ->where('created_at', '<=', $EndDate)
            ->where('affiliate_id', $affiliate_id)
            ->get();
    }

    /**
     * @param $invoice_id
     *
     * @return mixed
     */
    public function getByInvoiceId($invoice_id)
    {
        return $this->lead
            ->where('invoice_id', $invoice_id)
            ->get();
    }

    /**
     * @param int $id
     * @param array $with
     *
     * @return mixed
     */
    public function getById($id, $with = [])
    {
        return $this->lead->with($with)->where('id', $id)->first();
    }

    /**
     * @return array
     */
    public function getStatuses()
    {
        return [
            AffiliateLead::STATUS_ACCEPTED => AffiliateLead::STATUS_ACCEPTED,
            AffiliateLead::STATUS_REJECTED => AffiliateLead::STATUS_REJECTED,
            AffiliateLead::STATUS_NEW      => AffiliateLead::STATUS_NEW,
        ];
    }

    /**
     * @param $invoice_id
     *
     * @return array
     */
    public function getCampaignsFilterForInvoice($invoice_id)
    {
        $list = $this->lead
            ->leftJoin('s_affiliates.campaign', 'campaign.id', '=', 'campaign_id')
            ->distinct([
                'campaign_id',
                'campaign_name',
            ])
            ->where('invoice_id', $invoice_id)
            ->select([
                'campaign_id',
                'campaign_name',
            ])
            ->get();

        $items = [];
        foreach ($list as $item) {
            $items[ array_get($item, 'campaign_id') ] = array_get($item, 'campaign_name');
        }

        return $items;
    }

    /**
     * @param $status
     * @param $invoice_id
     *
     * @return mixed
     */
    public function getInvoiceLeadCountForStatus($status, $invoice_id)
    {
        return $this->lead
            ->where('invoice_id', $invoice_id)
            ->where('status_affiliate', $status)
            ->count();
    }

    /**
     * @param $lead_id
     *
     * @return mixed
     */
    public function getByLeadId($lead_id)
    {
        return $this->lead
            ->where('lead_id', $lead_id)
            ->first();
    }


    /**
     * @param $lead_id
     * @param $status
     *
     * @return mixed
     */
    public function updateAffiliateLead($lead_id,$status,$reject_reason)
    {
        return $this->lead
            ->where('id', $lead_id)
            ->update([
                'status_affiliate' => $status,
                'reject_reason' => $reject_reason,
                'accepted_at' => ($status == AffiliateLead::STATUS_ACCEPTED) ? Carbon::now() : null
            ]);

    }
}
