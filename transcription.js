const ui = {
  recordToggle: document.getElementById("recordToggle"),
  recordButtonText: document.getElementById("recordButtonText"),
  statusPill: document.getElementById("statusPill"),
  timerOutput: document.getElementById("timerOutput"),
  engineMode: document.getElementById("engineMode"),
  languageMode: document.getElementById("languageMode"),
  translateToggle: document.getElementById("translateToggle"),
  smartFormatToggle: document.getElementById("smartFormatToggle"),
  envWarning: document.getElementById("envWarning"),
  errorOutput: document.getElementById("errorOutput"),
  languageOutput: document.getElementById("languageOutput"),
  confidenceOutput: document.getElementById("confidenceOutput"),
  transcriptOutput: document.getElementById("transcriptOutput"),
  translationOutput: document.getElementById("translationOutput"),
  sourceOutput: document.getElementById("sourceOutput"),
  copyLatestBtn: document.getElementById("copyLatestBtn"),
  sessionCount: document.getElementById("sessionCount"),
  sessionText: document.getElementById("sessionText"),
  sessionList: document.getElementById("sessionList"),
  copySessionBtn: document.getElementById("copySessionBtn"),
  downloadSessionBtn: document.getElementById("downloadSessionBtn"),
  clearSessionBtn: document.getElementById("clearSessionBtn"),
};

const SESSION_KEY = "eraSpeechSessionEntries.v1";

const state = {
  recorder: null,
  recognition: null,
  stream: null,
  chunks: [],
  browserFinals: [],
  mimeType: "",
  startedAt: 0,
  timerId: 0,
  latest: null,
};

let sessionEntries = loadSessionEntries();

renderSession();
updateSecureContextWarning();

ui.recordToggle.addEventListener("click", () => {
  if (ui.recordToggle.dataset.state === "recording") {
    stopRecording();
    return;
  }

  startRecording();
});

ui.copyLatestBtn.addEventListener("click", () => {
  if (!state.latest) {
    setError("There is no latest transcript to copy yet.");
    return;
  }

  copyText(formatEntryText(state.latest, 1));
});

ui.copySessionBtn.addEventListener("click", () => {
  const text = buildSessionText();
  if (!text) {
    setError("There is no session text to copy yet.");
    return;
  }

  copyText(text);
});

ui.downloadSessionBtn.addEventListener("click", () => {
  const text = buildSessionText();
  if (!text) {
    setError("There is no session text to download yet.");
    return;
  }

  const blob = new Blob([text], { type: "text/plain;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = `speech-session-${Date.now()}.txt`;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
});

ui.clearSessionBtn.addEventListener("click", () => {
  sessionEntries = [];
  sessionStorage.removeItem(SESSION_KEY);
  renderSession();
  setStatus("Ready", "idle");
  setError("");
});

async function startRecording() {
  if (ui.engineMode.value === "browser") {
    startBrowserSpeech();
    return;
  }

  startServerRecording();
}

async function startServerRecording() {
  setError("");

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    setError("This browser cannot access the microphone.");
    return;
  }

  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      audio: {
        echoCancellation: true,
        noiseSuppression: true,
      },
    });

    const mimeType = pickMimeType();
    const recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);

    state.stream = stream;
    state.recorder = recorder;
    state.mimeType = mimeType || recorder.mimeType || "audio/webm";
    state.chunks = [];

    recorder.addEventListener("dataavailable", (event) => {
      if (event.data && event.data.size > 0) {
        state.chunks.push(event.data);
      }
    });

    recorder.addEventListener("stop", handleRecorderStop);
    recorder.start();

    state.startedAt = Date.now();
    state.timerId = window.setInterval(updateTimer, 250);
    updateTimer();
    setRecordingUi(true);
    setStatus("Recording", "recording");
  } catch (error) {
    setError("Microphone permission was denied or unavailable.");
    resetRecorderState();
  }
}

function stopRecording() {
  if (state.recognition) {
    stopBrowserSpeech();
    return;
  }

  if (!state.recorder || state.recorder.state === "inactive") {
    return;
  }

  setStatus("Processing", "processing");
  ui.recordToggle.disabled = true;
  state.recorder.stop();
}

