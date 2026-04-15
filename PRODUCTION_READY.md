# Production Readiness Checklist

## Critical Issues (Must Fix Before Launch)

### 1. Environment Configuration
- [ ] Set `APP_DEBUG=false` in production .env
- [ ] Set `APP_ENV=production` 
- [ ] Ensure `APP_KEY` is generated (`php artisan key:generate`)
- [ ] Configure all required blockchain variables:
  ```
  PRESALE_CONTRACT=0x...          # BSC presale contract
  OBX_TOKEN_CONTRACT=0x...        # OBX token contract
  STAKING_CONTRACT=0x...          # Staking contract
  AIRDROP_CONTRACT=0x...          # Airdrop contract
  PRESALE_WEBHOOK_SECRET=long_random_string  # 32+ chars
  PRESALE_SYNC_API_KEY=long_random_string    # 32+ chars
  BSCSCAN_API_KEY=...             # For event polling
  OWNER_PRIVATE_KEY=0x...         # Admin wallet (ENCRYPT IN VAULT)
  SIGNER_PRIVATE_KEY=0x...        # Hot wallet for OBX transfers (ENCRYPT IN VAULT)
  ```

### 2. Security Hardening
- [ ] Remove all `dd()`, `var_dump()`, `echo` statements with sensitive data
- [ ] Enable CSRF protection (verify `VerifyCsrfToken` middleware is active)
- [ ] Set secure session cookies: `SESSION_SECURE_COOKIES=true` (HTTPS only)
- [ ] Set `SANCTUM_STATEFUL_DOMAINS` for API auth
- [ ] Rotate all default keys/secrets before launch
- [ ] Store private keys in `.env` or secured vault (never in code/git)
- [ ] Use HTTPS only (redirect HTTP → HTTPS)
- [ ] Configure CORS properly for wallet interactions

### 3. Database & Migrations
- [ ] Run all pending migrations: `php artisan migrate --force`
- [ ] Verify database backups are enabled
- [ ] Test rollback procedures
- [ ] Create indexes on frequently queried columns (tx_hash, user_id, wallet_address, created_at)

### 4. Logging & Monitoring
- [ ] Set `LOG_CHANNEL=stack` and configure multiple channels:
  - `single` for general logs
  - `slack` for critical alerts
  - `sentry` for exception tracking
- [ ] Set appropriate log levels:
  - Production: `error` or `warning` (not `debug`)
  - Staging: `debug` for troubleshooting
- [ ] Enable structured logging with request IDs for tracing
- [ ] Monitor error logs for external service failures (RPC, payment processors)

### 5. External Services
- [ ] Test all RPC endpoints for failover (have backups configured):
  ```
  BSC_RPC_URL = https://bsc-dataseed.binance.org/  (production)
  BSC_RPC_URL_BACKUP = https://bsc-dataseed1.binance.org:8545/
  ```
- [ ] Configure WalletConnect project ID and verify rate limits
- [ ] Set up NOWPayments IPN endpoint and test webhook signature verification
- [ ] Configure Stripe/payment processor webhooks
- [ ] Test all blockchain operations (gas estimation, transaction broadcasting)

### 6. Testing
- [ ] Run full test suite: `php artisan test`
- [ ] All StakingTest tests pass (16/16)
- [ ] All AirdropTest tests pass (28/28)
- [ ] Load test critical endpoints (buy coin, withdraw, etc.)
- [ ] Test failover scenarios (RPC down, payment processor down)

### 7. API Rate Limiting
- [ ] Add throttle middleware to API routes:
  ```php
  Route::middleware('throttle:60,1')->group(function () {
      Route::post('/api/...');
  });
  ```
- [ ] Configure Redis for distributed rate limiting
- [ ] Monitor for abuse patterns

### 8. Cron Jobs & Background Tasks
- [ ] Set up cron: `php artisan presale:sync-events` (every 1 minute)
  ```
  * * * * * /usr/bin/php /path/to/artisan presale:sync-events
  ```
