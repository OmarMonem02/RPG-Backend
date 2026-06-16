<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Export\ExportColumnCatalog;
use Illuminate\Http\JsonResponse;

class ExportColumnController extends Controller
{
    public function __construct(private readonly ExportColumnCatalog $catalog) {}

    public function index(): JsonResponse
    {
        return response()->json($this->catalog->all());
    }
}