function startBrowserSpeech() {
  setError("");

  const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!Recognition) {
    setError("Browser speech mode is not available in this browser. Try Server Deepgram with a valid key.");
    return;
  }

  const recognition = new Recognition();
  recognition.continuous = true;
  recognition.interimResults = true;
  recognition.lang = speechLanguageCode(ui.languageMode.value);

  state.recognition = recognition;
  state.browserFinals = [];
  state.startedAt = Date.now();

  recognition.addEventListener("start", () => {
    state.timerId = window.setInterval(updateTimer, 250);
    updateTimer();
    setRecordingUi(true);
    setStatus("Recording", "recording");
    ui.languageOutput.textContent = browserLanguageLabel(ui.languageMode.value);
    ui.confidenceOutput.textContent = "-";
    ui.sourceOutput.textContent = "Browser speech";
    ui.transcriptOutput.textContent = "Listening...";
    ui.translationOutput.textContent = "-";
  });

  recognition.addEventListener("result", (event) => {
    let interim = "";

    for (let index = event.resultIndex; index < event.results.length; index += 1) {
      const transcript = event.results[index][0]?.transcript?.trim() || "";
      if (!transcript) {
        continue;
      }

      if (event.results[index].isFinal) {
        state.browserFinals.push(transcript);
      } else {
        interim = `${interim} ${transcript}`.trim();
      }
    }

    const visibleText = [state.browserFinals.join(" "), interim]
      .filter(Boolean)
      .join(" ")
      .trim();
    ui.transcriptOutput.textContent = visibleText || "Listening...";
  });

  recognition.addEventListener("error", (event) => {
    const reason = event.error ? `Browser speech error: ${event.error}.` : "Browser speech stopped with an error.";
    setError(reason);
    finishBrowserSpeech(false);
  });

  recognition.addEventListener("end", () => {
    if (state.recognition) {
      finishBrowserSpeech(true);
    }
  });

  try {
    recognition.start();
  } catch (error) {
    setError("Browser speech could not start.");
    finishBrowserSpeech(false);
  }
}

function stopBrowserSpeech() {
  if (!state.recognition) {
    return;
  }

  setStatus("Processing", "processing");
  ui.recordToggle.disabled = true;
  state.recognition.stop();
}

async function finishBrowserSpeech(shouldSave) {
  const recognition = state.recognition;
  state.recognition = null;
  stopTimer();

  if (recognition) {
    recognition.onresult = null;
    recognition.onerror = null;
    recognition.onend = null;
  }

  const transcript = state.browserFinals.join(" ").trim();
  state.browserFinals = [];
  setRecordingUi(false);
  ui.recordToggle.disabled = false;

  if (!shouldSave) {
    setStatus("Ready", "idle");
    return;
  }

  if (!transcript) {
    setStatus("Ready", "idle");
    setError("No speech was detected.");
    return;
  }

  setStatus("Processing", "processing");
  const language = browserResultLanguage(ui.languageMode.value, transcript);
  const translation = await resolveBrowserEnglish(transcript, language);
  const result = {
    ok: true,
    timestamp: new Date().toISOString(),
    language,
    language_confidence: null,
    transcript,
    english: translation.english,
    translation_source: translation.source,
    fallback_used: false,
  };

  renderLatest(result);
  addSessionEntry(result);
  setStatus("Ready", "idle");
}

async function handleRecorderStop() {
  stopTracks();
  stopTimer();

  const blob = new Blob(state.chunks, { type: state.mimeType || "audio/webm" });
  resetRecorderState(false);

  if (blob.size < 512) {
    setRecordingUi(false);
    setStatus("Ready", "idle");
    setError("The recording was too short or empty.");
    return;
  }

  try {
    const result = await uploadRecording(blob);
    renderLatest(result);
    addSessionEntry(result);
    setStatus("Ready", "idle");
  } catch (error) {
    setError(error.message || "Transcription failed.");
  } finally {
    setRecordingUi(false);
    ui.recordToggle.disabled = false;
  }
}