- [ ] Set up CMC price fetch cron: `php artisan fees:fetch-cmc-price` (every 30 min)
- [ ] Verify queue worker is running: `php artisan queue:work redis --daemon`
- [ ] Monitor cron job execution and error logs

### 9. SSL/TLS & HTTPS
- [ ] Install SSL certificate (Let's Encrypt)
- [ ] Configure nginx/Apache for HTTPS redirect
- [ ] Set `SECURE_HEADERS` and HSTS headers
- [ ] Test SSL certificate validity and expiry monitors

### 10. Performance & Caching
- [ ] Enable query caching: `php artisan config:cache`
- [ ] Cache route definitions: `php artisan route:cache`
- [ ] Cache views: `php artisan view:cache`
- [ ] Monitor N+1 queries in critical paths
- [ ] Use connection pooling for database
- [ ] Set up Redis for session/cache storage

### 11. Backups & Disaster Recovery
- [ ] Database backups: Daily (automated)
- [ ] Test backup restoration procedure
- [ ] Document rollback procedures for smart contract updates
- [ ] Have emergency contact list for critical issues

### 12. Deployment
- [ ] Document deployment steps
- [ ] Create rollback checklist
- [ ] Test deployment procedure on staging
- [ ] Verify file permissions (storage/ and vendor/)
- [ ] Set up monitoring alerts (cloud provider or third-party)

---

## What's Currently Production-Ready

✅ **Staking**
- On-chain verification for stake/unstake
- WalletConnect integration for signing
- Burn fees applied and visible on-chain
- Tests: 16/16 passing

✅ **Airdrop**
- On-chain unlock verification
- Daily claim limits enforced
- WalletConnect unlock flow
- Tests: 28/28 passing

✅ **OBX Withdrawal**
- WalletConnect fee gate (BNB fee + USDT approval)
- On-chain transaction verification
- Default wallet creation on signup

✅ **Buy with WalletConnect**
- On-chain purchase verification via TokensPurchased event
- Fallback to query active phase from contract if DB out of sync
- Validates USDT approval + purchase on BSC

---

## What Needs Attention

⚠️ **Phase Syncing**
- Must run `POST /api/presale/sync-events` regularly (via cron)
- Requires `PRESALE_SYNC_API_KEY` set in .env
- If not running, phases won't have `contract_phase_index` populated

⚠️ **Event Relay**
- Presale webhook endpoint must be reachable from external relay (e.g., Alchemy Notify)
- HMAC signature verification required (`PRESALE_WEBHOOK_SECRET`)
- Fallback: cron-based polling via `syncEvents()` endpoint

⚠️ **Error Handling**
- RPC endpoint failures are logged but may block purchases
- Need fallback RPC endpoints configured
- Payment processor downtime will block NOWPayments purchases

⚠️ **Monitoring**
- No alerts configured for:
  - Failed blockchain transactions
  - Sync lag (presale_last_block not updating)
  - RPC endpoint health
  - External service failures

---

## Pre-Launch Verification

Run this before going live:

```bash
# 1. Run all tests
php artisan test

# 2. Verify migrations
php artisan migrate:status

# 3. Check configuration
php artisan config:show APP_DEBUG      # should be false
php artisan config:show blockchain    # should have all contracts

# 4. Clear caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Run one sync cycle to verify event relay
curl -X POST https://yoursite.com/api/presale/sync-events \
  -H "X-Api-Key: $PRESALE_SYNC_API_KEY"

# 6. Test blockchain operations (via tinker)
php artisan tinker
> $bs = app(\App\Services\BlockchainService::class);
> $bs->getActivePhaseIndex();  # should return >= 0 if phase exists
```

---

## Post-Launch Monitoring

- [ ] Set up Sentry for error tracking
- [ ] Set up New Relic or similar for performance monitoring
- [ ] Hourly check: `presale_last_block` is advancing (event sync working)
- [ ] Daily check: No spike in error rates
- [ ] Weekly check: Backup restoration test
- [ ] Weekly check: SSL certificate expiry (< 30 days warning)

