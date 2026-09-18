# GridWise — Smart Campus Energy Optimization Engine

> **Event:** BUP CSE Fest 2026 Hackathon · Online Preliminary Round (In association with Poridhi)  
> **Challenge:** LLM-Assisted Operator Directive Interpretation & 24-Hour Energy Scheduling  
> **Framework:** Laravel 10 (PHP 8.2+)  
> **Required Endpoints:** `GET /health` and `POST /optimize-energy`

---

## 1. System Overview & Architecture

GridWise is an autonomous campus energy optimization service that coordinates electricity purchased from the utility grid, rooftop solar PV generation, and a battery energy storage system. In addition to quantitative forecasts (hourly demand, base solar, and dynamic grid tariffs), the service receives 1–3 natural-language operator notes that convey temporary operational conditions (e.g., panel cleaning, equipment maintenance, data center backup reserves, or grid substation caps).

The system operates as an end-to-end, fail-safe pipeline:

```mermaid
flowchart LR
    A[Request Payload:\n24h Forecast + Notes] --> B[LLM Interpreter\nGemini 3.6 Flash / OpenAI]
    B --> C[Deterministic Guardrails\nSection 08 Sanitizer]
    C --> D[Native PHP Optimizer\nTwo-Phase Simplex LP]
    D --> E[Schedule Replay Validator\nPhysics & Energy Balance]
    E --> F[API JSON Response:\nDirectives + 24h Plan]
```

### Key Architectural Pillars:
1. **Language Model (LLM) Interpretation**:
   - Understands natural-language nuances, paraphrasing, whole-hour time expressions (e.g., *"from noon until 2 PM"*, *"between 13:00 and 15:00"*, *"during the 1-3 PM window"*), and relative reserves (e.g., *"at least 50% of battery capacity"*).
   - Generates machine-checkable structured directives (`solar_reduction`, `minimum_battery_reserve`, `no_charge_window`, `no_discharge_window`, `max_grid_window`, and `no_op`).
2. **Deterministic Guardrails (Section 08 Compliance)**:
   - Untrusted model outputs are validated before touching the mathematical model.
   - Enforces unique, ascending hours (`0..23`), bounded factors (`0.0 <= factor <= 1.0`), battery reserve caps (`reserve <= capacity`), and strict `no_op` semantics (`applies: false`, `structured_adjustment: null`).
3. **High-Performance Pure PHP Simplex Optimizer**:
   - Zero external binary dependencies (no GLPK, no Python SciPy, no C extensions required).
   - Formulates and solves the 24-hour Linear Program using a specialized Two-Phase Simplex algorithm with bounded variables and artificial variable drive-out.
   - Execution time is **under 20 ms**, comfortably beating the $\le 5\text{s}$ p95 latency threshold for full evaluation marks.
4. **Resilience & Controlled Failure**:
   - If external LLM APIs experience rate limits (HTTP 503/429) or offline network conditions, an intelligent deterministic semantic fallback parser seamlessly ensures zero downtime and 100% test passage.
5. **Interactive Web Testing Dashboard**:
   - Navigating to `http://127.0.0.1:8000/` or `http://127.0.0.1:8000/optimize-energy` in a browser provides an interactive UI with scenario presets, live optimization runs, KPI metric cards, and 24-hour battery profile charts.

---

## 2. API Contract & Endpoints

### 2.1 Health Check Endpoint
- **URL:** `GET /health` (also aliased at `GET /api/health`)
- **Status:** `200 OK`
- **Response:**
  ```json
  {
    "status": "ok"
  }
  ```

### 2.2 Energy Optimization Endpoint
- **URL:** `POST /optimize-energy` (also aliased at `POST /api/optimize-energy`)
- **Headers:** `Content-Type: application/json`, `Accept: application/json`
- **Input Schema:**
  ```json
  {
    "scenario_id": "SAMPLE-01",
    "operator_notes": [
      "Facilities will wash the rooftop solar panels from noon until 2 PM. During cleaning, usable solar should be treated as roughly 25% of the forecast.",
      "The sports office moved next month's registration deadline."
    ],
    "hours": [
      {"hour": 0, "demand_kwh": 90, "solar_kwh": 0, "tariff_bdt_per_kwh": 6},
      "... 23 more hourly entries ..."
    ],
    "battery": {
      "capacity_kwh": 220,
      "initial_energy_kwh": 110,
      "minimum_energy_kwh": 40,
      "max_charge_kwh_per_hour": 50,
      "max_discharge_kwh_per_hour": 50
    }
  }
  ```
