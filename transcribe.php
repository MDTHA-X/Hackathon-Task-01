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
    </main>
  </body>
</html>
