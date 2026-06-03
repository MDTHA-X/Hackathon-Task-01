# Hackathon Task 01

Local XAMPP/PHP speech transcription demo with a main page, a transcribe page, browser speech fallback, server-side Deepgram upload, Bangla-to-English translation, and visible session text in the browser.

## Run Locally

1. Place this folder under XAMPP `htdocs`.
2. Start Apache in XAMPP.
3. Open `http://localhost/1/`.

## Deepgram Setup

Browser speech mode works without a Deepgram key in supported browsers. For the server Deepgram mode:

1. Copy `config.local.example.php` to `config.local.php`.
2. Put your Deepgram API key in `config.local.php`.
3. Keep `config.local.php` private; it is ignored by Git.