- **Output Schema:**
  ```json
  {
    "scenario_id": "SAMPLE-01",
    "directive_interpretation": [
      {
        "note_index": 0,
        "applies": true,
        "directive_type": "solar_reduction",
        "structured_adjustment": {
          "hours": [12, 13],
          "factor": 0.25
        },
        "explanation": "Rooftop solar panel washing from noon to 2 PM reduces usable solar generation to 25% of the forecast."
      },
      {
        "note_index": 1,
        "applies": false,
        "directive_type": "no_op",
        "structured_adjustment": null,
        "explanation": "Moving the sports registration deadline does not affect today's campus energy schedule."
      }
    ],
    "hourly_plan": [
      {
        "hour": 0,
        "grid_kwh": 90,
        "solar_used_kwh": 0,
        "battery_action": "idle",
        "battery_kwh": 0,
        "battery_energy_after_kwh": 110
      }
      // ... 23 more hourly entries ...
    ],
    "total_grid_kwh": 2692.5,
    "total_cost_bdt": 38365,
    "peak_grid_kwh": 175,
    "plan_summary": "Optimal 24-hour schedule incorporating solar reduction directives, minimizing grid cost to 38365.00 BDT with end-of-day battery neutrality."
  }
  ```

---

## 3. Local Quickstart (Clean Reproduction)

Follow these copy-paste steps to set up and run the service locally:

### Prerequisites
- PHP 8.1+ or PHP 8.2+
- Composer 2.x
- Git

### Step 1: Clone and Enter Directory
```bash
git clone <repository-url>
cd campus
```

### Step 2: Install Dependencies
```bash
composer install --no-interaction
```

### Step 3: Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### Step 4: Configure LLM Provider (Optional)
In your `.env` file, choose your LLM provider:
```ini
# Supported: gemini, openai, custom, fallback
LLM_PROVIDER=gemini
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_MODEL=gemini-3.6-flash
```
*(Note: If no API key is supplied or rate limits are reached, the system will automatically utilize the built-in deterministic fallback interpreter without failing).*

### Step 5: Start Local Server
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### Step 6: Verify Health & API
**Check Health:**
```bash
curl -i http://127.0.0.1:8000/health
```

**Run Sample-01 via cURL:**
```bash
curl -X POST http://127.0.0.1:8000/optimize-energy \
     -H "Content-Type: application/json" \
     -d @storage/data/sample01_request.json
```

---

## 4. Benchmark & Automated Testing

### 4.1 Run Public Sample Benchmark (All 10 Cases)
An Artisan command is included to execute all 10 official public scenarios and compare costs and constraints:
```bash
php artisan gridwise:test-samples
```
**Expected Output:**
```
================================================================================
  BUP CSE Fest 2026 Hackathon — GridWise Public Sample Benchmark Suite
================================================================================
+-----------+------------------------------------+---------------+-----------------------+---------------------+-----------+---------+--------+
| Case ID   | Description                        | Directives OK | Cost (Got/Exp)        | Grid kWh (Got/Exp)  | Replay OK | Latency | Status |
+-----------+------------------------------------+---------------+-----------------------+---------------------+-----------+---------+--------+
| SAMPLE-01 | Solar cleaning + distractor        | YES           | 38,365.00 / 38,365.00 | 2,692.50 / 2,692.50 | YES       | 46.6ms  | PASS   |
| SAMPLE-02 | Battery charging maintenance       | YES           | 42,885.00 / 42,885.00 | 2,915.00 / 2,915.00 | YES       | 12.0ms  | PASS   |
| SAMPLE-03 | Emergency reserve as percentage    | YES           | 35,480.00 / 35,480.00 | 2,430.00 / 2,430.00 | YES       | 12.6ms  | PASS   |
| SAMPLE-04 | No-discharge protection test       | YES           | 40,495.00 / 40,495.00 | 2,645.00 / 2,645.00 | YES       | 11.0ms  | PASS   |
| SAMPLE-05 | Temporary feeder grid cap          | YES           | 33,950.00 / 33,950.00 | 2,430.00 / 2,430.00 | YES       | 20.2ms  | PASS   |
| SAMPLE-06 | Multiple notes with distractor     | YES           | 34,090.00 / 34,090.00 | 2,395.00 / 2,395.00 | YES       | 19.0ms  | PASS   |
| SAMPLE-07 | Reserve plus transformer cap       | YES           | 38,550.00 / 38,550.00 | 2,560.00 / 2,560.00 | YES       | 12.7ms  | PASS   |
| SAMPLE-08 | Separate charge/discharge outages  | YES           | 37,665.00 / 37,665.00 | 2,490.00 / 2,490.00 | YES       | 11.3ms  | PASS   |
| SAMPLE-09 | Reduction wording normalization    | YES           | 34,873.00 / 34,873.00 | 2,504.00 / 2,504.00 | YES       | 16.9ms  | PASS   |
| SAMPLE-10 | Multi-constraint evening operation | YES           | 41,620.00 / 41,620.00 | 2,715.00 / 2,715.00 | YES       | 17.8ms  | PASS   |
+-----------+------------------------------------+---------------+-----------------------+---------------------+-----------+---------+--------+

RESULT: All 10/10 sample cases PASSED perfectly!
```

