# Quick Reference Guide

## Environment Setup (Development)

```bash
# Install dependencies
composer install
npm install

# Generate keys
php artisan key:generate

# Run migrations
php artisan migrate:seed

# Start dev server
php artisan serve

# Run tests
php artisan test

# Start queue worker (for background jobs)
php artisan queue:work redis --daemon

# Start Node.js coin service (for wallet operations)
cd cpocket-EVM_Based_node-file && node src/app.js
```

## Environment Setup (Production)

```bash
# 1. Copy and configure
cp .env.example .env
# Edit .env with production values

# 2. Generate app key
php artisan key:generate

# 3. Run migrations with backup
php artisan migrate --force

# 4. Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Start services
php artisan queue:work redis --daemon --tries=3
# Also: pm2/supervisor for Node.js service
```

---

## API Endpoints

### Public Endpoints
```
GET  /api/obx/price                    # Live OBX price (CMC)
POST /api/presale/webhook              # Event relay (HMAC-verified)
POST /api/presale/sync-events          # Cron sync (API-key verified)
GET  /api/presale/phase-info/{index}   # Active phase info
POST /api/nowpayments/ipn               # NOWPayments callback (HMAC-verified)
```

### Authenticated User Endpoints
```
POST /user/staking/confirm-stake       # Verify & credit stake
POST /user/staking/confirm-unstake     # Verify & credit unstake
POST /user/airdrop/claim               # Daily claim
POST /user/airdrop/request-unlock      # Airdrop unlock request
POST /user/airdrop/confirm-unlock      # Verify & credit unlock
POST /user/buy-coin                    # Submit purchase (NOWPayments or WalletConnect)
POST /user/wallet/withdraw-obx         # Submit withdrawal request
```

### Admin Endpoints
```
POST /admin/settings/check-wallet-address      # Derive wallet from private key
POST /admin/phase/add                           # Create presale phase
POST /admin/phase/edit/{id}                     # Update presale phase
GET  /admin/phase/list                          # View all phases
```

---

## Database Schema (Key Tables)

```sql
-- Users and wallets
users                -- User accounts (email, password, 2FA)
wallets              -- User OBX/USDT wallets (created on signup)
buy_coin_histories   -- Purchase records (pending → success)

-- Blockchain
ico_phases           -- Presale phases (contract_phase_index synced from chain)
staking_positions    -- User stake records (verified on-chain)
airdrop_campaigns    -- Airdrop campaigns (contract_address for unlock)
airdrop_claims       -- Daily claims (locked until campaign ends)
airdrop_unlocks      -- Pending unlocks (awaiting on-chain tx)

-- Configuration
admin_settings       -- App settings (contract addresses, API keys, etc)
```

---

## Key Code Files

| File | Purpose |
|------|---------|
| `app/Services/BlockchainService.php` | RPC calls, event polling, contract verification |
| `app/Controllers/user/StakingController.php` | Stake/unstake confirmation with on-chain verification |
| `app/Controllers/user/AirdropController.php` | Airdrop claim & unlock with on-chain verification |
| `app/Controllers/user/CoinController.php` | Buy coin routing (NOWPayments or WalletConnect) |
| `app/Controllers/Api/PresaleWebhookController.php` | Event relay & cron sync |
| `app/Repository/CoinRepository.php` | Coin purchase record creation |
| `app/Models/IcoPhase.php` | Phase model with contract sync |
| `contracts/OBXPresale.sol` | Main presale contract (buyTokens, phase management) |
| `contracts/OBXStaking.sol` | Staking contract (stake, unstake, burn fees) |
| `contracts/OBXAirdrop.sol` | Airdrop contract (unlock with fee) |
| `resources/views/user/buy_coin/index.blade.php` | Buy page with WalletConnect flow |

---

## Testing

```bash
# Run all tests
php artisan test

# Run specific test class
php artisan test --filter=StakingTest
php artisan test --filter=AirdropTest
php artisan test --filter=AdminImpersonationTest

# Run with verbose output
php artisan test --verbose

# Tinker REPL for interactive testing
php artisan tinker
> $user = \App\User::first();
> $user->get_primary_wallet();
```

---

## Common Tasks

### Check Phase Sync Status
```php
php artisan tinker
> \App\Model\IcoPhase::find(1);
// If contract_phase_index == null, phase hasn't synced yet
// Run sync: curl -X POST /api/presale/sync-events -H "X-Api-Key: ..."
```

### Verify Blockchain Event
```php
php artisan tinker
> $bs = app(\App\Services\BlockchainService::class);
> $bs->verifyPurchaseTransaction('0x...');  # Check if tx on-chain
> $bs->getActivePhaseIndex();                # Get active phase
```

### Create Admin User
```php
php artisan tinker
> \App\User::create([
    'email' => 'admin@test.com',
    'password' => bcrypt('password'),
    'is_admin' => 1,
  ]);
```

### Reset User Staking (Admin)
```php
php artisan tinker
> \App\Model\StakingPosition::where('user_id', 1)->delete();
```

### Clear All Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## Monitoring & Logs

```bash
# Watch logs in real-time
tail -f storage/logs/laravel-*.log

# Check for blockchain errors
grep -i "blockchain\|rpc\|transaction" storage/logs/laravel-*.log

# Monitor specific feature (e.g., staking)
grep -i "staking\|confirmstake" storage/logs/laravel-*.log

# Check queue worker status
ps aux | grep "queue:work"

# Check Node.js service status
ps aux | grep "node.*coin"
```

---

## Emergency Commands

```bash
# Restart all services
pkill -f "queue:work"
php artisan queue:work redis --daemon &

pkill -f "node.*coin"
cd cpocket-EVM_Based_node-file && node src/app.js &

# Force unlock stuck transaction
php artisan tinker
> \App\Model\IcoPhase::where('id', 1)->update(['pending_onchain_tx' => null]);

# Hard reset database (DEV ONLY)
php artisan migrate:fresh --seed

# Check database integrity
php artisan tinker
> DB::select('CHECK TABLE users;')
```

---

## Git Workflow

```bash
# Start feature
git checkout -b feature/your-feature-name

# Commit often
git add .
git commit -m "Descriptive message"

# Push to origin
git push origin feature/your-feature-name

# Create PR (GitHub/GitLab)
# After review & approval, merge to main

# Pull latest main
git checkout main
git pull origin main

# Deploy (staging first, then prod)
```

---

## Performance Tips

- Use `php artisan config:cache` in production
- Enable query caching for frequently accessed data
- Use `select()` to fetch only needed columns
- Monitor N+1 queries with Laravel Debugbar (dev only)
- Test load with `ab` or `wrk` before launch
- Monitor RPC endpoint response times

---

## Security Reminders

- Never commit `.env` to git
- Use HTTPS only in production
- Rotate API keys quarterly
- Enable CSRF protection on all forms
- Validate all user input
- Use parameterized queries (Laravel's ORM handles this)
- Keep dependencies updated: `composer update`
- Use strong passwords for database & admin

---

## Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Blockchain Service](../app/Services/BlockchainService.php)
- [Smart Contracts](../contracts/)
- [Test Specs](../tests/Feature/)
- [Production Guide](./PRODUCTION_READY.md)
- [Deployment Guide](./DEPLOYMENT.md)

