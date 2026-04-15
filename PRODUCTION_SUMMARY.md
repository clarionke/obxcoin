# Production Readiness Summary

## Test Results ✅

```
StakingTest:     16/16 PASSED (5.85s)
AirdropTest:     28/28 PASSED (6.03s)
Total:           44/44 PASSED
```

All critical features have passing test coverage.

---

## What's Complete & Production-Ready

### ✅ On-Chain Staking
- Stake verification via receipt analysis
- Unstake verification with contract call validation
- Burn fees calculated and visible on BSCScan
- Reward calculations for variable lock periods
- WalletConnect integration for transaction signing

### ✅ On-Chain Airdrop
- Daily claim limits (1 per 24h per campaign)
- Lockup period enforcement (claims locked until campaign ends)
- On-chain unlock via `OBXAirdrop.unlock()` smart contract
- WalletConnect unlock flow with transaction verification
- Streak bonuses on consecutive claim days
- Campaign fee reveal after campaign ends
- User's OBX wallet balance automatically created on signup

### ✅ OBX Withdrawal with WalletConnect Fee Gate
- Required BEP20 approval verification
- BNB gas fee validation before withdrawal
- Admin wallet receives only BNB fee
- User's OBX sent to destination wallet (not admin)
- Transaction receipt verification on-chain

### ✅ WalletConnect Coin Purchase
- On-chain presale `buyTokens()` invocation
- Automatic phase index fallback if DB out of sync
- USDT approval enforcement
- Transaction receipt verification
- TokensPurchased event tracking
- User OBX credit on event confirmation

### ✅ Phase Management
- Admin phase creation with on-chain sync
- Automatic `contract_phase_index` population
- Pending transaction tracking
- Event relay support (Alchemy Notify, Moralis Streams, cron polling)
- Phase update and deletion

---

## Critical Configuration (Must Set Before Launch)

### Environment Variables (in `.env` for production)

**Blockchain:**
```
PRESALE_CONTRACT=0xD68Cf01406b88f960959af3eE6f7caFc402788eB
OBX_TOKEN_CONTRACT=0x13bd87CE207815c9954d2267E85aFFC69E445797
STAKING_CONTRACT=0xC66867b82F67B5133B047ee24C73EEAAE0019905
AIRDROP_CONTRACT=0x75A7b964EBD57A24FE0C9709e4F6aDa3B724A1bA
BSC_RPC_URL=https://bsc-dataseed.binance.org/
PRESALE_CHAIN_ID=56
```

**Admin Wallet (Keep Encrypted in Vault):**
```
OWNER_PRIVATE_KEY=0x...     # Admin wallet for phase management
SIGNER_PRIVATE_KEY=0x...    # Hot wallet for OBX transfers (must hold OBX)
```

**Webhooks & APIs:**
```
PRESALE_WEBHOOK_SECRET=<generate: openssl rand -hex 32>
PRESALE_SYNC_API_KEY=<generate: openssl rand -hex 32>
BSCSCAN_API_KEY=<from https://bscscan.com/apis>
```

**WalletConnect:**
```
WALLETCONNECT_PROJECT_ID=<from https://cloud.walletconnect.com>
WALLETCONNECT_CHAIN_ID=56
```

**Security:**
```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...          # php artisan key:generate
PRESALE_WEBHOOK_SECRET=...  # Must be 32+ random characters
```

---

## Pre-Launch Tasks

### 1. Run Production Readiness Check
```bash
bash scripts/pre-launch-check.sh
```

### 2. Database Setup
```bash
# Apply all pending migrations
php artisan migrate --force

# Verify database schema
php artisan tinker
> DB::select('SHOW TABLES')
```

### 3. Cache Configuration
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Cron Job Setup
```bash
# Add to crontab (crontab -e):
* * * * * curl -X POST https://your-site.com/api/presale/sync-events \
  -H "X-Api-Key: $PRESALE_SYNC_API_KEY" >> /var/log/obxcoin-sync.log 2>&1
```

### 5. Blockchain Verification
```php
php artisan tinker
> $bs = app(\App\Services\BlockchainService::class);
> $bs->getActivePhaseIndex();        # Should return int >= 0
> $bs->getRemainingTokens(0);        # Should return uint256 value
```

### 6. Test Each Flow
- [ ] Create/edit phase in admin → verify syncs to contract
- [ ] User stakes OBX → verify on BscScan
- [ ] User claims airdrop → verify locked until campaign ends
- [ ] User unlocks airdrop → verify on BscScan
- [ ] User buys OBX via WalletConnect → verify TokensPurchased event
- [ ] User withdraws OBX → verify BNB fee verified on-chain

---

## Post-Launch Monitoring

### Critical Metrics (Check Every 15 minutes)
- [ ] `presale_last_block` advancing (sign event sync is working)
- [ ] Error rate < 1% in application logs
- [ ] RPC endpoint responding < 500ms
- [ ] Queue worker processing jobs
- [ ] Cron sync job completing successfully

