<?php

namespace App\Jobs;

use App\Models\KycRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessKycVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; 
    protected $kycRequest;
    protected $webhookUrl; // Added

    public function __construct(KycRequest $kycRequest, $webhookUrl)
    {
        $this->kycRequest = $kycRequest;
        $this->webhookUrl = $webhookUrl;
    }

    public function handle(): void
    {
        $userId   = $this->kycRequest->user_id;
        $docPath  = Storage::disk('local')->path($this->kycRequest->document_path);
        $videoPath = Storage::disk('local')->path($this->kycRequest->video_path);

        $pythonPath = base_path('kyc_env/bin/python');
        $scriptPath = base_path('scripts/verify.py');

        Log::info("KYC PROCESSING: Starting AI verification for User {$userId}", [
            'kyc_request_id' => $this->kycRequest->id,
            'document_path'  => $docPath,
            'video_path'     => $videoPath,
            'doc_exists'     => file_exists($docPath),
            'video_exists'   => file_exists($videoPath),
        ]);

        // Run Python Script
        $result = Process::run([$pythonPath, $scriptPath, $docPath, $videoPath]);

        $output = null;

        if ($result->successful()) {
            $output = json_decode($result->output(), true);

            Log::info("KYC AI RESULT: Python script completed for User {$userId}", [
                'kyc_request_id' => $this->kycRequest->id,
                'status'         => $output['status'] ?? 'unknown',
                'face_match'     => $output['face_match'] ?? false,
                'confidence'     => $output['confidence'] ?? 0,
                'ocr_text'       => substr($output['ocr_text'] ?? '', 0, 200),
                'feedback'       => $output['feedback'] ?? '',
            ]);

            $this->kycRequest->update([
                'status'              => $output['status'] ?? 'failed',
                'ai_confidence_score' => $output['confidence'] ?? null,
                'ai_feedback'         => $output['feedback'] ?? 'Parsing error',
            ]);
        } else {
            $errorMsg = $result->errorOutput();

            Log::error("KYC AI FAILED: Python script error for User {$userId}", [
                'kyc_request_id' => $this->kycRequest->id,
                'exit_code'      => $result->exitCode(),
                'stderr'         => substr($errorMsg, 0, 500),
                'stdout'         => substr($result->output(), 0, 500),
            ]);

            $this->kycRequest->update([
                'status'      => 'failed',
                'ai_feedback' => 'Python execution failed: ' . $errorMsg,
            ]);
        }

        // Build webhook payload (safe even if $output is null from a failed run)
        $webhookPayload = [
            'user_id'    => $userId,
            'status'     => $output['status'] ?? 'failed',
            'face_match' => $output['face_match'] ?? false,
            'ocr_text'   => $output['ocr_text'] ?? '',
            'confidence' => $output['confidence'] ?? 0,
            'feedback'   => $output['feedback'] ?? 'Verification failed.',
        ];

        // FIRE WEBHOOK BACK TO SERVER A
        try {
            Log::info("KYC WEBHOOK: Sending result to Server A for User {$userId}", [
                'webhook_url' => $this->webhookUrl,
                'payload'     => $webhookPayload,
            ]);

            $response = Http::withHeaders([
                'X-KYC-SECRET' => env('KYC_WEBHOOK_SECRET'),
            ])->post($this->webhookUrl, $webhookPayload);

            Log::info("KYC WEBHOOK RESPONSE: Server A responded for User {$userId}", [
                'status_code' => $response->status(),
                'body'        => $response->json() ?? $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error("KYC WEBHOOK FAILED: Could not reach Server A for User {$userId}", [
                'webhook_url' => $this->webhookUrl,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}