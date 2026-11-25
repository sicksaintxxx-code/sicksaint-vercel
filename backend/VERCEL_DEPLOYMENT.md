# Laravel on Vercel: Candidate Configuration and Verification Status

The repository proposes a Laravel 12 HTTP container using FrankenPHP in `Dockerfile.vercel` and `Caddyfile`. Root `vercel.json` declares a Next.js service rooted at `frontend/` and this container service rooted at `backend/`. Public routes are routed to Next.js. The Next.js server-side BFF calls Laravel using the private `LARAVEL_API_URL` service binding; browser requests should use the same-origin `/api/laravel/*` proxy.

The Laravel ID-token middleware, Firestore REST store, and API routes are present. Local Laravel unit/feature tests pass, but the BFF-to-Laravel-to-Firestore flow has not been exercised end-to-end. The Laravel service requires a private `FIREBASE_CREDENTIALS_JSON` or mounted `GOOGLE_APPLICATION_CREDENTIALS` value for live Firestore operations. No credential is available in this workspace.

## Platform compatibility

Vercel currently documents multi-service deployments and Docker/container-based functions, with capability and availability dependent on plan/account and beta status. References: [Vercel Services](https://vercel.com/docs/services) and [Dockerfile support](https://vercel.com/changelog/bring-your-dockerfile-to-vercel-functions).

This is a candidate platform configuration, not a verified deployment. Docker and Podman are unavailable locally; the Dockerfile was not built. The Vercel project API returned 403, so no project setting, entitlement, service binding, build log, container start, HTTP route, PHP extension, writable path, or cold start could be checked. Do not claim Laravel-on-Vercel compatibility for this project until a successful account-authorized preview build and API smoke test exist.

## Required validation before release

1. Confirm Services/container availability in the Vercel account and the expected configuration format for the project.
2. Build `backend/Dockerfile.vercel` with Docker/BuildKit; verify FrankenPHP listens on `$PORT`, Laravel boots, and writable cache/storage paths work.
3. Configure `APP_KEY`, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, `FIREBASE_PROJECT_ID=sicksaint`, and a server-only credential in the backend service. Do not pass service credentials to Next.js.
4. Verify the private `LARAVEL_API_URL` binding from a Next.js server function, then exercise Firebase bearer-token verification and a least-privilege Firestore request in staging.
5. Confirm Vercel container filesystem ephemerality and do not rely on local disk for durable uploads. No upload feature is currently identified.
6. Run API smoke tests, auth/admin role tests, CORS/origin checks, and failure/timeout behavior against the preview deployment before considering routing production traffic.

No deployment was attempted; no domain, production data, or Firebase rules were changed.
