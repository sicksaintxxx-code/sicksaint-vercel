# SICKSAINT Role and Permission Matrix

All API authorization is enforced by Laravel after Firebase ID-token verification. UI guards are for navigation only; they are not authorization boundaries. Roles come from Firestore user records, never request bodies or client token claims.

| Capability | Reader | Contributor | Editor | Admin |
|---|---:|---:|---:|---:|
| Read public published WordPress articles | Yes | Yes | Yes | Yes |
| Read/update own profile | Yes | Yes | Yes | Yes |
| Create and manage own drafts/submissions | No | Yes | Yes | Yes |
| Review submissions / request changes | No | No | Yes | Yes |
| Manage users and roles | No | No | No | Yes |
| Read admin statistics | No | No | Yes | Yes |
| Export newsletter subscriber list | No | No | Yes | Yes |
| Publish editorial content | No | No | Requires WordPress editor access | Requires WordPress editor access |

## Enforcement source

- Registration creates only `READER` profiles; caller-provided roles are ignored.
- Laravel routes apply `firestore.auth` plus role middleware and ownership checks.
- Admin role changes occur through a protected Laravel endpoint. The bootstrap CLI command can promote only an existing verified Firebase-linked profile after explicit server-console confirmation; it cannot create a password-based account.
- The frontend admin/user/submission pages call Laravel through the same-origin Next.js API client/BFF. Next route handlers for profile, submissions, reviews, users/stats, notifications and newsletter proxy to Laravel.
- Firestore client rules are local deny-all; server IAM access bypasses those rules and depends on Laravel checks.

## Test limits

Local Laravel tests verify role/ownership decisions using an in-memory Firestore store and fake Firebase token verifier. They do not verify Firebase token signatures, live user accounts, or production records. Four signature-specific tests were skipped because local PHP/OpenSSL could not create a test RSA key. Firestore Emulator tests could not run because Java is missing. This matrix does not certify live authorization.
