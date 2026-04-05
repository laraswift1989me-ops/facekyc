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
        $docPath = Storage::disk('local')->path($this->kycRequest->document_path);
        $videoPath = Storage::disk('local')->path($this->kycRequest->video_path);
        
        $pythonPath = base_path('kyc_env/bin/python');
        $scriptPath = base_path('scripts/verify.py');

        // Run Python Script
        $result = Process::run([$pythonPath, $scriptPath, $docPath, $videoPath]);

        if ($result->successful()) {
            $output = json_decode($result->output(), true);
            
            $this->kycRequest->update([
                'status' => $output['status'] ?? 'failed',
                'ai_confidence_score' => $output['confidence'] ?? null,
                'ai_feedback' => $output['feedback'] ?? 'Parsing error'
            ]);
        } else {
            $this->kycRequest->update([
                'status' => 'failed',
                'ai_feedback' => 'Python execution failed: ' . $result->errorOutput()
            ]);
        }

        // FIRE WEBHOOK BACK TO SERVER A
        try {
            Http::withHeaders([
                'X-KYC-SECRET' => env('KYC_WEBHOOK_SECRET') // Send same secret back
            ])->post($this->webhookUrl, [
                'user_id'    => $this->kycRequest->user_id,
    'status'     => $output['status'],
    'face_match' => $output['face_match'] ?? false,
    'ocr_text'   => $output['ocr_text'] ?? '',
    'confidence' => $output['confidence'] ?? 0,
    'feedback'   => $output['feedback'] ?? '',
            ]);
        } catch (\Exception $e) {
            Log::error("Webhook failed for User {$this->kycRequest->user_id}: " . $e->getMessage());
        }
    }
}