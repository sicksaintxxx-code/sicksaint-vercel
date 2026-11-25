# Firebase Setup and Verification

## Existing project

**Read-only Firebase verification (2026-11-24):** Firebase CLI 15.37.0 reported the authenticated account `sicksaintxxx@gmail.com`; `projects:list` confirmed project `sicksaint`, project number `66041958725`, state `ACTIVE`. One registered Web App was returned; its project ID, app ID, auth domain, bucket, sender ID and measurement ID match the existing supplied Web App configuration. The API key was present in the returned SDK configuration but was deliberately suppressed from output. The default Firestore database is `STANDARD`, `FIRESTORE_NATIVE`, region `nam5`. Delete protection and point-in-time recovery are both disabled. No collections/documents, Authentication user records, or production data were read.

The live Firestore rules were retrieved read-only for project `sicksaint`. They deny all client reads and writes. This is semantically consistent with the checked-in `firestore.rules`; it was not published or modified. The authenticated CLI reported no deployed composite indexes. The checked-in index file has notification/submission indexes, but Laravel also queries reviews by `submissionId` ordered by `createdAt`, for which no index is declared locally. Index/query behavior has not been exercised against production. Firestore's query model can require composite indexes for equality filters combined with an order field; resolve and test this in the emulator before preparing any index deployment.

The Firebase project also has a Realtime Database instance listed in project resources, but the frontend uses only Auth, Firestore initialization and Storage initialization; no Realtime Database SDK calls or `databaseURL` use were found. Do not introduce RTDB into the application. The repo has no root `firebase.json` or `.firebaserc`; only `firebase.emulator.json` maps local rules/indexes and emulator ports. Do not use that emulator config for a production deploy without a deliberate reviewed production config.

The application targets the existing Firebase project `sicksaint`. Browser configuration is supplied through the `NEXT_PUBLIC_FIREBASE_*` variables documented in `ENVIRONMENT_VARIABLES.md`; values are not hardcoded in app code. The checked-in frontend `.env.example` values are blank. Do not add a Realtime Database URL: no current app feature uses Realtime Database.

Firebase Authentication email/password sign-in is implemented in the Next.js client. The client requests Firebase ID tokens and refreshes them via the Firebase SDK. The same-origin Next.js API proxy forwards the bearer token to Laravel. Laravel verifies RS256 signatures against Google's public JWK endpoint, then checks issuer, audience/project, subject, timestamps, and email claims. Protected routes use the server-loaded Firestore profile for role/ownership authorization; browser-supplied roles are ignored. New registration is assigned the lowest `READER` role.

## Cryptographic test evidence

`php artisan test` passed 18 tests / 55 assertions, zero skipped. The signature tests use ephemeral RSA keys generated for the tests and cryptographically verify valid, tampered, wrong-audience, expired, and unverified-email tokens. The public-JWK HTTP response is mocked; this proves the local signature-verification code path, not a real Firebase-issued token or live Google key fetch.

## Required private Laravel credential

Laravel does **not** currently use a Firebase Admin SDK package. It uses `google/auth` to obtain OAuth credentials and a custom Firestore REST adapter; ID tokens are verified by the project's own RS256/JWK verifier. For Laravel's server-side Firestore calls, provide a dedicated Google service-account credential through one secure server-only mechanism:

- `FIREBASE_CREDENTIALS_JSON`: full service account JSON value stored as a backend platform secret, or
- `GOOGLE_APPLICATION_CREDENTIALS`: path to a securely mounted file available in the backend runtime.

Also configure `FIREBASE_PROJECT_ID=sicksaint`. Grant the service identity only the Firestore data permissions the API needs. The Web API key is not a backend credential. Never put the private key in frontend variables, source, a repository, ZIP, build logs, or chat. Never send a private key to the assistant; enter it only in the relevant secret manager. On Vercel, set credentials only on the Laravel service, not the Next.js service.

Google documents `roles/datastore.user` as read/write access intended for application developers and service accounts; consider a custom role with narrower permissions if it covers every REST call the application makes. Do not grant Owner or Editor merely to make a connection succeed. See [Cloud Firestore IAM roles](https://firebase.google.com/docs/firestore/security/iam).

The Laravel `.env.example` has `FIREBASE_PROJECT_ID=sicksaint`, but `FIREBASE_CREDENTIALS_JSON`, `GOOGLE_APPLICATION_CREDENTIALS`, and `APP_KEY` are blank. No backend `.env` or live deployment variables were available. Firebase server IAM credentials are therefore missing. The Next.js project has a separate `firebase-admin.ts` helper that expects `FIREBASE_PROJECT_ID`, `FIREBASE_CLIENT_EMAIL`, and `FIREBASE_PRIVATE_KEY`, but no call sites were found and those variables are absent from its example file. Do not provision duplicate admin credentials for that unused helper; Laravel is the selected server boundary.

The same service-account identity could support a future explicitly approved Firebase Auth import, but user imports are not part of deployment setup. A one-time import must be staged first, preserve verification status, resolve duplicate email/UID records, and be approved separately.

## Emulator rules verification

The Firestore Emulator suite was rerun with Temurin Java 21.0.13: **4 passed, 0 failed, 0 skipped**. It exercises only checked-in rules and synthetic test identities. It does not prove server IAM or live application requests. Production rules were read-only inspected; no production rules or records were modified, and no production documents were read.

## Account/data preservation

Firebase CLI project access is verified, but Authentication user/provider inventory remains uninspected. No account import, UID linking, profile write, Firestore document read, or data migration occurred. The existing local SQLite account has not been linked to a Firebase UID. See `docs/LEGACY_DATABASE_MIGRATION_PLAN.md` for aggregate-only inventory and a proposed safe path.

## Not verified

- Real account login/logout, recovery, email verification, token refresh in a browser, and deployed admin authorization.
- Real Firebase Authentication provider status/users, Laravel credential issuance, token verification against a real Firebase-issued token/live Google keys, Firestore IAM, production collection paths, or actual application reads/writes.
- Whether production indexes support all backend queries; CLI returned no deployed composite indexes.
- Storage bucket and deployed Storage rules. No binary upload feature was found; current storage assessment is in `docs/FIREBASE_STORAGE_POLICY.md`.
- End-to-end Next.js BFF → Laravel → live Firestore operations.

Do not call the integration production-complete until those live checks are performed using authorized test accounts and a staging environment.
