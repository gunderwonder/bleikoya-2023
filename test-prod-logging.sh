#!/bin/bash
# Test logging i produksjon via SSH

echo "🧪 Tester error logging på prod-serveren..."
echo ""

ssh bleikoya.net@ssh.bleikoya.net "cd /www/beta/wp-content/themes/bleikoya-2023 && php test-logging-simple.php"

echo ""
echo "✅ Test fullført!"
echo ""
echo "📊 Sjekk logger i Grafana:"
echo "1. Gå til https://grafana.com/"
echo "2. Klikk 'Explore' i venstremenyen"
echo "3. Velg Loki data source"
echo "4. Bruk query: {app=\"bleikoya-net\", environment=\"production\"}"
echo "5. Logger skal vises innen 1-2 minutter"
