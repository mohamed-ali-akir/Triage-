<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardStatistics;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardStatistics $statistics): View
    {
        return view('admin.dashboard.index', [
            'ticketsByStatus' => $statistics->ticketsByStatus(),
            'ticketsByCategory' => $statistics->ticketsByCategory(),
            'averageMinutesToTake' => $statistics->averageMinutesToTake(),
            'averageMinutesToResolve' => $statistics->averageMinutesToResolve(),
            'sla' => $statistics->sla(),
            'satisfaction' => $statistics->satisfaction(),
            'suggestions' => $statistics->suggestionsByStatus(),
            'aiUsageByDay' => $statistics->aiUsageByDay(),
            'aiUsageByPurpose' => $statistics->aiUsageByPurpose(),
        ]);
    }
}
