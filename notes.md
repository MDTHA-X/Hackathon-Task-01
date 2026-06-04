# Project Notes

## Current State
- The project has a transcription page (`transcribe.php`) with browser and server-based (Deepgram) speech-to-text.
- It translates text from Bangla to English.

## Work Done
- Addressed the issue with inaccurate speech-to-text by updating the Deepgram model (e.g., from `nova-3-general` to `nova-2`, which is more stable for multi-language and auto-detection).
- Removed the manual English/Bangla language mode selection from the UI as it was deemed unnecessary.
- Configured the frontend and backend to automatically detect the spoken language.
- Set `Server Deepgram` as the default engine instead of `Browser speech`, allowing the app to successfully auto-detect language before transcription starts.
- Streamlined `transcription.js` and `api/transcribe.php` to rely on auto-language detection and removed redundant language label formatting functions.
- **Update**: Reverted the default engine back to `Browser speech` and restored the manual English/Bangla toggle at the user's request (due to invalid Deepgram credentials).
- Integrated the Google Gemini API to translate and correct Bangla transcripts into English more reliably.
- Commented out the `Server Deepgram` option from the UI to avoid confusion, fully relying on `Browser speech`.
