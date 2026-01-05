<?php

namespace App\Http\Controllers;

use App\Services\AssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Controller
{
    private AssistantService $assistantService;

    public function __construct(AssistantService $assistantService)
    {
        $this->assistantService = $assistantService;
    }

    public function index()
    {
        return inertia('Index');
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'nullable|string',
        ]);

        $sessionId = $request->input('session_id') ?? $this->generateSessionId();

        $result = $this->assistantService->chat(
            $request->input('message'),
            $sessionId
        );

        return response()->json([
            'response' => $result['response'],
            'order_complete' => $result['order_complete'] ?? false,
            'order_data' => $result['order_data'] ?? null,
            'session_id' => $sessionId,
        ]);
    }

    public function getStatus(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $status = $this->assistantService->getOrderStatus($request->input('session_id'));

        return response()->json($status ?? ['status' => 'not_found']);
    }

    private function generateSessionId(): string
    {
        return Str::uuid()->toString();
    }
}
