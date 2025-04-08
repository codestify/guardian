<?php

namespace Shah\Guardian\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Shah\Guardian\Guardian;

class ReportController extends Controller
{
    /**
     * The Guardian instance.
     *
     * @var \Shah\Guardian\Guardian
     */
    protected $guardian;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Guardian $guardian)
    {
        $this->guardian = $guardian;
    }

    public function store(Request $request): JsonResponse
    {
        // Validate the request
        $request->validate([
            'signals' => 'required|array',
            'path' => 'nullable|string',
            'url' => 'nullable|string',
        ]);

        $success = $this->guardian->processClientReport($request);

        return response()->json([
            'success' => $success,
        ]);
    }
}
