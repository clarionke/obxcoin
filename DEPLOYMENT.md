# Deployment & Operations Guide

## Pre-Deployment Checklist

### 1. Code & Configuration Review
```bash
# Ensure main branch is ready
git status  # should be clean
git log -1  # verify latest commit

# Run all tests
php artisan test

# Verify production configuration
php artisan config:show APP_DEBUG        # must be false
php artisan config:show blockchain      # verify all contracts set
```

### 2. Database Backup
```bash
# Backup current database
mysqldump -u $DB_USER -p $DB_NAME > backup_$(date +%Y%m%d_%H%M%S).sql

# Test restore procedure
mysql -u $DB_USER -p $DB_NAME < backup_*.sql
```

### 3. Environment Preparation
```bash
# Copy .env and update critical values
cp .env.example .env.production

# Generate or copy APP_KEY
php artisan key:generate

# Update these in .env.production:
# - APP_DEBUG=false
# - APP_ENV=production
# - DB_* credentials
# - PRESALE_CONTRACT, OBX_TOKEN_CONTRACT, STAKING_CONTRACT, AIRDROP_CONTRACT
# - OBX_DEX_PAIR, OBX_DEX_CHAIN (for DEX-based auto price fallback)
# - PRESALE_WEBHOOK_SECRET (generate: openssl rand -hex 32)
# - PRESALE_SYNC_API_KEY (generate: openssl rand -hex 32)
# - OWNER_PRIVATE_KEY (from secure vault)
# - SIGNER_PRIVATE_KEY (from secure vault)
# - BSCSCAN_API_KEY
# - WalletConnect project ID & settings
# - NOWPayments merchant settings
```

---

## Deployment Steps

### Step 1: Pull & Install
```bash
cd /path/to/obxcoin

# Pull latest code
git pull origin main

# Install/update dependencies
composer install --no-dev --optimize-autoloader

# Install frontend dependencies (if needed)
npm install --production
npm run prod

# Optional: deploy contracts and seed first OBX/USDT liquidity so DEX price is discoverable
# INITIAL_LP_OBX and INITIAL_LP_USDT can be set in .env before this step
npx hardhat run scripts/deploy.js --network bsc_mainnet

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Step 2: Database Migrations
```bash
# Backup database FIRST
php artisan db:backup

# Run pending migrations
php artisan migrate --force --env=production

# Verify migrations completed
php artisan migrate:status
```

### Step 3: Cache & Config
```bash
# Cache configuration for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 4: Start Services
```bash
# Start queue worker (in background or supervisor)
php artisan queue:work redis --daemon --tries=3 --timeout=300 > storage/logs/queue.log 2>&1 &

# Verify services are running
ps aux | grep "queue:work"
ps aux | grep "node /path/to/cpocket-EVM_Based_node-file/src/app.js"
```

### Step 5: Cron Jobs
```bash
# Add ONE system cron entry (crontab -e)
# Laravel scheduler will run all service jobs configured in App\Console\Kernel
* * * * * cd /path/to/obxcoin && /usr/bin/php artisan schedule:run >> /var/log/obxcoin-scheduler.log 2>&1

# Included jobs:
# - command:membershipbonus (daily)
# - custom-token-deposit (every 5 minutes)
# - adjust-token-deposit (every 30 minutes)
# - cmc:fetch-price (every 5 minutes)
# - cmc:report-supply (daily)
# - co-wallet:cancel-expired-withdrawals (every minute)
# - presale:sync-events (every minute)
```

### Step 6: Verify Services
```bash
# Test blockchain connectivity
php artisan tinker
> $bs = app(\App\Services\BlockchainService::class);
> $bs->getActivePhaseIndex();        # should return int >= 0

# Test market data sync (CMC primary, DexScreener fallback via OBX_DEX_PAIR)
php artisan cmc:fetch-price

# Test webhook endpoint
curl -X POST https://your-site.com/api/presale/webhook \
  -H "Content-Type: application/json" \
  -H "X-Presale-Signature: test" \
  -d '{}'
# Should return 401 (invalid signature) — good sign webhook is protected

# Test sync endpoint
curl -X POST https://your-site.com/api/presale/sync-events \
  -H "X-Api-Key: YOUR_PRESALE_SYNC_API_KEY"
# Should return: {"processed": N, "last_block": M}
```

---

## Post-Deployment

### Monitoring (First Hour)
```bash
# Watch error logs for issues
tail -f storage/logs/laravel-*.log

# Check system load
top

# Monitor queue processing
php artisan queue:work --daemon --verbose

# Check cron execution (monitor cron logs)
tail -f /var/log/obxcoin-scheduler.log
```

### Health Checks
```bash
# Every 5 minutes for first hour:
curl https://your-site.com/api/obx/price          # should return price data
curl https://your-site.com/api/presale/sync-events -H "X-Api-Key: ..." # should sync

# Check presale_last_block is advancing
php artisan tinker
> \App\Model\AdminSetting::where('slug', 'presale_last_block')->first()->value
> # Run again after 2 minutes, should be higher
```