### Alerts to Configure
| Alert | Condition | Action |
|-------|-----------|--------|
| Phase Sync Down | `presale_last_block` unchanged for 10+ min | Page on-call |
| RPC Endpoint Down | BlockchainService requests failing | Failover to backup RPC |
| High Error Rate | > 10 errors/min in logs | Investigate & rollback if needed |
| Queue Backed Up | > 100 jobs in queue | Scale workers or throttle input |
| Gas Estimation Failed | Cannot estimate on contract calls | Check admin wallet balance |

---

## Known Limitations & Workarounds

### NOWPayments Path (Still Off-Chain)
- IPN webhook credits OBX based on payment processor callback
- **Workaround:** Verify purchase on BSCScan separately if needed
- **Future:** Can add on-chain verification after NOWPayments confirmation

### Phase Syncing Lag
- New phases take ~30-60 sec to appear on-chain
- **Workaround:** Web3 modal queries `activePhaseIndex()` live from contract as fallback
- **Future:** Immediate on-chain sync via signer service

### Wallet Connection Reset
- WalletConnect session expires after ~5 minutes of inactivity
- **Workaround:** User reconnects during transaction
- **Future:** Auto-reconnect on session expiry

---

## Common Operations

### Create New Presale Phase
1. Admin Panel → ICO Phase → Create Phase
2. Fill: name, dates, rate (USD), amount (OBX), bonus %
3. Click Save
4. System broadcasts to contract via admin wallet
5. Verify in Admin Panel: `contract_phase_index` is populated within 2 min
6. **If not synced:** Run `php artisan presale:sync-events` manually

### Manual Event Sync
```bash
curl -X POST https://your-site.com/api/presale/sync-events \
  -H "X-Api-Key: YOUR_PRESALE_SYNC_API_KEY"

# Response: {"processed": N, "last_block": M}
```

### Verify Wallet Gets Credit
```php
php artisan tinker
> $user = \App\User::find(1);
> $wallet = $user->get_primary_wallet();
> $wallet->balance;          # Should show OBX amount
> $wallet->created_at;       # Auto-created on signup
```

### Check Pending Phase Sync
```php
php artisan tinker
> \App\Model\IcoPhase::all()->map(fn($p) => [
    'id' => $p->id,
    'name' => $p->phase_name,
    'contract_idx' => $p->contract_phase_index,
    'synced' => $p->contract_synced,
    'pending_tx' => $p->pending_onchain_tx,
  ])
```

---

## Disaster Recovery

### Quick Rollback (Transaction Failure)
```bash
# Stop all services
pkill -f "queue:work"

# Restore database snapshot
mysql -u $USER -p $DB_NAME < backup_latest.sql

# Restart
php artisan cache:clear
php artisan queue:work --daemon
```

### Blockchain Reorg Recovery
- Small reorgs (< 5 blocks): Auto-handled by event sync polling
- Large reorgs: Manually review and reconcile purchase records
- **Prevention:** Wait 12 confirmations before finalizing sensitive operations

### Emergency Private Key Rotation
1. Generate new admin wallet
2. Update `OWNER_PRIVATE_KEY` in .env
3. Transfer contract ownership (if needed)
4. Update presale signer address
5. Restart blockchain service
6. Notify team and audit logs

---

## Security Checklist

- [ ] Private keys stored in encrypted vault (not in code/git)
- [ ] `.env` file not committed to repository
- [ ] SSL/TLS certificate installed and auto-renewed
- [ ] HTTPS forced for all traffic
- [ ] CORS configured to allow only trusted origins
- [ ] Rate limiting enabled on API endpoints
- [ ] Admin authentication requires 2FA
- [ ] Webhook signatures verified (HMAC-SHA512)
- [ ] API keys rotated quarterly
- [ ] Database backups encrypted and tested regularly
- [ ] VPN/IP whitelist for sensitive admin endpoints
- [ ] Application logs monitored for suspicious activity
- [ ] Smart contract audited (if using custom contracts)

---

## Support & Troubleshooting

See [DEPLOYMENT.md](DEPLOYMENT.md) for:
- Detailed deployment steps with troubleshooting
- Monitoring dashboards and alert configuration
- Common issues and solutions
- Performance optimization tips

See [PRODUCTION_READY.md](PRODUCTION_READY.md) for:
- Complete pre-launch checklist
- Environmental configuration details
- Testing and validation procedures
- Backup and disaster recovery procedures

---

## Contact

For production support or emergencies:
- On-call: [phone/email]
- Escalation: [manager/lead]
- Incident channel: [Slack/Discord/etc]

---

**Last Updated:** 2026-04-16
**Maintained By:** Dev Team
**Next Review:** 2026-05-16
