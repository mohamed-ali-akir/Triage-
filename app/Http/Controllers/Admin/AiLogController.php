<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use Illuminate\View\View;

class AiLogController extends Controller
{
    /**
     * The technical log of every AI call. It holds figures only: no prompt, no answer.
     */
    public function index(): View
    {
        return view('admin.ai-logs.index', [
            'logs' => AiLog::query()->latest('id')->paginate(50),
        ]);
    }
}
