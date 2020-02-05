<?php

namespace App\Http\Controllers\Affiliate;

use App\Modules\Affiliate\Statistics\Export\Factory;
use App\Modules\Affiliate\Statistics\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StatisticsController extends BaseController
{

    /**
     * @param Service $statisticsService
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getIndex(Service $statisticsService, Request $request)
    {
        try {
            $start = Carbon::createFromFormat(Service::DATE_FORMAT, $request->get('start'));
            $end = Carbon::createFromFormat(Service::DATE_FORMAT, $request->get('end'));

            $jsonGraphData = $statisticsService->getGraphData($this->getCurrentAffiliate(), $request->get('campaign_id', []), $start, $end);
            $tableData = $statisticsService->getTableData($this->getCurrentAffiliate(), $request->get('campaign_id', []), $start, $end);
            $filterData = $statisticsService->getFilterData($this->getCurrentAffiliate());
        } catch (\InvalidArgumentException $e) {
            return $this->redirectWithDefaultParams($statisticsService);
        }

        return view('affiliate.statistics.index')->with([
            'data'       => $jsonGraphData,
            'start'      => $start->toDateTimeString(),
            'end'        => $end->toDateTimeString(),
            'tableData'  => $tableData,
            'filterData' => $filterData,
        ]);
    }

    /**
     * @param Service $statisticsService
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectWithDefaultParams(Service $statisticsService)
    {
        return redirect()->route('affiliate.statistics', [
            'start' => $statisticsService->getDefaultStartDate(),
            'end'   => $statisticsService->getDefaultEndDate(),
        ]);
    }

    /**
     * @param $type
     * @param Request $request
     * @param Factory $exportFactory
     * @return \Illuminate\Http\Response|void
     */
    public function getExport($type, Request $request, Factory $exportFactory)
    {
        $handler = $exportFactory->buildExportHandler($type);
        $handler->setAffiliate($this->getCurrentAffiliate());
        $handler->setFilters(collect($request->all()));
        return $handler->output();
    }
}
