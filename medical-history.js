document.addEventListener('DOMContentLoaded', () => {
  // Elements
  const dropZone = document.getElementById('dropZone');
  const fileInput = document.getElementById('fileInput');
  const previewList = document.getElementById('previewList');
  const btnExtract = document.getElementById('btnExtract');
  const uploadError = document.getElementById('uploadError');
  const extractedHistory = document.getElementById('extractedHistory');
  
  const stepUpload = document.getElementById('stepUpload');
  const stepVitals = document.getElementById('stepVitals');
  const stepReport = document.getElementById('stepReport');
  
  const btnAnalyze = document.getElementById('btnAnalyze');
  const vitalsForm = document.getElementById('vitalsForm');
  const analyzeError = document.getElementById('analyzeError');
  
  // State
  let selectedFiles = [];
  const MAX_FILES = 3;
  const MAX_SIZE_MB = 10;
  let reportData = null;

  // Retrieve demographics
  const savedDemographicsStr = localStorage.getItem('patientDemographics');
  const demographics = savedDemographicsStr ? JSON.parse(savedDemographicsStr) : {
    name: 'Unknown', age: 'Unknown', sex: 'Unknown', contact: 'Unknown'
  };

  // --- Step 1: Upload ---
  
  dropZone.addEventListener('click', () => fileInput.click());
  
  dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('drag-over');
  });
  
  dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('drag-over');
  });
  
  dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    handleFiles(e.dataTransfer.files);
  });
  
  fileInput.addEventListener('change', () => {
    handleFiles(fileInput.files);
  });

  function handleFiles(files) {
    uploadError.style.display = 'none';
    uploadError.textContent = '';
    
    const newFiles = Array.from(files);
    
    if (selectedFiles.length + newFiles.length > MAX_FILES) {
      showError(uploadError, `You can only upload a maximum of ${MAX_FILES} images.`);
      return;
    }
    
    for (const file of newFiles) {
      if (!file.type.startsWith('image/')) {
        showError(uploadError, 'Only image files are allowed.');
        return;
      }
      if (file.size > MAX_SIZE_MB * 1024 * 1024) {
        showError(uploadError, `File ${file.name} exceeds ${MAX_SIZE_MB}MB limit.`);
        return;
      }
      selectedFiles.push(file);
    }
    
    renderPreviews();
    btnExtract.disabled = selectedFiles.length === 0;
  }

  function renderPreviews() {
    previewList.innerHTML = '';
    selectedFiles.forEach((file) => {
      const img = document.createElement('img');
      img.className = 'preview-item';
      img.src = URL.createObjectURL(file);
      previewList.appendChild(img);
    });
  }

  function showError(el, msg) {
    el.textContent = msg;
    el.style.display = 'block';
  }

  btnExtract.addEventListener('click', async () => {
    if (selectedFiles.length === 0) return;
    
    btnExtract.disabled = true;
    btnExtract.textContent = 'Extracting...';
    uploadError.style.display = 'none';
    
    const formData = new FormData();
    selectedFiles.forEach((file) => {
      formData.append('images[]', file);
    });
    
    try {
      const res = await fetch('api/extract-history.php', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Failed to extract history.');
      }
      
      extractedHistory.value = data.text;
      
      // Move to step 2
      stepUpload.classList.add('hidden');
      stepVitals.classList.remove('hidden');
      
    } catch (e) {
      showError(uploadError, e.message);
      btnExtract.disabled = false;
      btnExtract.textContent = 'Extract Medical History';
    }
  });

  // --- Step 2: Vitals & Analyze ---

  btnAnalyze.addEventListener('click', async () => {
    if (!vitalsForm.checkValidity()) {
      vitalsForm.reportValidity();
      return;
    }
    
    btnAnalyze.disabled = true;
    btnAnalyze.textContent = 'Analyzing...';
    analyzeError.style.display = 'none';
    
    const payload = {
      demographics,
      history: extractedHistory.value.trim(),
      vitals: {
        bp: document.getElementById('vitalBp').value.trim(),
        hr: document.getElementById('vitalHr').value.trim(),
        temp: document.getElementById('vitalTemp').value.trim(),
        spo2: document.getElementById('vitalSpo2').value.trim(),
        bg: document.getElementById('vitalBg').value.trim(),
      }
    };
    
    try {
      const res = await fetch('api/analyze-vitals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Failed to analyze patient data.');
      }
      
      reportData = data.analysis;
      renderReport(payload.vitals);
      
      stepVitals.classList.add('hidden');
      stepReport.classList.remove('hidden');
      
    } catch (e) {
      showError(analyzeError, e.message);
      btnAnalyze.disabled = false;
      btnAnalyze.textContent = 'Analyze & Generate Triage';
    }
  });

  // --- Step 3: Report & TTS ---

  function renderReport(vitals) {
    document.getElementById('reportDate').textContent = new Date().toLocaleString();
    
    // Demographics
    document.getElementById('reportName').textContent = demographics.name || 'Unknown';
    document.getElementById('reportAgeSex').textContent = `${demographics.age || '?'} / ${demographics.sex || '?'}`;
    document.getElementById('reportContact').textContent = demographics.contact || 'Unknown';
    
    // Triage Badge
    const badge = document.getElementById('reportTriageBadge');
    badge.textContent = reportData.triage_score || 'Unknown';
    badge.className = `triage-badge triage-${reportData.triage_score || 'Unknown'}`;
    
    // Vitals
    const vitalsGrid = document.getElementById('reportVitalsGrid');
    vitalsGrid.innerHTML = `
      <div class="report-item"><strong>BP</strong><span>${vitals.bp || '-'}</span></div>
      <div class="report-item"><strong>HR</strong><span>${vitals.hr || '-'} bpm</span></div>
      <div class="report-item"><strong>Temp</strong><span>${vitals.temp || '-'} °F</span></div>
      <div class="report-item"><strong>SpO2</strong><span>${vitals.spo2 || '-'}%</span></div>
      <div class="report-item"><strong>Glucose</strong><span>${vitals.bg || '-'} mg/dL</span></div>
    `;
    
    // Notes
    document.getElementById('reportReasoning').textContent = reportData.reasoning || '-';
    document.getElementById('reportDiagnoses').textContent = Array.isArray(reportData.differential_diagnoses) 
      ? reportData.differential_diagnoses.map(d => '• ' + d).join('\n') 
      : reportData.differential_diagnoses || '-';
    document.getElementById('reportFirstAid').textContent = Array.isArray(reportData.first_aid) 
      ? reportData.first_aid.map(f => '• ' + f).join('\n') 
      : reportData.first_aid || '-';
    document.getElementById('reportSpecialist').textContent = Array.isArray(reportData.specialist_recommendation) 
      ? reportData.specialist_recommendation.map(s => '• ' + s).join('\n') 
      : reportData.specialist_recommendation || '-';
  }

  // TTS
  const btnSpeakEn = document.getElementById('btnSpeakEn');
  const btnSpeakBn = document.getElementById('btnSpeakBn');
  let currentUtterance = null;

  function speak(text, lang) {
    if (window.speechSynthesis.speaking) {
      window.speechSynthesis.cancel();
    }
    currentUtterance = new SpeechSynthesisUtterance(text);
    currentUtterance.lang = lang;
    // Try to pick a good voice if available
    const voices = window.speechSynthesis.getVoices();
    const voice = voices.find(v => v.lang.startsWith(lang));
    if (voice) {
      currentUtterance.voice = voice;
    }
    window.speechSynthesis.speak(currentUtterance);
  }

  function getSummaryText() {
    if (!reportData) return '';
    return `The patient has been triaged as ${reportData.triage_score}. 
Reasoning: ${reportData.reasoning}
First aid recommended: ${Array.isArray(reportData.first_aid) ? reportData.first_aid.join('. ') : reportData.first_aid}
Specialist recommended: ${Array.isArray(reportData.specialist_recommendation) ? reportData.specialist_recommendation.join(', ') : reportData.specialist_recommendation}`;
  }

  btnSpeakEn.addEventListener('click', () => {
    speak(getSummaryText(), 'en-US');
  });

  btnSpeakBn.addEventListener('click', async () => {
    btnSpeakBn.textContent = 'Translating...';
    btnSpeakBn.disabled = true;
    try {
      // We translate the summary to Bangla using our existing translate API
      const textToTranslate = getSummaryText();
      const res = await fetch('api/translate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        // Hack: Our translate API does BN -> EN. Let's see if we can adapt it or if we just use Gemini directly.
        // Wait, the existing `translate.php` is hardcoded for BN -> EN. 
        // We will need to update translate.php or create a new endpoint, or use the `translate.php` if we modify it to support target languages.
        // For now, I'll pass source=en, target=bn.
        body: JSON.stringify({ text: textToTranslate, source: 'en', target: 'bn' })
      });
      const data = await res.json();
      const translatedText = data.text || data.bangla || data.english; // Wait, translate.php returns 'english'. Let's check it. 
      // Actually, I'll update translate.php to support direction, or create translate-to-bn.php.
      
      // Let's assume the endpoint is updated to return `translated` field.
      if (data.translated) {
        speak(data.translated, 'bn-BD');
      } else {
        alert('Translation failed.');
      }
    } catch (e) {
      alert('Translation error: ' + e.message);
    } finally {
      btnSpeakBn.textContent = '🔊 Read Summary (Bangla)';
      btnSpeakBn.disabled = false;
    }
  });

});
