<?php
namespace App\Http\Controllers\Affiliate;

use App\Domain\DataTables\AdminAffiliatesCampaignsDataTable;
use App\Domain\Repositories\AffiliateRepository;
use App\Domain\Services\Affiliate\AffiliateService;
use App\Domain\Models\Affiliate\Affiliate;
use App\Domain\Models\Affiliate\Iframe;
use Auth;
use Illuminate\Http\Request;
use App\Domain\DataTables\AffiliateIframeDataTable;

class FormulateController extends BaseController
{
    /**
     * @var AffiliateRepository
     */
    protected $affiliateRepository;

    /**
     * @param AffiliateRepository $affiliateRepository
     */
    public function __construct(AffiliateRepository $affiliateRepository)
    {
        $this->affiliateRepository = $affiliateRepository;
    }


    /**
     * @return mixed
     */
    public function getOverview()
    {
        $affiliate = $this->getCurrentAffiliate();

        return view('affiliate.formulate.overview', ['affiliate_id' => $affiliate->id]);
    }


    /**
     * @param $affiliate_id
     * @param Request $request
     * @param AffiliateIframeDataTable $table
     *
     * @return mixed
     */
    public function getOverviewData($affiliate_id, Request $request, AffiliateIframeDataTable $table)
    {
        $table->affiliate_id = $affiliate_id;

        return $table->make($request);
    }

    /**
     * @return $this
     */
    public function createIFrame()
    {
        $Affiliate = $this->getCurrentAffiliate();
        $campaign = $Affiliate->getAllCampaigns();
        $font = ['Arial', 'Times Roman', 'Helvetica'];
        $width = ['1px', '2px', '3px', '4px', '5px'];
        $width = array_combine($width, $width);
        $font = array_combine($font, $font);

        
        return view('affiliate.formulate.create')->with([
            'campaign'     => array_pluck($campaign, 'campaign_name', 'id'),
            'affiliate_id' => $Affiliate->id,
            'font'         => $font,
            'width'        => $width,
            'action'       => 'create',
        ]);
    }

    /**
     * @param Request $request
     * @param AffiliateService $service
     *
     * @return string
     */
    public function storeIFrame(Request $request, AffiliateService $service)
    {
        $data = $request->all();
        $data['status'] = Iframe::STATUS_ACTIVE;
        $this->deleteDraftVersion();
        $url = $service->postIFrameData($data, serialize(array_get($data, 'css')));
        return redirect()->route('affiliate.formulate.iframe.edit', $url[1]);
    }

    /**
     * @param Request $request
     * @param AffiliateService $service
     *
     * @return string
     */
    public function previewIFrame(Request $request, AffiliateService $service)
    {
        $data = $request->all();
        $data['status'] = Iframe::STATUS_DRAFT;
        $url = $service->postIFrameData($data, serialize(array_get($data, 'css')));
        return $this->getHtmlIframe($url, array_get($data, 'css'));
    }

    /**
     * @param $id
     * @param AffiliateService $service
     *
     * @return $this
     */
    public function editIframe($id, AffiliateService $service)
    {
        $Affiliate = $this->getCurrentAffiliate();
        $campaign = $Affiliate->getAllCampaigns();
        $font = ['Arial', 'Times Roman', 'Helvetica'];
        $width = ['1px', '2px', '3px', '4px', '5px'];
        $iframe_data = $service->getIFrameData($id);

        $iframe_data['css'] = unserialize($iframe_data['css_settings']);
        $iframe_data['aff'] = false;
        $iframe_data['code'] = $this->buildHtmlIframe($iframe_data['iframe_url'], $iframe_data['css']);
        ;

        return view('affiliate.formulate.create')->with([
            'campaign'     => array_pluck($campaign, 'campaign_name', 'id'),
            'affiliate_id' => $Affiliate->id,
            'font'         => array_combine($font, $font),
            'width'        => array_combine($width, $width),
            'iframe_data'  => $iframe_data,
            'action'       => 'edit',
        ]);
    }

    /**
     * @param $id
     * @param Request $request
     * @param AffiliateService $service
     *
     * @return string
     */
    public function updateIFrame($id, Request $request, AffiliateService $service)
    {
        $data = $request->all();
        $this->deleteDraftVersion();
        $url = $service->updateIFrameData($id, $data, serialize(array_get($data, 'css')));
        return $this->getHtmlIframe($url, array_get($data, 'css'));
    }

    /**
     * @param $url array
     * @param $css
     *
     * @return array
     */
    function getHtmlIframe($url, $css)
    {
        $html = $this->buildHtmlIframe($url[0], $css);
        return [$html, $url[1]];
    }

    /**
     * @param $id
     *
     * @return redirect
     */
    public function deleteIframe($id)
    {
        /**
         * @var Iframe $iFrame
         */
        $iFrame = Iframe::find($id);
        if ($iFrame == null) {
            return null;
        }
        $iFrame->deleteIframe($iFrame);

        return redirect()->back();
    }

    /**
     * @param Request $request
     *
     * @return null
     */
    public function deleteIframeHref(Request $request)
    {
        $id = $request->get('id');
        /**
         * @var Iframe $iFrame
         */
        $iFrame = Iframe::where('id', $id)->where('affiliate_id', array_get($this->getCurrentAffiliateAsArray(), 'id'))->first();
        if ($iFrame == null) {
            return null;
        }
        $iFrame->deleteIframe($iFrame);

        return redirect()->back();
    }

    /**
     * @return string
     */
    public function deleteDraftVersion()
    {
        $Affiliate = \Auth::user()->affiliate->first()->toArray();
        Iframe::where('affiliate_id', $Affiliate['id'])
            ->where('status', Iframe::STATUS_DRAFT)
            ->forceDelete();
    }

    /**
     * @param $url
     * @param $css_settings
     *
     * @return string
     */
    protected function buildHtmlIframe($url, $css_settings)
    {
        $width = ($css_settings['iframe_width_code']) ? $css_settings['iframe_width_code'] : AffiliateService::DEFAULT_WIDTH_IFRAME;

        return Iframe::getEmbedCode($url, $width.'%', AffiliateService::DEFAULT_HEIGHT_IFRAME);
    }
}
