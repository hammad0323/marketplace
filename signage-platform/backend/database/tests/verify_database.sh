#!/usr/bin/env bash
# =============================================================================
#  Phase 1 database verification.
#  Creates a throw-away database, loads schema.sql + seed.sql (FK checks ON),
#  then asserts structure, seed content, limit resolution and DB-level
#  cross-user protection.
#
#  Usage:  MYSQL="mysql -uroot -p" ./verify_database.sh
#          (defaults to plain `mysql`, database `signage_verify`)
# =============================================================================
set -u
DIR="$(cd "$(dirname "$0")/.." && pwd)"
MYSQL="${MYSQL:-mysql}"
DB="${DB:-signage_verify}"
PASS=0; FAIL=0

q()  { $MYSQL -N -B "$DB" -e "$1" 2>&1; }
ok() { PASS=$((PASS+1)); printf '  \033[32m✔\033[0m %s\n' "$1"; }
ko() { FAIL=$((FAIL+1)); printf '  \033[31m✘\033[0m %s  (got: %s)\n' "$1" "$2"; }
eq() { local got; got="$(q "$2")"; [ "$got" = "$3" ] && ok "$1" || ko "$1" "$got"; }
ge() { local got; got="$(q "$2")"; [ "$got" -ge "$3" ] 2>/dev/null && ok "$1 ($got)" || ko "$1" "$got"; }
fails() { local out; out="$($MYSQL -N -B "$DB" -e "$2" 2>&1)"; [ $? -ne 0 ] && ok "$1" || ko "$1 — statement unexpectedly succeeded" "$out"; }

echo "▶ Loading schema + seed into '$DB'"
$MYSQL -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || exit 1
$MYSQL "$DB" < "$DIR/schema.sql" || { echo "schema.sql failed"; exit 1; }
$MYSQL "$DB" < "$DIR/seed.sql"   || { echo "seed.sql failed";   exit 1; }

echo "▶ Structure"
ge "base tables"               "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB' AND table_type='BASE TABLE'" 39
eq "all tables InnoDB"         "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB' AND table_type='BASE TABLE' AND engine<>'InnoDB'" 0
eq "all tables utf8mb4"        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB' AND table_type='BASE TABLE' AND table_collation NOT LIKE 'utf8mb4%'" 0
ge "foreign keys"              "SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema='$DB'" 50
for t in users screens screen_widgets playlists playlist_items devices device_playlists media_library device_logs; do
  ge "$t has indexes"          "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema='$DB' AND table_name='$t'" 2
done
eq "views present"             "SELECT COUNT(*) FROM information_schema.views WHERE table_schema='$DB'" 2

echo "▶ Seed content"
eq "super admin exists"        "SELECT COUNT(*) FROM users u JOIN roles r USING(role_id) WHERE r.role_key='super_admin' AND u.email='admin@signage.local'" 1
eq "5 plans"                   "SELECT COUNT(*) FROM plans" 5
eq "14 template categories"    "SELECT COUNT(*) FROM template_categories" 14
ge "templates (>=20)"          "SELECT COUNT(*) FROM screen_templates" 20
eq "every category has a template" "SELECT COUNT(*) FROM template_categories c WHERE NOT EXISTS (SELECT 1 FROM screen_templates t WHERE t.category_id=c.category_id)" 0
ge "portrait templates"        "SELECT COUNT(*) FROM screen_templates WHERE orientation='portrait'" 5
ge "landscape templates"       "SELECT COUNT(*) FROM screen_templates WHERE orientation='landscape'" 10
ge "widget types (>=30)"       "SELECT COUNT(*) FROM widget_types" 30
eq "Bilal has 5 screens"       "SELECT COUNT(*) FROM screens WHERE user_id=3" 5
eq "playlist 1 order"          "SELECT GROUP_CONCAT(s.screen_name ORDER BY pi.sort_order SEPARATOR ' > ') FROM playlist_items pi JOIN screens s USING(screen_id) WHERE pi.playlist_id=1" "Welcome Screen > Promotional Screen > Product Screen > Company Information Screen > Contact Screen"
eq "sample media files exist"  "SELECT COUNT(*) FROM media_library WHERE checksum_sha256 IS NULL" 0

echo "▶ JSON validity"
eq "template_json valid"       "SELECT COUNT(*) FROM screen_templates WHERE JSON_VALID(template_json)=0" 0
eq "widget configs valid"      "SELECT COUNT(*) FROM screen_widgets WHERE JSON_VALID(configuration_json)=0" 0
eq "publications valid"        "SELECT (SELECT COUNT(*) FROM screen_publications WHERE JSON_VALID(published_json)=0)+(SELECT COUNT(*) FROM playlist_publications WHERE JSON_VALID(published_json)=0)" 0
eq "template schema_version=1" "SELECT COUNT(*) FROM screen_templates WHERE JSON_EXTRACT(template_json,'$.schema_version')<>1" 0
# Every widget referenced in a template exists in the registry
MISSING=$(q "SELECT DISTINCT jt.wtype FROM screen_templates t, JSON_TABLE(t.template_json, '\$.widgets[*]' COLUMNS (wtype VARCHAR(60) PATH '\$.widget_type')) jt WHERE jt.wtype COLLATE utf8mb4_unicode_ci NOT IN (SELECT widget_key FROM widget_types)" )
[ -z "$MISSING" ] && ok "template widget types all registered" || ko "template widget types all registered" "$MISSING"
# Normalised geometry; designs may bleed slightly off the canvas edge (like a cropped photo), never far.
eq "template widget geometry normalised" "SELECT COUNT(*) FROM screen_templates t, JSON_TABLE(t.template_json, '\$.widgets[*]' COLUMNS (x DOUBLE PATH '\$.position_x', y DOUBLE PATH '\$.position_y', w DOUBLE PATH '\$.width', h DOUBLE PATH '\$.height')) jt WHERE jt.x<-0.25 OR jt.y<-0.25 OR jt.x+jt.w>1.25 OR jt.y+jt.h>1.25 OR jt.w<=0 OR jt.h<=0" 0
eq "template media are shared (global)" "SELECT COUNT(*) FROM screen_templates t, JSON_TABLE(t.template_json, '\$.widgets[*]' COLUMNS (m INT PATH '\$.configuration.media_id')) jt JOIN media_library ml ON ml.media_id = jt.m WHERE ml.is_global = 0" 0
eq "Aisha restricted to 2 template categories" "SELECT COUNT(*) FROM user_template_access WHERE user_id = 2" 2

