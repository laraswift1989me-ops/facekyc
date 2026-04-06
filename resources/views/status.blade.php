<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KYC Verification Service</title>
<meta name="robots" content="noindex, nofollow">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; background: #0a0e1a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; overflow: hidden; }

  .bg { position: fixed; inset: 0; z-index: 0; }
  .bg .orb { position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.15; }
  .bg .orb-1 { width: 500px; height: 500px; background: #22d3ee; top: -150px; right: -100px; animation: float 20s ease-in-out infinite; }
  .bg .orb-2 { width: 400px; height: 400px; background: #8b5cf6; bottom: -100px; left: -80px; animation: float 25s ease-in-out infinite reverse; }
  .bg .orb-3 { width: 300px; height: 300px; background: #3b82f6; top: 50%; left: 50%; transform: translate(-50%, -50%); animation: pulse 15s ease-in-out infinite; }
  @keyframes float { 0%, 100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-40px) scale(1.05); } }
  @keyframes pulse { 0%, 100% { opacity: 0.1; transform: translate(-50%, -50%) scale(1); } 50% { opacity: 0.2; transform: translate(-50%, -50%) scale(1.15); } }

  .container { position: relative; z-index: 1; width: 100%; max-width: 520px; padding: 24px; }

  .card { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(40px); border: 1px solid rgba(148, 163, 184, 0.1); border-radius: 28px; padding: 40px 36px; box-shadow: 0 25px 80px rgba(0,0,0,0.5); }

  .badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 16px; border-radius: 40px; font-size: 11px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 24px; }
  .badge .dot { width: 8px; height: 8px; border-radius: 50%; animation: blink 2s ease-in-out infinite; }
  @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

  .icon-row { display: flex; align-items: center; gap: 16px; margin-bottom: 28px; }
  .icon-box { width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #22d3ee, #3b82f6); display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 30px rgba(34, 211, 238, 0.25); flex-shrink: 0; }
  .icon-box svg { width: 28px; height: 28px; stroke: #0f172a; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
  .title { font-size: 22px; font-weight: 900; color: #fff; letter-spacing: -0.5px; }
  .subtitle { font-size: 12px; color: #64748b; font-weight: 600; margin-top: 2px; }

  .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
  .stat { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(71, 85, 105, 0.3); border-radius: 16px; padding: 16px 12px; text-align: center; }
  .stat-value { font-size: 24px; font-weight: 900; color: #fff; letter-spacing: -1px; }
  .stat-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; margin-top: 4px; }

  .queue { display: flex; gap: 10px; margin-bottom: 24px; }
  .queue-item { flex: 1; padding: 14px; border-radius: 14px; text-align: center; }
  .queue-item.pending { background: rgba(251, 191, 36, 0.08); border: 1px solid rgba(251, 191, 36, 0.2); }
  .queue-item.active { background: rgba(34, 211, 238, 0.08); border: 1px solid rgba(34, 211, 238, 0.2); }
  .queue-val { font-size: 20px; font-weight: 900; }
  .queue-item.pending .queue-val { color: #fbbf24; }
  .queue-item.active .queue-val { color: #22d3ee; }
  .queue-lbl { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-top: 2px; }

  .bar { margin-bottom: 24px; }
  .bar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
  .bar-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
  .bar-value { font-size: 12px; font-weight: 800; color: #22c55e; }
  .bar-track { height: 6px; background: rgba(30, 41, 59, 0.8); border-radius: 10px; overflow: hidden; }
  .bar-fill { height: 100%; background: linear-gradient(90deg, #22c55e, #10b981); border-radius: 10px; transition: width 1s ease; }

  .footer { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid rgba(71, 85, 105, 0.2); }
  .footer-text { font-size: 11px; color: #475569; }
  .footer-time { font-size: 10px; color: #334155; font-family: monospace; }

  .secured { text-align: center; margin-top: 20px; }
  .secured p { font-size: 10px; color: #334155; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
</style>
</head>
<body>

<div class="bg">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>

<div class="container">
  <div class="card">

    <div class="badge" style="background: {{ $statusColor }}15; border: 1px solid {{ $statusColor }}30; color: {{ $statusColor }};">
      <span class="dot" style="background: {{ $statusColor }};"></span>
      {{ $status }}
    </div>

    <div class="icon-row">
      <div class="icon-box">
        <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      </div>
      <div>
        <div class="title">KYC Verification</div>
        <div class="subtitle">Identity Verification Service</div>
      </div>
    </div>

    <div class="stats">
      <div class="stat">
        <div class="stat-value">{{ number_format($total) }}</div>
        <div class="stat-label">Total</div>
      </div>
      <div class="stat">
        <div class="stat-value" style="color: #22c55e;">{{ number_format($approved) }}</div>
        <div class="stat-label">Approved</div>
      </div>
      <div class="stat">
        <div class="stat-value" style="color: #ef4444;">{{ number_format($failed) }}</div>
        <div class="stat-label">Rejected</div>
      </div>
    </div>

    <div class="queue">
      <div class="queue-item pending">
        <div class="queue-val">{{ $pending }}</div>
        <div class="queue-lbl">In Queue</div>
      </div>
      <div class="queue-item active">
        <div class="queue-val">{{ $processing }}</div>
        <div class="queue-lbl">Processing</div>
      </div>
    </div>

    <div class="bar">
      <div class="bar-header">
        <span class="bar-label">Approval Rate</span>
        <span class="bar-value">{{ $rate }}%</span>
      </div>
      <div class="bar-track">
        <div class="bar-fill" style="width: {{ $rate }}%;"></div>
      </div>
    </div>

    <div class="footer">
      <span class="footer-text">Last verified {{ $lastText }}</span>
      <span class="footer-time">{{ now()->format('H:i:s') }} UTC</span>
    </div>

  </div>

  <div class="secured">
    <p>Secured Internal Service &bull; AI-Powered Verification</p>
  </div>
</div>

</body>
</html>