async function uploadRecording(blob) {
  const formData = new FormData();
  formData.append("audio", blob, `recording-${Date.now()}.${extensionForMime(blob.type)}`);
  formData.append("language", ui.languageMode.value);
  formData.append("translate", ui.translateToggle.checked ? "true" : "false");
  formData.append("smart_format", ui.smartFormatToggle.checked ? "true" : "false");

  const response = await fetch("api/transcribe.php", {
    method: "POST",
    body: formData,
  });

  const data = await response.json().catch(() => null);
  if (!response.ok || !data || !data.ok) {
    throw new Error(data?.error || "The local transcription endpoint returned an error.");
  }

  return data;
}

async function resolveBrowserEnglish(transcript, language) {
  if (!ui.translateToggle.checked) {
    return { english: "", source: "disabled" };
  }

  if (language.startsWith("en")) {
    return { english: transcript, source: "browser_original" };
  }

  if (!language.startsWith("bn") && !containsBanglaScript(transcript)) {
    return { english: transcript, source: "browser_untranslated" };
  }

  try {
    const response = await fetch("api/translate.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ text: transcript, source: "bn", target: "en" }),
    });
    const data = await response.json().catch(() => null);

    if (response.ok && data?.ok && data.english) {
      return { english: data.english, source: "browser_translation" };
    }
  } catch (error) {
    // The original transcript is still useful when translation is unavailable.
  }

  return { english: transcript, source: "browser_translation_unavailable" };
}

function renderLatest(result) {
  state.latest = result;
  ui.languageOutput.textContent = result.language || "Unknown";
  ui.confidenceOutput.textContent = formatConfidence(result.language_confidence);
  ui.transcriptOutput.textContent = result.transcript || "No speech detected.";
  ui.translationOutput.textContent = result.english || "-";
  ui.sourceOutput.textContent = sourceLabel(result.translation_source, result.fallback_used);
}

function addSessionEntry(result) {
  if (!result.transcript && !result.english) {
    return;
  }

  const entry = {
    id:
      typeof crypto !== "undefined" && crypto.randomUUID
        ? crypto.randomUUID()
        : String(Date.now()),
    timestamp: result.timestamp || new Date().toISOString(),
    language: result.language || "",
    confidence: result.language_confidence,
    transcript: result.transcript || "",
    english: result.english || "",
    source: result.translation_source || "none",
    fallbackUsed: Boolean(result.fallback_used),
  };

  sessionEntries.unshift(entry);
  saveSessionEntries(sessionEntries);
  renderSession();
}

function renderSession() {
  ui.sessionCount.textContent = `${sessionEntries.length} ${sessionEntries.length === 1 ? "entry" : "entries"}`;
  ui.sessionText.value = buildSessionText();
  ui.sessionList.innerHTML = "";

  sessionEntries.forEach((entry, index) => {
    ui.sessionList.appendChild(createSessionItem(entry, sessionEntries.length - index));
  });
}

function createSessionItem(entry, number) {
  const article = document.createElement("article");
  article.className = "session-item";

  const meta = document.createElement("div");
  meta.className = "session-meta";
  meta.textContent = `${number}. ${formatTimestamp(entry.timestamp)} | ${entry.language || "Unknown"}`;

  const transcript = document.createElement("p");
  transcript.className = "session-transcript";
  transcript.textContent = entry.transcript || "-";

  const english = document.createElement("p");
  english.className = "session-english";
  english.textContent = entry.english ? `English: ${entry.english}` : "English: -";

  article.appendChild(meta);
  article.appendChild(transcript);
  article.appendChild(english);
  return article;
}

function buildSessionText() {
  return sessionEntries
    .slice()
    .reverse()
    .map((entry, index) => formatEntryText(entry, index + 1))
    .join("\n\n");
}

function formatEntryText(entry, number) {
  const lines = [
    `#${number} ${formatTimestamp(entry.timestamp)}`,
    `Language: ${entry.language || "Unknown"}`,
    `Transcript: ${entry.transcript || "-"}`,
  ];

  if (entry.english) {
    lines.push(`English: ${entry.english}`);
  }

  return lines.join("\n");
}

function loadSessionEntries() {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch (error) {
    return [];
  }
}

function saveSessionEntries(entries) {
  sessionStorage.setItem(SESSION_KEY, JSON.stringify(entries));
}

