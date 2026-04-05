<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycRequest;
use App\Jobs\ProcessKycVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KycController extends Controller
{
    public function receivePayload(Request $request)
    {
        // 1. Verify the Secret from Server A
        if ($request->header('X-API-SECRET') !== env('KYC_API_SECRET')) {
            Log::warning('KYC INCOMING: Unauthorized request — invalid API secret', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 2. Validate exact payload names from Server A
        $request->validate([
            'user_id' => 'required|integer',
            'webhook_url' => 'required|url',
            'document' => 'required|file',
            'video_selfie' => 'required|file',
        ]);

        // 3. Log incoming request details
        Log::info("KYC INCOMING: Received payload for User {$request->user_id}", [
            'user_id'            => $request->user_id,
            'webhook_url'        => $request->webhook_url,
            'document_mime'      => $request->file('document')->getMimeType(),
            'document_size_kb'   => round($request->file('document')->getSize() / 1024, 1),
            'video_mime'         => $request->file('video_selfie')->getMimeType(),
            'video_size_kb'      => round($request->file('video_selfie')->getSize() / 1024, 1),
            'ip'                 => $request->ip(),
        ]);

        try {
            // 4. Store the files securely
            $docPath = $request->file('document')->store("kyc/{$request->user_id}/documents", 'local');
            $videoPath = $request->file('video_selfie')->store("kyc/{$request->user_id}/videos", 'local');

            Log::info("KYC STORAGE: Files saved for User {$request->user_id}", [
                'document_path' => $docPath,
                'video_path'    => $videoPath,
            ]);

            // 5. Create the Local DB Record
            $kycRecord = KycRequest::create([
                'user_id' => $request->user_id,
                'document_path' => $docPath,
                'video_path' => $videoPath,
                'status' => 'processing'
            ]);

            // 6. Dispatch the Python Job AND pass the webhook URL
            ProcessKycVideo::dispatch($kycRecord, $request->webhook_url);

            Log::info("KYC QUEUED: Job dispatched for User {$request->user_id}", [
                'kyc_request_id' => $kycRecord->id,
            ]);

            // 7. Return success immediately so Server A knows it's safely queued
            return response()->json([
                'message' => 'Files securely received. AI processing started.',
                'status' => 'processing'
            ], 202);

        } catch (\Exception $e) {
            Log::error("KYC ERROR: Failed to process incoming payload for User {$request->user_id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}