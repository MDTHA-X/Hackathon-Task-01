<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Era Hackathon Speech Lab</title>
    <link rel="icon" type="image/png" href="favicon.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="styles.css" />
  </head>
  <body>
    <header class="topbar">
      <a class="brand" href="index.php">Era Hackathon</a>
      <nav class="nav-links" aria-label="Primary">
        <a href="transcribe.php">Transcribe</a>
      </nav>
    </header>

    <main class="home-shell">
      <section class="home-hero" aria-labelledby="homeTitle">
        <p class="eyebrow">Speech Lab</p>
        <h1 id="homeTitle">Local speech sessions with visible text history.</h1>
        <p class="lead">
          Record from the microphone, transcribe through the local PHP proxy,
          and keep every result visible in the browser for the current session.
        </p>
        <div class="hero-actions">
          <a class="button primary" href="transcribe.php">Open Transcribe Page</a>
        </div>
      </section>

      <section class="home-panel" aria-label="Current setup">
        <div class="metric">
          <span>API</span>
          <strong>Server-side PHP</strong>
        </div>
        <div class="metric">
          <span>Speech</span>
          <strong>Deepgram</strong>
        </div>
        <div class="metric">
          <span>Session text</span>
          <strong>Browser storage</strong>
        </div>
      </section>
    </main>
  </body>
</html>
