<?php

namespace App\Http\Controllers;

use App\Services\Accounting;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;

class AccountingController extends Controller
{
    private $accounting;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->accounting = new Accounting();
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function offices(Request $request): JsonResponse
    {
        return response()->json($this->accounting->offices($request));
    }

    /**
     * @param  Request  $request
     * @param  int      $office
     * @return JsonResponse
     */
    public function incomes(Request $request, int $office): JsonResponse
    {
        return response()->json($this->accounting->incomes($request, $office));
    }

    /**
     * @param  Request  $request
     * @param  int      $office
     * @return JsonResponse
     */
    public function expenses(Request $request, int $office): JsonResponse
    {
        return response()->json($this->accounting->expenses($request, $office));
    }

    /**
     * @param  Request  $request
     * @param  int      $office
     * @return JsonResponse
     */
    public function payouts(Request $request, int $office): JsonResponse
    {
        return response()->json($this->accounting->payouts($request, $office));
    }

    /**
     * @param  Request  $request
     * @param  int      $office
     * @return JsonResponse
     */
    public function weekly_report(Request $request, int $office): JsonResponse
    {
        return response()->json($this->accounting->weekly_report($request, $office));
    }

    /**
     * @param  Request  $request
     * @param  int      $office
     * @return JsonResponse
     */
    public function setOfficeName(Request $request, int $office): JsonResponse
    {
        return response()->json($this->accounting->setOfficeName($request, $office));
    }

    /**
     * @param  int  $office
     * @return JsonResponse
     */
    public function getOfficeData(int $office): JsonResponse
    {
        return response()->json($this->accounting->getOfficeData($office));
    }
}
