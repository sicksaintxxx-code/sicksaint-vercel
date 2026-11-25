# SICKSAINT

The existing Next.js magazine site and Admin Panel are in `frontend/`. The existing Laravel 12.73 API is in `backend/`. Firebase project `sicksaint` is the intended authentication and Firestore project; WordPress remains the editorial CMS.

## Current state

The working tree now routes sign-in and selected account/admin operations through Firebase Authentication, a Next.js same-origin BFF and Laravel's Firestore-backed API. It has not passed live Firebase or deployment checks. Do not deploy or treat the current tree as final; see [PROJECT_STATUS.md](PROJECT_STATUS.md) and [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md).

## Local development

1. Install Node dependencies with `npm ci` at the repository root and PHP dependencies with `composer install` under `backend/`.
2. Copy `frontend/.env.example` to `frontend/.env.local`, add the existing Firebase Web App settings privately, and set `LARAVEL_API_URL=http://127.0.0.1:8000`.
3. Copy `backend/.env.example` to `backend/.env`, generate a local `APP_KEY`, set `FIREBASE_PROJECT_ID=sicksaint`, and provide a server-side Firebase credential or use a local Firestore Emulator. Never put credentials in source or chat.
4. Start Laravel from `backend/` and Next.js from the repository root with `npm run dev`.

See [ENVIRONMENT_VARIABLES.md](ENVIRONMENT_VARIABLES.md), [FIREBASE_SETUP.md](FIREBASE_SETUP.md), [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md), and [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md). Keep `.env` files and local databases private. The tracked `frontend/sicksaint-store.json` contains subscriber emails and must not be uploaded or packaged.
