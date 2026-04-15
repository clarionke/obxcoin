#!/bin/bash

# Production Readiness Pre-Launch Checklist Script

set -e

echo "🔍 OBXCoin Production Readiness Check"
echo "======================================"
echo ""

# 1. Check .env configuration
echo "✓ Checking environment variables..."
if grep -q "APP_DEBUG=true" .env 2>/dev/null; then
    echo "  ❌ CRITICAL: APP_DEBUG=true in .env (should be false for production)"
    exit 1
fi

if ! grep -q "PRESALE_WEBHOOK_SECRET=" .env 2>/dev/null || [ -z "$(grep 'PRESALE_WEBHOOK_SECRET=' .env | cut -d'=' -f2)" ]; then
    echo "  ⚠️  WARNING: PRESALE_WEBHOOK_SECRET not configured"
fi

if ! grep -q "PRESALE_SYNC_API_KEY=" .env 2>/dev/null || [ -z "$(grep 'PRESALE_SYNC_API_KEY=' .env | cut -d'=' -f2)" ]; then
    echo "  ⚠️  WARNING: PRESALE_SYNC_API_KEY not configured"
fi

if ! grep -q "PRESALE_CONTRACT=" .env 2>/dev/null || [ -z "$(grep 'PRESALE_CONTRACT=' .env | cut -d'=' -f2)" ]; then
    echo "  ⚠️  WARNING: PRESALE_CONTRACT not configured"
fi

# 2. Check private keys aren't hardcoded
echo "✓ Checking for hardcoded secrets..."
if grep -r "0x[a-fA-F0-9]\{64\}" app/ --include="*.php" | grep -v "test" | grep -v "_testing"; then
    echo "  ❌ WARNING: Potential hardcoded private keys found in code"
fi

# 3. Run tests
echo "✓ Running test suite..."
php artisan test --filter="StakingTest|AirdropTest" 2>/dev/null || {
    echo "  ❌ Tests failed - fix before launch"
    exit 1
}

# 4. Check database migrations
echo "✓ Checking database migrations..."
php artisan migrate:status 2>/dev/null | grep "No" && {
    echo "  ⚠️  WARNING: Pending migrations exist"
}

# 5. Check important tables exist
echo "✓ Verifying database schema..."
php artisan tinker <<'EOF' 2>/dev/null
$tables = DB::select('SHOW TABLES');
$required = ['users', 'ico_phases', 'buy_coin_histories', 'wallets', 'admin_settings'];
foreach ($required as $table) {
    $exists = collect($tables)->contains(DB::raw("Tables_in_" . env('DB_DATABASE')), $table);
    if (!$exists) {
        echo "  ❌ Missing table: $table\n";
        exit(1);
    }
}
echo "  ✓ All required tables exist\n";
EOF

# 6. Cache configuration
echo "✓ Caching configuration..."
php artisan config:cache 2>&1 | grep -v "Configuration cached" || true
php artisan route:cache 2>&1 | grep -v "Routes cached" || true
php artisan view:cache 2>&1 | grep -v "Blade templates cached" || true

# 7. Check storage permissions
echo "✓ Checking storage permissions..."
if [ ! -w "storage/" ]; then
    echo "  ❌ storage/ directory is not writable"
    exit 1
fi

if [ ! -w "bootstrap/cache/" ]; then
    echo "  ❌ bootstrap/cache/ directory is not writable"
    exit 1
fi

# 8. Final checks
echo ""
echo "✅ Basic Production Readiness Check Passed!"
echo ""
echo "📋 Remaining Manual Checks:"
echo "  □ SSL/TLS certificate installed and valid"
echo "  □ Cron jobs configured (presale:sync-events every 1 minute)"
echo "  □ Queue worker running: php artisan queue:work redis --daemon"
echo "  □ Backups configured and tested"
echo "  □ Monitoring and alerting configured"
echo "  □ Load testing completed"
echo "  □ Rollback procedure documented and tested"
echo ""
echo "See PRODUCTION_READY.md for full checklist"