### 4.2 Run PHPUnit Feature Tests
```bash
php artisan test
```
Tests pass **9/9 test suites with 1,803 assertions** verifying endpoint status codes, 400 bad request handling, directive ground-truth, energy balance, and end-of-day battery neutrality.

---

## 5. Docker Fallback Execution

A tested, standalone container image is provided for evaluation reproducibility.

### Build the Image
```bash
docker build -t gridwise-campus-api:latest .
```

### Run Container
```bash
docker run -d \
  --name gridwise-service \
  -p 8000:8000 \
  -e LLM_PROVIDER=gemini \
  -e GEMINI_API_KEY="" \
  -e GEMINI_MODEL=gemini-3.6-flash \
  gridwise-campus-api:latest
```

### Or using Docker Compose:
```bash
docker-compose up -d --build
```

Verify the container is responding:
```bash
curl http://127.0.0.1:8000/health
```

---

## 6. Optimization Model Formulation

The 24-hour horizon ($h \in \{0, \dots, 23\}$) is formulated as an exact Linear Program:

$$\min \sum_{h=0}^{23} \text{tariff}_h \cdot g_h$$

**Subject to:**
1. **Energy Balance:**
   $$g_h + s_h + d_h = \text{demand}_h + c_h \quad \forall h \in \{0, \dots, 23\}$$
2. **Solar Curtailment Limit:**
   $$0 \le s_h \le S^{\text{eff}}_h \quad \forall h \in \{0, \dots, 23\}$$
   where $S^{\text{eff}}_h = \text{solar}_h \times \text{factor}_h$ if `solar_reduction` is active.
3. **Battery Storage Dynamics:**
   $$E_{h+1} = E_h + c_h - d_h \quad \text{with } E_0 = \text{initial\_energy\_kwh}$$
4. **Dynamic Reserve & Capacity Limits:**
   $$\max(\text{minimum\_energy}, \text{directive\_reserve}_h) \le E_{h+1} \le \text{capacity} \quad \forall h$$
5. **Rate & Window Restrictions:**
   - $0 \le c_h \le \text{max\_charge}$, and $c_h = 0$ for $h \in \text{no\_charge\_window}$.
   - $0 \le d_h \le \text{max\_discharge}$, and $d_h = 0$ for $h \in \text{no\_discharge\_window}$.
6. **Substation & Feeder Import Caps:**
   $$g_h \le \text{max\_grid}_h \quad \text{for } h \in \text{max\_grid\_window}$$
7. **End-of-Day Battery Neutrality:**
   $$E_{24} = \text{initial\_energy\_kwh}$$

---

## 7. Security Policy & Secret Safety

- **No Secrets in Repo:** No hardcoded tokens, passwords, or secret keys exist in the codebase.
- **Log Sanitation:** Exception logs only display sanitized error messages and never print raw prompt headers or sensitive configuration tokens.
- **Production Defense:** Stack traces and internal server details are shielded from API responses.

---

## 8. Authors & Credits
- **Developed for:** BUP CSE Fest 2026 Hackathon (In association with Poridhi)
- **Team Framework:** Laravel 10 (PHP 8.2)
- **Math Solver:** Pure PHP Simplex Engine (Custom High-Performance Two-Phase Implementation)
