# Integration Checklist

Run these checks against a disposable local database loaded with `database/schema.sql`
and `database/seed.sql`. Record the build/commit, PHP and MySQL versions, browser, and
result for each check. Do not mark the end-to-end workflow complete until the full
sequence has passed.

## Current integration run

- Issue: Local MySQL service unavailable during verification.
- Build / commit: `feature/claims-admin` working tree before commit.
- Responsible component owner: Member 4 / local XAMPP database setup.
- Reproduction steps: Run `php test_db.php`, then `php tests/SmartMatchingTest.php` while MySQL is stopped.
- Expected behavior: PDO connects to `127.0.0.1:3306` and database integration tests continue.
- Actual behavior: Connection is refused; the matching score checks pass, but database-dependent test sections cannot run.
- Evidence: `SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it`.
- Disposition: Blocked until MySQL is started; full workflow remains unverified.

## Complete application flow

- [ ] Register a student/staff account with valid fields; verify the row and hashed password in `users`.
- [ ] Log in, then verify logout invalidates the session.
- [ ] Report lost and found items; verify item data, custody values, and uploaded item-image paths in MySQL.
- [ ] Confirm the matching service evaluates the new reports and persists only eligible cached scores in `smart_matches`.
- [ ] Confirm eligible match suggestions are available to the relevant report owners and include no `identifying_features`.
- [ ] Submit a claim for an open found item with a proof description and optional proof image; verify `claims.status = pending` and `items.status = pending_claim`.
- [ ] Confirm the claimant and authorized reviewers can access claim information; confirm other users cannot retrieve claim records or proof files.
- [ ] Review a claim as an administrator; verify only pending/under-review transitions are accepted and the claimant receives a notification.
- [ ] Complete a handover after physically checking the claimant's ID; verify the claim handover timestamp, found-item status/custody, linked lost-report closure, audit record, and notifications.
- [ ] Verify notification pagination, unread counts, mark-one/read-all behavior, and per-user isolation.
- [ ] Verify all admin pages and API routes reject non-admin users on the server.

## Negative, retry, and responsive checks

- [ ] Invalid item/claim IDs, invalid status values, overlong text, and proof descriptions shorter than 20 characters are rejected.
- [ ] Duplicate active claims for the same found item or linked lost report are rejected without changing item state.
- [ ] Repeating a claim submission/review notification operation does not create duplicate notifications.
- [ ] A claimant cannot read another user's claim, cancel another user's claim, or view another user's proof image.
- [ ] Missing proof image remains valid; oversized, non-image, forged-MIME, or malformed uploads are rejected.
- [ ] A proof URL cannot be opened directly from `uploads/proofs/`; authorized `proof-view.php` access streams only verified image MIME types.
- [ ] Simulate a database failure during submission, review, moderation, and handover; verify no partial status changes or orphan proof uploads remain.
- [ ] Invalid item/claim status transitions are rejected and do not write success-shaped responses.
- [ ] Repeating a completed handover is rejected; item custody cannot be set to `returned_to_owner` through moderation.
- [ ] Verify account suspension takes effect on the next authenticated request; verify the final active administrator cannot be demoted or suspended.
- [ ] At 360px, tablet, and desktop widths, verify claim forms, review comparison, handover checklist, user management, and notification screens remain usable without horizontal page overflow.

## Issue record

For every failure, create one record with:

```text
Issue:
Build / commit:
Responsible component owner:
Reproduction steps:
Expected behavior:
Actual behavior:
Evidence (response/status/log, with secrets and proof content redacted):
Disposition (open/fixed/deferred):
```

## Ownership and integration dependencies

- Claims, protected proofs, claim review/cancellation, admin user/item moderation,
  handover integration, notifications, and this checklist: Member 5.
- Registration/login, active-account/session behavior, item CRUD/upload validation,
  and public item privacy: Member 3. Confirm `require_admin()` also refreshes the
  user's database role so a demoted administrator's existing session loses access.
- Matching cache creation and match visibility/notifications from `MatchingService`:
  Member 4.
- Claim form, admin controls, notification display, and responsive presentation:
  Members 1 and 2.
- End-to-end match-to-claim testing requires the current Member 3 item API and Member 4
  matching service to be integrated with the endpoints and interfaces above.