---

## Rollback Procedure

If critical issues discovered after deployment:

### Quick Rollback (Last 5 minutes)
```bash
# Revert to previous code
git revert HEAD
git pull origin main

# Rollback database
mysql -u $DB_USER -p $DB_NAME < backup_previous.sql

# Restart services
php artisan cache:clear
php artisan queue:work --daemon
```

### Full Rollback (Last Deploy)
```bash
# Kill all services
pkill -f "queue:work"

# Checkout previous version
git checkout previous_version_tag

# Rollback database
mysql -u $DB_USER -p $DB_NAME < backup_previous.sql

# Reinstall
composer install

# Restart
php artisan migration:refresh --seed  # if needed
php artisan queue:work --daemon
```

---

## Monitoring Dashboards

### Set Up Alerts For:
1. **Health Checks**
   - Check /health endpoint responds with 200
   - Check presale_last_block has advanced in last 5 minutes

2. **Error Logs**
   - Alert on ERROR+ in laravel logs
   - Alert on specific strings: "blockchain", "transaction failed", "RPC"

3. **Performance**
   - App response time > 2s
   - Queue job failure rate > 5%
   - Memory usage > 80%

4. **External Services**
   - BSC RPC endpoint down
   - Binance API down
   - NOWPayments API down

### Example Monitoring Script
```bash
#!/bin/bash
# monitor.sh - run every 5 minutes via cron

CHECK_LAST_BLOCK() {
    LAST_BLOCK=$(php artisan tinker --env=production <<EOF | tail -1
\App\Model\AdminSetting::where('slug', 'presale_last_block')->first()?->value ?? 0
EOF
    )
    
    STORED_BLOCK=$(cat /tmp/last_presale_block.txt 2>/dev/null || echo "0")
    if [ "$LAST_BLOCK" == "$STORED_BLOCK" ]; then
        echo "ALERT: presale_last_block not advancing ($LAST_BLOCK)" | mail -s "OBXCoin Alert" ops@yourcompany.com
    fi
    echo "$LAST_BLOCK" > /tmp/last_presale_block.txt
}

CHECK_ERROR_LOGS() {
    ERROR_COUNT=$(grep "ERROR" storage/logs/laravel-*.log 2>/dev/null | wc -l)
    if [ "$ERROR_COUNT" -gt 10 ]; then
        tail -20 storage/logs/laravel-*.log | mail -s "OBXCoin: $ERROR_COUNT Errors" ops@yourcompany.com
    fi
}

CHECK_LAST_BLOCK
CHECK_ERROR_LOGS
```

---

## Troubleshooting

### Issue: Phase Sync Not Working
```bash
# Check sync API key is correct
echo $PRESALE_SYNC_API_KEY

# Test sync endpoint manually
curl -X POST https://your-site.com/api/presale/sync-events \
  -H "X-Api-Key: $PRESALE_SYNC_API_KEY" \
  -v

# Check cron job output
grep "sync-events" /var/log/syslog | tail -20

# Verify blockchain config
php artisan config:show blockchain.presale_contract
```

### Issue: Transactions Failing
```bash
# Check RPC connectivity
php artisan tinker
> Http::get('https://bsc-dataseed.binance.org/', ['method' => 'eth_blockNumber', 'jsonrpc' => '2.0', 'id' => 1])->json()

# Check admin wallet balance (for gas)
# Visit BscScan: https://bscscan.com/address/0x{OWNER_PRIVATE_KEY}

# Check signer wallet balance (for OBX transfers)
# View logs for rejection reasons
tail -100 storage/logs/laravel-*.log | grep -i "blockchain\|transaction\|failed"
```

### Issue: Webhooks Not Received
```bash
# Verify webhook URL is publicly accessible
curl https://your-site.com/api/presale/webhook -v
# Should return Content-Type error (not 404 or 403)

# Check if firewall/WAF is blocking
# Check nginx logs for rejected requests
tail -100 /var/log/nginx/access.log | grep "presale/webhook"

# If Alchemy/Moralis: verify webhook is enabled and secret matches
# PRESALE_WEBHOOK_SECRET in .env must match relay configuration
```

---

## Security Reminders

- [ ] Never commit `.env` to git (use `.env.example` only)
- [ ] Rotate PRESALE_WEBHOOK_SECRET if exposed
- [ ] Rotate OWNER_PRIVATE_KEY if compromised
- [ ] Use HTTPS only (redirect HTTP → HTTPS)
- [ ] Keep dependencies updated: `composer update --no-dev`
- [ ] Enable two-factor authentication for admin accounts
- [ ] Regularly audit admin logs
- [ ] Use VPN/IP whitelist for sensitive endpoints