echo "▶ Limits & permissions"
eq "User A max_screens=2"      "SELECT max_screens FROM v_user_effective_limits WHERE user_id=2" 2
eq "User B max_screens=5"      "SELECT max_screens FROM v_user_effective_limits WHERE user_id=3" 5
eq "User C max_screens=10"     "SELECT max_screens FROM v_user_effective_limits WHERE user_id=4" 10
eq "User D unlimited (-1)"     "SELECT max_screens FROM v_user_effective_limits WHERE user_id=5" -1
eq "User B playlists from override" "SELECT max_playlists FROM v_user_effective_limits WHERE user_id=3" 3
eq "User B storage from plan"  "SELECT max_storage_mb FROM v_user_effective_limits WHERE user_id=3" 1024
eq "Free plan blocks video"    "SELECT can_upload_video FROM v_user_effective_limits WHERE user_id=6" 0
eq "User A at screen limit"    "SELECT (u.screens_used >= l.max_screens) FROM v_user_usage u JOIN v_user_effective_limits l USING(user_id) WHERE user_id=2" 1
eq "User B storage usage > 0"  "SELECT storage_used_bytes > 0 FROM v_user_usage WHERE user_id=3" 1
eq "User A video revoked"      "SELECT allowed FROM user_permissions up JOIN permissions p USING(permission_id) WHERE up.user_id=2 AND p.permission_key='media.upload_video'" 0
eq "user role lacks admin.access" "SELECT COUNT(*) FROM role_permissions rp JOIN permissions p USING(permission_id) WHERE rp.role_id=2 AND p.permission_key LIKE 'admin.%'" 0

echo "▶ Device pairing"
eq "pairing code resolves"     "SELECT d.device_code FROM device_pairing_codes c JOIN devices d USING(device_id) WHERE c.pairing_code_hash=SHA2('K7Q4ZP',256) AND c.used_at IS NULL AND c.expires_at>NOW()" "DSP-3H8R"
eq "device token resolves"     "SELECT device_id FROM devices WHERE device_token_hash=SHA2('dev-device-token-lobby-tv',256)" 1
eq "device 1 default playlist" "SELECT playlist_id FROM device_playlists WHERE device_id=1 AND is_default=1" 1

echo "▶ Integrity guards (each statement MUST fail)"
fails "playlist cannot include another user's screen" \
      "INSERT INTO playlist_items (playlist_id, screen_id, user_id, sort_order) VALUES (1, 6, 3, 9)"
fails "…nor by spoofing the user_id column" \
      "INSERT INTO playlist_items (playlist_id, screen_id, user_id, sort_order) VALUES (1, 6, 2, 9)"
fails "device cannot get another user's playlist" \
      "INSERT INTO playlists (playlist_id, playlist_uid, user_id, playlist_name) VALUES (99,'x-99',2,'Aisha PL'); INSERT INTO device_playlists (device_id, playlist_id, user_id) VALUES (1, 99, 3)"
fails "unpaired device cannot receive playlists" \
      "INSERT INTO device_playlists (device_id, playlist_id, user_id) VALUES (3, 1, 3)"
fails "duplicate email rejected"   "INSERT INTO users (role_id, full_name, email, password_hash) VALUES (2,'Dup','bilal@demo.local','x')"
fails "invalid JSON rejected"      "UPDATE screen_widgets SET configuration_json='{bad' WHERE widget_id=1"
fails "unknown widget type rejected" "INSERT INTO screen_widgets (widget_uid, screen_id, widget_type, configuration_json) VALUES ('u1', 1, 'not_a_widget', '{}')"
fails "negative plan limit < -1 rejected" "UPDATE plans SET max_screens=-5 WHERE plan_id=1"
fails "media in use cannot be hard-deleted" "DELETE FROM media_library WHERE media_id=1"
fails "end_date before start_date rejected" "UPDATE playlist_schedules SET end_date='2020-01-01' WHERE schedule_id=1"

echo "▶ Cascades"
q "DELETE FROM screens WHERE screen_id=5" >/dev/null
eq "screen delete cascades widgets"        "SELECT COUNT(*) FROM screen_widgets WHERE screen_id=5" 0
eq "screen delete cascades playlist items" "SELECT COUNT(*) FROM playlist_items WHERE screen_id=5" 0

echo
echo "Result: $PASS passed, $FAIL failed"
$MYSQL -e "DROP DATABASE IF EXISTS \`$DB\`;"
[ "$FAIL" -eq 0 ]
