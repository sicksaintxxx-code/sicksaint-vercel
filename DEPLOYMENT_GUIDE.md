# SICKSAINT Deployment Guide

**Current status: deployment preparation only.** No Vercel project, domain, production data, or Firestore rules were changed.

## Repository layout

- `frontend/`: existing Next.js 15 site, Admin Panel and same-origin Laravel API proxy.
- `backend/`: existing Laravel 12.73 API and Firestore REST adapter.
- `docs/`, Firebase rules/index files, root scripts and package manifests remain in the monorepo.

## Vercel compatibility finding

Vercel currently documents the `services` configuration for a Next.js service plus an HTTP container service. Its container runtime can build an OCI image from `Dockerfile.vercel`; Laravel can therefore be attempted without replacing it. This is not a native PHP runtime. Services access is currently Private Beta/request-based, and the container runtime is beta; the account/project must have access. See [Vercel Services](https://vercel.com/docs/services) and [Dockerfile support for Vercel Functions](https://vercel.com/changelog/bring-your-dockerfile-to-vercel-functions).

The repository has `backend/Dockerfile.vercel` (FrankenPHP/PHP 8.4) and a matching Caddy listener using `$PORT`. The existing `vercel.json` describes frontend and backend services with a private `LARAVEL_API_URL` binding; the Next.js BFF calls Laravel through that binding. Public routing sends requests to Next.js. Docker/Podman are unavailable in the current workstation, and Vercel project access returned 403 for team scope `sicksaint`, so no image build, project setting, or live service binding was checked. The root config and app-to-service call sites were inspected; this remains a documented candidate architecture, not a proven deployment for this project/account.

Laravel's writable storage, `APP_KEY`, Firebase service credentials, durable uploaded media and Firestore network/IAM behavior must be configured and tested. The container filesystem is ephemeral; local uploads are not durable. Storage upload integration is incomplete. If Services/container beta is unavailable, this repository has no verified alternative that satisfies the no-external-host requirement; stop and use Vercel's account support/UI to enable the documented capability before attempting deployment.

## Configuration required before deployment

1. Create/connect the `sicksaint-production` GitHub repository using the clean-export procedure below. Do not upload the working tree as-is.
2. In Vercel, create a project linked to that repository and choose Services framework if prompted; configure `frontend/` as the Next.js service root and `backend/` as the container service root/entrypoint. Confirm that `Dockerfile.vercel` is recognized.
3. Set frontend public Firebase Web App values in the frontend service environment. Set Laravel `APP_KEY`, `APP_URL`, `FRONTEND_URL`, `FIREBASE_PROJECT_ID=sicksaint`, and a secure server-side Firestore credential in the backend service environment. `LARAVEL_API_URL` should be supplied by the private service binding, not copied from a public URL.
4. Set `NEXT_PUBLIC_SITE_URL` and WordPress API endpoints only after confirming the intended CMS. The local frontend build completed, but WordPress `/posts` and `/categories` requests failed.
5. Local Firebase ID-token RSA verification tests and Firestore Emulator rule tests now pass. Before deployment, still verify a real authorized Firebase sign-in, Laravel server credentials/IAM, user mapping, live Firestore flow, and a successful Vercel container build/service smoke test in preview/staging.

See [ENVIRONMENT_VARIABLES.md](ENVIRONMENT_VARIABLES.md) and [FIREBASE_SETUP.md](FIREBASE_SETUP.md). No deployment has been performed.

## GitHub UI steps (repository connector unavailable)

Create a **private** repository named `sicksaint-production` in GitHub. Do not initialize it with a README, license or template `.gitignore`. A sanitized Git-ready folder has been prepared at `C:\Users\ahsan\Desktop\sicksaint-production`; it is not production-cleared and contains the current blocked status. The sensitive-data scan found no private-key/token pattern hits and no forbidden credential/database files in this export. The old subscriber JSON is absent, and ignore checks confirm it would be excluded. `.env` files, local SQLite/database files, service-account keys, dependencies, `.next`, Laravel `vendor`, logs and build output are ignored. The folder includes `.env.example`, lockfiles, source, rules, docs and tests. No GitHub repository was created or pushed.

## Checks to run before an owner-led deployment

```powershell
cd frontend
npm ci
npm run build

cd ..\backend
composer install --no-interaction --prefer-dist
php artisan test
php artisan route:list --path=api

cd ..
npm ci
npm run test:firestore-rules  # Java required
docker build -f backend/Dockerfile.vercel backend
```

Do not run production migrations or seeders as a deployment check. Verify every account/media operation against a staging Firebase project or approved emulator setup before production cutover.
