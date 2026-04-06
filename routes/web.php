<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $pending    = \App\Models\KycRequest::where('status', 'pending')->count();
    $processing = \App\Models\KycRequest::where('status', 'processing')->count();
    $approved   = \App\Models\KycRequest::where('status', 'approved')->count();
    $failed     = \App\Models\KycRequest::where('status', 'failed')->count();
    $total      = \App\Models\KycRequest::count();
    $last       = \App\Models\KycRequest::whereIn('status', ['approved', 'failed'])->latest('updated_at')->value('updated_at');
    $lastText   = $last ? \Carbon\Carbon::parse($last)->diffForHumans() : 'No verifications yet';
    $queueLoad  = $pending + $processing;
    $status     = $queueLoad > 50 ? 'High Load' : ($queueLoad > 10 ? 'Moderate' : 'Operational');
    $statusColor = $queueLoad > 50 ? '#ef4444' : ($queueLoad > 10 ? '#f59e0b' : '#22c55e');
    $rate       = $total > 0 ? round($approved / $total * 100, 1) : 0;

    return response(view('status', compact(
        'pending', 'processing', 'approved', 'failed', 'total', 'lastText',
        'status', 'statusColor', 'rate', 'queueLoad'
    )), 200)->header('Content-Type', 'text/html');
});

Route::fallback(function () {
    return response()->json(['status' => 404, 'message' => 'Not found.'], 404);
});
