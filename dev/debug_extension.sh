#!/usr/bin/env bash
# Debug script to test FieldPermissions extension

echo "=== FieldPermissions Debugging Guide ==="
echo ""
echo "1. First, access a page with SMW queries in your browser:"
echo "   http://localhost:8888/w/index.php/Query:Employees"
echo "   or"
echo "   http://localhost:8888/w/index.php/Employee:Alice"
echo ""
echo "2. Then run this command to see the latest logs:"
echo "   docker compose exec -T mediawiki tail -n 50 /var/log/fieldpermissions/fieldpermissions.log"
echo ""
echo "3. What to look for in the logs:"
echo "   - 'onSMWResultArrayBeforePrint hook called' - confirms hook is invoked"
echo "   - 'Checking property: Email, level: X' - shows property resolution"
echo "   - 'Blocking property...' or 'Allowing property...' - shows filtering decisions"
echo ""
echo "4. If you don't see 'onSMWResultArrayBeforePrint hook called', the hook may not be registered."
echo ""
echo "5. To test property resolution manually, check if properties have visibility annotations:"
echo "   - Visit Property:Email page - should have [[Has visibility level::Visibility:Internal]]"
echo "   - Visit Property:Salary page - should have [[Has visibility level::Visibility:Private]]"
echo ""
echo "=== Running test now ==="
echo "Access a page now, then press Enter to check logs..."
read

cd ~/.cache/fieldpermissions/mediawiki-FieldPermissions-test
docker compose exec -T mediawiki tail -n 50 /var/log/fieldpermissions/fieldpermissions.log

