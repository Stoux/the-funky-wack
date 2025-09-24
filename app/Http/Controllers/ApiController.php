<?php

namespace App\Http\Controllers;

use App\Services\EditionsDataService;
use App\Services\TimetablerService;

class ApiController
{

    public function editions(TimetablerService $timetablerService, EditionsDataService $editionsDataService)
    {
        return response()->json($editionsDataService->buildEditionsData($timetablerService));
    }

}
