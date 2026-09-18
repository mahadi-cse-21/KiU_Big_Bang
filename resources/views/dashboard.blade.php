<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GridWise — Smart Campus Energy Optimization Engine | BUP CSE Fest 2026</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090d16;
            --surface: #0f172a;
            --surface-hover: #1e293b;
            --border: #1e293b;
            --border-highlight: #334155;
            --primary: #38bdf8;
            --primary-glow: rgba(56, 189, 248, 0.2);
            --accent: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.8) 0%, rgba(9, 13, 22, 0.4) 100%);
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 2rem;
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .logo-group {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .logo-badge {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #38bdf8, #6366f1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.2rem;
            color: #fff;
            box-shadow: 0 4px 20px var(--primary-glow);
        }

        .brand-title {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .brand-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 9999px;
            font-size: 0.8rem;
            color: #34d399;
            font-weight: 600;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 10px #10b981;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.15); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }

        main {
            flex: 1;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: 460px 1fr;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .panel-title {
            font-size: 1.05rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--text);
        }

        label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        select, textarea, input {
            width: 100%;
            background: #090d16;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            color: var(--text);
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s ease;
        }

        select:focus, textarea:focus, input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        textarea {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            resize: vertical;
            min-height: 260px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.85rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0284c7, #4f46e5);
            color: #fff;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.3);
        }

        .btn-primary:hover {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(2, 132, 199, 0.4);
        }

        .kpi-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .kpi-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }

        .kpi-card.cost::before { background: #38bdf8; }
        .kpi-card.grid::before { background: #10b981; }
        .kpi-card.peak::before { background: #f59e0b; }

        .kpi-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.35rem;
        }

        .kpi-value {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #fff;
        }

        .kpi-unit {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-left: 0.25rem;
        }

        .directive-card {
            background: #090d16;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-applies { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-noop { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }
        .badge-charge { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .badge-discharge { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .badge-idle { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

        .table-wrap {
            max-height: 480px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            text-align: left;
        }

        th {
            background: #1e293b;
            color: var(--text-muted);
            font-weight: 600;
            padding: 0.75rem 1rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 0.65rem 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--text);
        }

        tr:hover td {
            background: rgba(30, 41, 59, 0.4);
        }

        .code-pill {
            font-family: 'JetBrains Mono', monospace;
            background: #090d16;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            border: 1px solid var(--border);
        }

        .chart-box {
            background: #090d16;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem;
            height: 220px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            position: relative;
        }

        .chart-bars {
            display: flex;
            align-items: flex-end;
            height: 160px;
            gap: 4px;
            width: 100%;
        }

        .chart-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
        }

        .chart-bar {
            width: 100%;
            background: linear-gradient(180deg, #38bdf8, #0284c7);
            border-radius: 3px 3px 0 0;
            min-height: 4px;
            transition: height 0.3s ease;
        }

        .chart-bar.battery {
            background: linear-gradient(180deg, #a855f7, #6366f1);
        }

        .chart-label {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        footer {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding: 1.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<header>
    <div class="header-content">
        <div class="logo-group">
            <div class="logo-badge">⚡</div>
            <div>
                <div class="brand-title">GridWise <span style="font-size:0.75rem; background:rgba(56,189,248,0.15); color:var(--primary); padding:2px 8px; border-radius:6px; border:1px solid rgba(56,189,248,0.3);">Laravel 10</span></div>
                <div class="brand-sub">BUP CSE Fest 2026 Hackathon · Poridhi Preliminary</div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:1.5rem;">
            <div class="status-pill">
                <span class="status-dot"></span>
                <span>API Ready &amp; Listening</span>
            </div>
            <a href="/health" target="_blank" style="color:var(--primary); font-size:0.85rem; text-decoration:none; font-weight:600;">GET /health ↗</a>
        </div>
    </div>
</header>

<main>
    <div class="grid-layout">
        <!-- Input Configuration Panel -->
        <div class="panel">
            <div class="panel-title">
                <span>Input Scenario</span>
                <span style="font-size:0.8rem; color:var(--text-muted);">POST /optimize-energy</span>
            </div>

            <div>
                <label for="sampleSelector">Quick Load Preset Scenario</label>
                <select id="sampleSelector" style="margin-top:0.35rem;" onchange="loadSelectedSample()">
                    <option value="0">SAMPLE-01: Solar cleaning + distractor</option>
                    <option value="1">SAMPLE-02: Battery charging maintenance</option>
                    <option value="2">SAMPLE-03: Emergency reserve as percentage</option>
                    <option value="3">SAMPLE-04: No-discharge protection test</option>
                    <option value="4">SAMPLE-05: Temporary feeder grid cap</option>
                    <option value="5">SAMPLE-06: Multiple notes with distractor</option>
                    <option value="6">SAMPLE-07: Reserve plus transformer cap</option>
                    <option value="7">SAMPLE-08: Separate charge/discharge outages</option>
                    <option value="8">SAMPLE-09: Reduction wording normalization</option>
                    <option value="9">SAMPLE-10: Multi-constraint evening operation</option>
                </select>
            </div>

            <div style="display:flex; flex-direction:column; gap:0.35rem;">
                <label for="payloadEditor">Request Payload (JSON)</label>
                <textarea id="payloadEditor" spellcheck="false"></textarea>
            </div>

            <button class="btn btn-primary" id="btnSubmit" onclick="runOptimization()">
                <span id="btnIcon">⚡</span>
                <span id="btnText">Run Energy Optimizer</span>
            </button>

            <div style="background:#090d16; border:1px solid var(--border); border-radius:10px; padding:0.85rem; font-size:0.75rem; color:var(--text-muted);">
                <div style="font-weight:600; color:var(--text); margin-bottom:0.25rem;">cURL Terminal Command:</div>
                <code style="word-break:break-all; font-family:'JetBrains Mono',monospace;">curl -X POST http://127.0.0.1:8000/optimize-energy -H "Content-Type: application/json" -d @scenario.json</code>
            </div>
        </div>

        <!-- Output & Dashboard Panel -->
        <div class="panel" id="resultsPanel">
            <div class="panel-title">
                <span>Optimization &amp; Schedule Results</span>
                <span id="latencyBadge" style="font-size:0.8rem; font-family:'JetBrains Mono', monospace; color:var(--primary);">Ready</span>
            </div>

            <!-- KPIs -->
            <div class="kpi-row">
                <div class="kpi-card cost">
                    <div class="kpi-label">Recalculated Grid Cost</div>
                    <div class="kpi-value" id="kpiCost">—<span class="kpi-unit">BDT</span></div>
                </div>
                <div class="kpi-card grid">
                    <div class="kpi-label">Total Grid Imported</div>
                    <div class="kpi-value" id="kpiGrid">—<span class="kpi-unit">kWh</span></div>
                </div>
                <div class="kpi-card peak">
                    <div class="kpi-label">Peak Hourly Grid</div>
                    <div class="kpi-value" id="kpiPeak">—<span class="kpi-unit">kWh</span></div>
                </div>
            </div>

            <!-- Plan Summary -->
            <div style="background:#090d16; border:1px solid var(--border); border-radius:10px; padding:1rem;">
                <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.25rem;">Plan Summary</div>
                <div id="planSummary" style="font-size:0.9rem; color:var(--text);">Select a scenario and click Run Energy Optimizer.</div>
            </div>

            <!-- Visual Bar Chart -->
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                    <label>24-Hour Battery Storage Profile (kWh)</label>
                    <span style="font-size:0.75rem; color:var(--text-muted);">End-of-day neutrality: E<sub>24</sub> = E<sub>0</sub></span>
                </div>
                <div class="chart-box">
                    <div class="chart-bars" id="chartBars">
                        <!-- Bars dynamically populated -->
                    </div>
                </div>
            </div>

            <!-- Directive Interpretation -->
            <div>
                <label style="display:block; margin-bottom:0.5rem;">LLM Machine-Checkable Directive Interpretation</label>
                <div id="directiveContainer" style="display:flex; flex-direction:column; gap:0.5rem;">
                    <div style="color:var(--text-muted); font-size:0.85rem; font-style:italic;">No directives evaluated yet.</div>
                </div>
            </div>

            <!-- Schedule Table -->
            <div>
                <label style="display:block; margin-bottom:0.5rem;">24-Hour Energy Schedule (Hourly Plan)</label>
                <div class="table-wrap">
                    <table id="planTable">
                        <thead>
                            <tr>
                                <th>Hour</th>
                                <th>Grid (kWh)</th>
                                <th>Solar Used (kWh)</th>
                                <th>Battery Action</th>
                                <th>Battery (kWh)</th>
                                <th>Battery After (kWh)</th>
                            </tr>
                        </thead>
                        <tbody id="planTableBody">
                            <tr>
                                <td colspan="6" style="text-align:center; color:var(--text-muted); padding:2rem;">Run optimization to view hourly schedule.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<footer>
    <div>GridWise Campus Energy Optimization System · BUP CSE Fest 2026 Hackathon</div>
    <div style="margin-top:0.25rem; font-size:0.75rem;">Pure PHP Two-Phase Simplex Solver &amp; LLM-Assisted Directive Interpreter</div>
</footer>

<script>
let sampleCases = [];

async function init() {
    try {
        const res = await fetch('/data/public_sample_cases.json');
        if (res.ok) {
            const data = await res.json();
            sampleCases = data.cases || [];
            loadSelectedSample();
        }
    } catch (e) {
        console.error("Could not load samples", e);
    }
}

function loadSelectedSample() {
    const idx = parseInt(document.getElementById('sampleSelector').value, 10);
    if (sampleCases[idx]) {
        document.getElementById('payloadEditor').value = JSON.stringify(sampleCases[idx].input, null, 2);
    }
}

async function runOptimization() {
    const btn = document.getElementById('btnSubmit');
    const btnText = document.getElementById('btnText');
    const rawJson = document.getElementById('payloadEditor').value;

    let payload;
    try {
        payload = JSON.parse(rawJson);
    } catch (e) {
        alert('Invalid JSON in editor: ' + e.message);
        return;
    }

    btn.disabled = true;
    btnText.innerText = 'Optimizing Schedule...';
    const t0 = performance.now();

    try {
        const response = await fetch('/optimize-energy', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const dur = Math.round(performance.now() - t0);
        document.getElementById('latencyBadge').innerText = `${dur}ms`;

        const data = await response.json();

        if (!response.ok) {
            alert('Error (' + response.status + '): ' + (data.error || JSON.stringify(data)));
            return;
        }

        renderResults(data, payload);
    } catch (err) {
        alert('Failed to connect to API: ' + err.message);
    } finally {
        btn.disabled = false;
        btnText.innerText = 'Run Energy Optimizer';
    }
}

function renderResults(data, payload) {
    document.getElementById('kpiCost').innerHTML = `${Number(data.total_cost_bdt).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}<span class="kpi-unit">BDT</span>`;
    document.getElementById('kpiGrid').innerHTML = `${Number(data.total_grid_kwh).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}<span class="kpi-unit">kWh</span>`;
    document.getElementById('kpiPeak').innerHTML = `${Number(data.peak_grid_kwh).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}<span class="kpi-unit">kWh</span>`;
    document.getElementById('planSummary').innerText = data.plan_summary;

    // Render directives
    const dirContainer = document.getElementById('directiveContainer');
    dirContainer.innerHTML = '';
    (data.directive_interpretation || []).forEach(dir => {
        const card = document.createElement('div');
        card.className = 'directive-card';
        const appliesBadge = dir.applies
            ? `<span class="badge badge-applies">applies = true</span>`
            : `<span class="badge badge-noop">applies = false (no_op)</span>`;
        
        card.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="font-weight:700; color:var(--primary); font-size:0.9rem;">
                    Note #${dir.note_index}: <span class="code-pill">${dir.directive_type}</span>
                </div>
                ${appliesBadge}
            </div>
            <div style="font-size:0.85rem; color:var(--text-muted);">${dir.explanation || ''}</div>
            ${dir.structured_adjustment ? `<div style="font-size:0.75rem; font-family:'JetBrains Mono', monospace; background:#000; padding:0.5rem; border-radius:6px; color:#38bdf8;">${JSON.stringify(dir.structured_adjustment)}</div>` : ''}
        `;
        dirContainer.appendChild(card);
    });

    // Render table
    const tableBody = document.getElementById('planTableBody');
    tableBody.innerHTML = '';
    const cap = (payload.battery && payload.battery.capacity_kwh) ? payload.battery.capacity_kwh : 250;

    const chartBars = document.getElementById('chartBars');
    chartBars.innerHTML = '';

    (data.hourly_plan || []).forEach(row => {
        const tr = document.createElement('tr');
        let actionBadge = `<span class="badge badge-idle">idle</span>`;
        if (row.battery_action === 'charge') {
            actionBadge = `<span class="badge badge-charge">charge</span>`;
        } else if (row.battery_action === 'discharge') {
            actionBadge = `<span class="badge badge-discharge">discharge</span>`;
        }

        tr.innerHTML = `
            <td><strong>Hour ${row.hour}</strong></td>
            <td>${row.grid_kwh}</td>
            <td style="color:#34d399;">${row.solar_used_kwh}</td>
            <td>${actionBadge}</td>
            <td>${row.battery_kwh}</td>
            <td><strong>${row.battery_energy_after_kwh}</strong></td>
        `;
        tableBody.appendChild(tr);

        // Chart bar
        const barWrap = document.createElement('div');
        barWrap.className = 'chart-bar-wrap';
        const pct = Math.max(4, Math.round((row.battery_energy_after_kwh / cap) * 100));
        barWrap.innerHTML = `
            <div class="chart-bar battery" style="height:${pct}%;" title="Hour ${row.hour}: ${row.battery_energy_after_kwh} kWh"></div>
            <div class="chart-label">${row.hour}</div>
        `;
        chartBars.appendChild(barWrap);
    });
}

window.onload = init;
</script>

</body>
</html>
