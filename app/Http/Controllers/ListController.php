<?php

namespace App\Http\Controllers;

use App\Enums\LivesetQuality;
use App\Services\TimetablerService;
use Inertia\Inertia;

class ListController extends Controller
{

    public function index(TimetablerService $timetablerService, \App\Services\EditionsDataService $editionsDataService)
    {
        return Inertia::render('List', [
            'editions' => $editionsDataService->buildEditionsData($timetablerService),
            'qualities' => LivesetQuality::options(),
        ]);
    }


}
