<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Transcribe | Era Hackathon</title>
    <link rel="icon" type="image/png" href="favicon.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="styles.css" />
    <script src="transcription.js" defer></script>
    <script src="medical-history.js" defer></script>
  </head>
  <body>
    <header class="topbar">
      <a class="brand" href="index.php">Era Hackathon</a>
      <nav class="nav-links" aria-label="Primary">
        <a href="index.php">Home</a>
      </nav>
    </header>

    <main class="transcribe-shell">
      <section class="tool-panel recorder-panel" aria-labelledby="transcribeTitle">
        <div>
          <p class="eyebrow">Transcribe</p>
          <h1 id="transcribeTitle">Speech session</h1>
        </div>

        <div class="record-row">
          <button
            id="recordToggle"
            class="record-button"
            type="button"
            data-state="idle"
            aria-pressed="false"
          >
            <span class="record-dot" aria-hidden="true"></span>
            <span id="recordButtonText">Start recording</span>
          </button>
          <span id="statusPill" class="status-pill" data-mode="idle">Ready</span>
          <span id="timerOutput" class="timer">00:00</span>
        </div>

        <div class="settings-grid" aria-label="Recording settings">
          <label>
            <span>Engine</span>
            <select id="engineMode">
              <option value="browser" selected>Browser speech</option>
              <!-- <option value="server">Server Deepgram</option> -->
            </select>
          </label>
          <label>
            <span>Language</span>
            <select id="languageMode">
              <option value="bn" selected>Bangla</option>
              <option value="en">English</option>
              <option value="auto">Auto detect</option>
            </select>
          </label>
          <label class="check-row">
            <input type="checkbox" id="translateToggle" checked />
            <span>English output</span>
          </label>
          <label class="check-row">
            <input type="checkbox" id="smartFormatToggle" checked />
            <span>Smart format</span>
          </label>
        </div>

        <div id="envWarning" class="warning" role="status"></div>
        <div id="errorOutput" class="error" role="alert"></div>
      </section>

      <section class="tool-panel result-panel" aria-labelledby="latestTitle">
        <div class="section-heading">
          <h2 id="latestTitle">Latest result</h2>
          <button id="copyLatestBtn" class="button quiet" type="button">Copy</button>
        </div>

        <dl class="result-list">
          <div>
            <dt>Language</dt>
            <dd id="languageOutput">-</dd>
          </div>
          <div>
            <dt>Confidence</dt>
            <dd id="confidenceOutput">-</dd>
          </div>
          <div class="wide">
            <dt>Transcript</dt>
            <dd id="transcriptOutput">Start recording to see text here.</dd>
          </div>
          <div class="wide">
            <dt>English</dt>
            <dd id="translationOutput">-</dd>
          </div>
          <div>
            <dt>Source</dt>
            <dd id="sourceOutput">-</dd>
          </div>
        </dl>
      </section>

      <section class="tool-panel session-panel" aria-labelledby="sessionTitle">
        <div class="section-heading">
          <div>
            <h2 id="sessionTitle">Session text</h2>
            <p id="sessionCount" class="muted">0 entries</p>
          </div>
          <div class="action-row">
            <button id="copySessionBtn" class="button quiet" type="button">Copy</button>
            <button id="downloadSessionBtn" class="button quiet" type="button">Download</button>
            <button id="clearSessionBtn" class="button danger" type="button">Clear</button>
          </div>
        </div>

        <textarea
          id="sessionText"
          class="session-textarea"
          readonly
          placeholder="Your session transcripts will appear here."
        ></textarea>
        <div id="sessionList" class="session-list" aria-live="polite"></div>
      </section>

      <div class="medical-area">
        <!-- Step 1: Image Upload -->
        <section id="stepUpload" class="tool-panel step-panel">
          <p class="eyebrow">Medical History</p>
          <h2>Upload Documents</h2>
          <p class="lead">Upload photos of prescriptions, reports, or previous medical history (Max 3 files, 10MB each).</p>
          
          <div id="dropZone" class="file-upload-area">
            <p>Drag & drop images here or click to browse</p>
            <input type="file" id="fileInput" multiple accept="image/*" class="hidden" />
          </div>
          
          <div id="previewList" class="preview-list"></div>
          <div id="uploadError" class="error"></div>
          
          <div class="hero-actions">
            <button type="button" id="btnExtract" class="button primary" disabled>Extract Medical History</button>
          </div>
        </section>

        <!-- Step 2: Vitals & Extracted History -->
        <section id="stepVitals" class="tool-panel step-panel hidden">
          <p class="eyebrow">Medical History</p>
          <h2>Patient Vitals & History</h2>
          
          <div class="form-group" style="margin-top: 16px;">
            <label>Extracted Medical History</label>
            <textarea id="extractedHistory" rows="5" placeholder="Extracted text will appear here..."></textarea>
          </div>

          <h3 style="margin-top: 32px; font-size: 1.1rem;">Enter Patient Vitals</h3>
          <form id="vitalsForm" class="vitals-grid">
            <div class="form-group">
              <label for="vitalBp">Blood Pressure (mmHg)</label>
              <input type="text" id="vitalBp" placeholder="e.g. 120/80" required />
            </div>
            <div class="form-group">
              <label for="vitalHr">Heart Rate (bpm)</label>
              <input type="number" id="vitalHr" placeholder="e.g. 75" required />
            </div>
            <div class="form-group">
              <label for="vitalTemp">Temperature (°F)</label>
              <input type="number" step="0.1" id="vitalTemp" placeholder="e.g. 98.6" required />
            </div>
            <div class="form-group">
              <label for="vitalSpo2">Oxygen Saturation (%)</label>
              <input type="number" id="vitalSpo2" placeholder="e.g. 98" required />
            </div>
            <div class="form-group">
              <label for="vitalBg">Blood Glucose (mg/dL)</label>
              <input type="number" id="vitalBg" placeholder="e.g. 100" />
            </div>
          </form>
          
          <div id="analyzeError" class="error"></div>

          <div class="hero-actions">
            <button type="button" id="btnAnalyze" class="button primary">Analyze & Generate Triage</button>
          </div>
        </section>

        <!-- Step 3: Digital Report -->
        <section id="stepReport" class="tool-panel step-panel hidden">
          <p class="eyebrow">Digital Report</p>
          <div class="digital-report">
            <div class="report-header">
              <div>
                <h2>Patient Triage Report</h2>
                <p class="muted" id="reportDate"></p>
              </div>
              <div id="reportTriageBadge" class="triage-badge triage-Unknown">Unknown</div>
            </div>

            <div class="report-section">
              <h3>Patient Demographics</h3>
              <div class="report-grid">
                <div class="report-item">
                  <strong>Name</strong>
                  <span id="reportName">-</span>
                </div>
                <div class="report-item">
                  <strong>Age / Sex</strong>
                  <span id="reportAgeSex">-</span>
                </div>
                <div class="report-item">
                  <strong>Contact</strong>
                  <span id="reportContact">-</span>
                </div>
              </div>
            </div>

            <div class="report-section">
              <h3>Vitals</h3>
              <div class="report-grid" id="reportVitalsGrid">
                <!-- Populated by JS -->
              </div>
            </div>

            <div class="report-section">
              <h3>Triage Reasoning</h3>
              <div class="report-text" id="reportReasoning">-</div>
            </div>

            <div class="report-section">
              <h3>Differential Diagnoses</h3>
              <div class="report-text" id="reportDiagnoses">-</div>
            </div>

            <div class="report-section">
              <h3>Immediate First-Aid</h3>
              <div class="report-text" id="reportFirstAid">-</div>
            </div>

            <div class="report-section">
              <h3>Specialist Recommendation</h3>
              <div class="report-text" id="reportSpecialist">-</div>
            </div>
            
            <div class="tts-controls">
              <button type="button" id="btnSpeakEn" class="button quiet">🔊 Read Summary (English)</button>
              <button type="button" id="btnSpeakBn" class="button quiet">🔊 Read Summary (Bangla)</button>
            </div>
          </div>
        </section>
      </div>
    </main>
  </body>
</html>
