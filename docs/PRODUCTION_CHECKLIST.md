# PLASTEX ERP — Production Readiness Checklist

## Environment
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` set to the real domain
- [ ] `APP_KEY` generated; never reused from dev
- [ ] MySQL 8 (InnoDB) with a **least-privilege** app user (not root)
- [ ] Redis running; `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` use it as appropriate
- [ ] `config:cache` + `route:cache` run after each deploy

## Security
- [x] Server-side authorization on every protected endpoint (policies + gates)
- [x] Multi-tenant isolation (`company_id` global scope + policy + module gate)
- [x] Passwords hashed; **NID encrypted at rest + blind index**, hidden from the API
- [x] Security headers on every response (`SecurityHeaders` middleware)
- [x] Login rate-limited (`throttle:10,1`)
- [x] Domain errors return 422 (no 500 leakage of internals)
- [ ] HTTPS enforced (certbot); HSTS emitted over TLS
- [ ] `ufw` firewall: allow Nginx Full + OpenSSH only
- [ ] Secrets only in server env / secret store (never in Git or frontend)
- [ ] File uploads validated; private documents streamed through authorized routes only

## Financial & inventory integrity (enforced + tested)
- [x] Double-entry: posted entries balance; posting is server-side + transactional
- [x] Idempotent posting (unique source+purpose); posted entries never edited/deleted
      (reversals only); period locking
- [x] Stock engine row-locked; no negative stock unless allowed; append-only ledger
- [x] Inventory ↔ GL reconciliation; customer-owned (job-work) material kept separate
- [x] Invoice ≠ delivery challan; partial deliveries supported
- [x] Import landed-cost allocation (configurable basis; charge never allocated twice)

## Statutory (must be verified before go-live)
- [ ] VAT/Mushak **9.1 Note Numbers + note→subform mappings** entered and **approved by a
      qualified Bangladesh VAT practitioner** (nothing hard-coded; unmapped → review)
- [ ] VAT rates, tax treatments, thresholds verified & effective-dated
- [ ] VDS rates/service categories verified & approved
- [ ] Review the **Unmapped Transaction Report** is empty (or consciously accepted)

## Backup & recovery
- [x] `backup:run` scheduled every 20 min; compress + encrypt + checksum-verify
- [x] 30-day retention; never deletes the last good backup
- [ ] `BACKUP_ENCRYPTION_KEY` stored separately from artifacts
- [ ] Google Drive service account + dedicated folder configured (driver wired)
- [ ] A **restore test** performed from a real backup (`deploy/restore.sh`)
- [ ] Admin alerts configured for backup failure / auth expiry / quota

## Operations
- [ ] Queue worker + scheduler running (systemd units enabled)
- [ ] Log rotation configured; error logs monitored
- [ ] Documented: single-server deployment has downtime if the host fails
- [x] Automated tests green (115 backend Pest; frontend Vitest) in CI before deploy

## Data
- [ ] No demo/seed data in the production database (DemoSeeder is dev-only)
- [ ] Company Setup Wizard run for the real company; business types + modules configured