function pickMimeType() {
  const candidates = [
    "audio/webm;codecs=opus",
    "audio/webm",
    "audio/ogg;codecs=opus",
    "audio/ogg",
    "audio/mp4",
  ];

  if (!window.MediaRecorder || !MediaRecorder.isTypeSupported) {
    return "";
  }

  return candidates.find((type) => MediaRecorder.isTypeSupported(type)) || "";
}

function extensionForMime(mimeType) {
  if (mimeType.includes("ogg")) {
    return "ogg";
  }
  if (mimeType.includes("mp4")) {
    return "m4a";
  }
  if (mimeType.includes("wav")) {
    return "wav";
  }
  return "webm";
}

function setRecordingUi(isRecording) {
  ui.recordToggle.dataset.state = isRecording ? "recording" : "idle";
  ui.recordToggle.setAttribute("aria-pressed", isRecording ? "true" : "false");
  ui.recordButtonText.textContent = isRecording ? "Stop recording" : "Start recording";
}

function setStatus(text, mode) {
  ui.statusPill.textContent = text;
  ui.statusPill.dataset.mode = mode;
}

function setError(message) {
  ui.errorOutput.textContent = message || "";
  ui.errorOutput.style.display = message ? "block" : "none";
  if (message) {
    setStatus("Error", "error");
  }
}

function updateTimer() {
  const elapsed = Math.max(0, Date.now() - state.startedAt);
  const totalSeconds = Math.floor(elapsed / 1000);
  const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, "0");
  const seconds = String(totalSeconds % 60).padStart(2, "0");
  ui.timerOutput.textContent = `${minutes}:${seconds}`;
}

function stopTimer() {
  window.clearInterval(state.timerId);
  state.timerId = 0;
}

function stopTracks() {
  if (state.stream) {
    state.stream.getTracks().forEach((track) => track.stop());
  }
}

function resetRecorderState(clearChunks = true) {
  state.recorder = null;
  state.stream = null;
  if (clearChunks) {
    state.chunks = [];
  }
}

function formatConfidence(value) {
  if (typeof value !== "number") {
    return "-";
  }

  return `${Math.round(value * 100)}%`;
}

function sourceLabel(source, fallbackUsed) {
  const labels = {
    original: "Original English",
    translation: "Translated to English",
    translation_unavailable: "Translation unavailable",
    untranslated: "Original transcript",
    disabled: "English output off",
    browser_original: "Browser speech English",
    browser_translation: "Browser speech translated",
    browser_translation_unavailable: "Browser translation unavailable",
    browser_untranslated: "Browser speech transcript",
    none: "-",
  };

  const base = labels[source] || source || "-";
  return fallbackUsed ? `${base} with Bangla retry` : base;
}

function speechLanguageCode(languageMode) {
  if (languageMode === "en") {
    return "en-US";
  }

  return "bn-BD";
}

function browserLanguageLabel(languageMode) {
  if (languageMode === "en") {
    return "en";
  }

  if (languageMode === "auto") {
    return "bn-BD";
  }

  return "bn";
}

function browserResultLanguage(languageMode, transcript) {
  if (languageMode === "en") {
    return "en";
  }

  if (containsBanglaScript(transcript)) {
    return "bn";
  }

  return languageMode === "auto" ? "bn-BD" : languageMode;
}

function containsBanglaScript(text) {
  return /[\u0980-\u09FF]/u.test(text);
}

function formatTimestamp(timestamp) {
  const date = new Date(timestamp);
  if (Number.isNaN(date.getTime())) {
    return timestamp || "";
  }

  return date.toLocaleString();
}

async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    setStatus("Copied", "idle");
    setError("");
  } catch (error) {
    const textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.setAttribute("readonly", "");
    textarea.style.position = "absolute";
    textarea.style.left = "-9999px";
    document.body.appendChild(textarea);
    textarea.select();

    const copied = document.execCommand("copy");
    textarea.remove();
    setStatus(copied ? "Copied" : "Copy failed", copied ? "idle" : "error");
  }
}

function updateSecureContextWarning() {
  const host = window.location.hostname;
  const localHost = host === "localhost" || host === "127.0.0.1" || host === "";

  if (!window.isSecureContext && !localHost) {
    ui.envWarning.textContent = "Microphone access needs HTTPS or localhost.";
    ui.envWarning.style.display = "block";
  }
}
