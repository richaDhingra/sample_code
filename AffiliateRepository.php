<?php
namespace App\Domain\Repositories;

use App\Domain\Models\Affiliate\Affiliate;
use App\Domain\Models\Affiliate\Campaign;
use App\Domain\Repositories\Interfaces\Repository;

/**
 * Class AffiliateRepository
 *
 * @package App\Domain\Repositories
 */
class AffiliateRepository extends AbstractRepository implements Repository
{
    /**
     * @param Affiliate $affiliate
     */
    public function __construct(Affiliate $affiliate)
    {
        $this->model = $affiliate;
    }

    /**
     * @return mixed
     */
    public function getAllForFilter()
    {
        $sql = "
            SELECT af.id, CONCAT(p.first_name, ' ', p.last_name) AS name 
            FROM s_affiliates.affiliate AS af
            LEFT JOIN s_affiliates.account AS ac ON (af.id = ac.affiliate_id)
            LEFT JOIN s_affiliates.affiliate_user_rel AS aur ON (af.id = aur.affiliate_id)
            LEFT JOIN s_users.user AS u ON (aur.user_id = u.id)
            LEFT JOIN s_profiles.profile AS p ON (p.user_id = u.id)
            WHERE af.deleted_at IS NULL
            ORDER BY name
        ";

        $results = \DB::select($sql, []);
        $items = [];

        $result_data = json_decode(json_encode($results), true);

        foreach ($result_data as $item) {
            $items[ $item['id'] ] = $item['name'];
        }

        return $items;
    }

    /**
     * @param array $with
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getAllActive($with = [])
    {
        return $this->model
            ->with($with)
            ->whereHas('campaigns', function ($query) {
                $query->where('status', Campaign::STATUS_ACTIVE);
            })
            ->get();
    }

    /**
     * @param array $with
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getAllActiveWithCampaigns($locale_code)
    {
        return $this->model
            ->with([
                'campaigns'      => function ($query) use ($locale_code) {
                    $query->where('status', Campaign::STATUS_ACTIVE);
                    if ($locale_code) {
                        $query->whereIn('locale_code', $locale_code);
                    }
                },
                'campaigns.products'
            ])
            ->whereHas('campaigns', function ($query) use($locale_code){
                $query->where('status', Campaign::STATUS_ACTIVE);
                if ($locale_code) {
                    $query->whereIn('locale_code', $locale_code);
                }

            })
            ->get();
    }

}
