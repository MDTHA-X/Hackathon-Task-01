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
        <p class="eyebrow">Patient Intake</p>
        <h1 id="homeTitle">Capture patient details and vitals securely.</h1>
        <p class="lead">
          Record demographics, transcribe speech, or use advanced AI to extract medical history from documents and analyze vitals.
        </p>

        <form id="demographicsForm" class="demographics-form">
          <div class="form-group">
            <label for="patientName">Full Name</label>
            <input type="text" id="patientName" name="patientName" required placeholder="e.g. John Doe" />
          </div>
          <div class="form-group-row">
            <div class="form-group">
              <label for="patientAge">Age</label>
              <input type="number" id="patientAge" name="patientAge" required min="0" placeholder="Years" />
            </div>
            <div class="form-group">
              <label for="patientSex">Sex</label>
              <select id="patientSex" name="patientSex" required>
                <option value="">Select...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="patientContact">Contact Number</label>
            <input type="tel" id="patientContact" name="patientContact" required placeholder="Phone number" />
          </div>
          
          <div class="hero-actions">
            <button type="button" id="btnTranscribe" class="button primary">Open Transcribe Page</button>
            <button type="button" id="btnMedicalHistory" class="button primary">Medical History & Triage</button>
          </div>
        </form>
      </section>

      <script>
        document.addEventListener('DOMContentLoaded', () => {
          const form = document.getElementById('demographicsForm');
          const btnTranscribe = document.getElementById('btnTranscribe');
          const btnMedicalHistory = document.getElementById('btnMedicalHistory');

          // Pre-fill if exists
          const savedStr = localStorage.getItem('patientDemographics');
          if (savedStr) {
            try {
              const saved = JSON.parse(savedStr);
              document.getElementById('patientName').value = saved.name || '';
              document.getElementById('patientAge').value = saved.age || '';
              document.getElementById('patientSex').value = saved.sex || '';
              document.getElementById('patientContact').value = saved.contact || '';
            } catch (e) {}
          }

          function saveDemographics() {
            if (!form.checkValidity()) {
              form.reportValidity();
              return false;
            }
            const data = {
              name: document.getElementById('patientName').value,
              age: document.getElementById('patientAge').value,
              sex: document.getElementById('patientSex').value,
              contact: document.getElementById('patientContact').value,
            };
            localStorage.setItem('patientDemographics', JSON.stringify(data));
            return true;
          }

          btnTranscribe.addEventListener('click', () => {
            if (saveDemographics()) {
              window.location.href = 'transcribe.php';
            }
          });

          btnMedicalHistory.addEventListener('click', () => {
            if (saveDemographics()) {
              window.location.href = 'transcribe.php#stepUpload';
            }
          });
        });
      </script>

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
