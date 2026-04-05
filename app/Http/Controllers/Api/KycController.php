<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycRequest;
use App\Jobs\ProcessKycVideo;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function receivePayload(Request $request)
    {
        // 1. Verify the Secret from Server A (Set KYC_API_SECRET in Server B's .env)
        if ($request->header('X-API-SECRET') !== env('KYC_API_SECRET')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 2. Validate exact payload names from Server A
        $request->validate([
            'user_id' => 'required|integer',
            'webhook_url' => 'required|url',
            'document' => 'required|file',
            'video_selfie' => 'required|file',
        ]);

        try {
            // 3. Store the files securely
            $docPath = $request->file('document')->store("kyc/{$request->user_id}/documents", 'local');
            $videoPath = $request->file('video_selfie')->store("kyc/{$request->user_id}/videos", 'local');

            // 4. Create the Local DB Record
            $kycRecord = KycRequest::create([
                'user_id' => $request->user_id,
                'document_path' => $docPath,
                'video_path' => $videoPath,
                'status' => 'processing'
            ]);

            // 5. Dispatch the Python Job AND pass the webhook URL
            ProcessKycVideo::dispatch($kycRecord, $request->webhook_url);

            // 6. Return success immediately so Server A knows it's safely queued
            return response()->json([
                'message' => 'Files securely received. AI processing started.',
                'status' => 'processing'
            ], 202);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}