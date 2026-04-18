#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/home/kassym/soft_dev/CS4116-HarmonyMatch"
BASE_URL="http://127.0.0.1:8091"
SERVER_LOG="/tmp/harmonymatch_smoke_server.log"
SERVER_PID=""
TMP_DIR="$(mktemp -d)"
SUFFIX="$(date +%s)"

cleanup() {
  if [[ -n "${SERVER_PID}" ]] && kill -0 "${SERVER_PID}" 2>/dev/null; then
    kill "${SERVER_PID}" 2>/dev/null || true
  fi
  rm -rf "${TMP_DIR}"
}
trap cleanup EXIT

json_field() {
  local field="$1"
  php -r '$data=json_decode(stream_get_contents(STDIN), true); $field=$argv[1]; if (is_array($data) && array_key_exists($field,$data)) { if (is_bool($data[$field])) { echo $data[$field] ? "true" : "false"; } else { echo is_scalar($data[$field]) ? $data[$field] : json_encode($data[$field]); }}' "$field"
}

assert_contains() {
  local haystack="$1"
  local needle="$2"
  local label="$3"
  if [[ "${haystack}" != *"${needle}"* ]]; then
    echo "FAIL: ${label}" >&2
    echo "${haystack}" >&2
    exit 1
  fi
  echo "PASS: ${label}"
}

php -S 127.0.0.1:8091 -t "${APP_ROOT}" > "${SERVER_LOG}" 2>&1 &
SERVER_PID=$!
sleep 2

ADMIN_EMAIL="admin.smoke.${SUFFIX}@harmonymatch.local"
ADMIN_PASSWORD="AdminPass1!"

php -r '
require "'"${APP_ROOT}"'/config/database.php";
$db = Database::getConnection();
$email = $argv[1];
$password = $argv[2];
$stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$id = $stmt->fetchColumn();
if (!$id) {
  $db->beginTransaction();
  $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, status, created_at, updated_at) VALUES (?, ?, \"admin\", \"active\", NOW(), NOW())");
  $stmt->execute([$email, password_hash($password, PASSWORD_BCRYPT)]);
  $id = (int)$db->lastInsertId();
  $stmt = $db->prepare("INSERT INTO profiles (user_id, display_name, bio, birth_year, visibility, updated_at) VALUES (?, ?, ?, ?, \"public\", NOW())");
  $stmt->execute([$id, "Smoke Admin", "Temporary admin for smoke tests.", 1995]);
  $stmt = $db->prepare("INSERT INTO user_preferences (user_id, gender_id, seeking_type, min_age_pref, max_age_pref, updated_at) VALUES (?, NULL, \"dating\", 18, 40, NOW())");
  $stmt->execute([$id]);
  $db->commit();
}
echo $id;
' "${ADMIN_EMAIL}" "${ADMIN_PASSWORD}" > /dev/null

USER_A_EMAIL="smoke.a.${SUFFIX}@harmonymatch.local"
USER_B_EMAIL="smoke.b.${SUFFIX}@harmonymatch.local"
USER_PASSWORD="SmokePass1!"
COOKIE_A="${TMP_DIR}/a.cookie"
COOKIE_B="${TMP_DIR}/b.cookie"
COOKIE_ADMIN="${TMP_DIR}/admin.cookie"
GENRES_A="$(php -r 'require "'"${APP_ROOT}"'/config/database.php"; $db=Database::getConnection(); $wanted=["Indie Rock","Alternative Rock","Pop"]; $ids=[]; $stmt=$db->prepare("SELECT genre_id FROM genres WHERE name = ? LIMIT 1"); foreach($wanted as $name){$stmt->execute([$name]); $id=$stmt->fetchColumn(); if($id){$ids[]=$id;}} echo implode(",", $ids);')"
GENRES_B="$(php -r 'require "'"${APP_ROOT}"'/config/database.php"; $db=Database::getConnection(); $wanted=["Pop","Dance","Electronic"]; $ids=[]; $stmt=$db->prepare("SELECT genre_id FROM genres WHERE name = ? LIMIT 1"); foreach($wanted as $name){$stmt->execute([$name]); $id=$stmt->fetchColumn(); if($id){$ids[]=$id;}} echo implode(",", $ids);')"

RESP_A=$(curl -sS -c "${COOKIE_A}" -b "${COOKIE_A}" -X POST "${BASE_URL}/api/auth.php?action=register" \
  --data-urlencode "email=${USER_A_EMAIL}" \
  --data-urlencode "password=${USER_PASSWORD}" \
  --data-urlencode "name=Smoke Alpha" \
  --data-urlencode "dob=2000-01-02" \
  --data-urlencode "gender=male")
assert_contains "${RESP_A}" '"success":true' "registration user A"
USER_A_ID=$(printf '%s' "${RESP_A}" | json_field user_id)

RESP_B=$(curl -sS -c "${COOKIE_B}" -b "${COOKIE_B}" -X POST "${BASE_URL}/api/auth.php?action=register" \
  --data-urlencode "email=${USER_B_EMAIL}" \
  --data-urlencode "password=${USER_PASSWORD}" \
  --data-urlencode "name=Smoke Beta" \
  --data-urlencode "dob=1999-03-04" \
  --data-urlencode "gender=female")
assert_contains "${RESP_B}" '"success":true' "registration user B"
USER_B_ID=$(printf '%s' "${RESP_B}" | json_field user_id)

curl -sS -c "${COOKIE_A}" -b "${COOKIE_A}" -X POST "${BASE_URL}/api/users.php?action=update_profile" \
  --data-urlencode "name=Smoke Alpha" \
  --data-urlencode "bio=Indie listener for smoke testing." \
  --data-urlencode "location=Dublin, Ireland" > /dev/null
curl -sS -c "${COOKIE_B}" -b "${COOKIE_B}" -X POST "${BASE_URL}/api/users.php?action=update_profile" \
  --data-urlencode "name=Smoke Beta" \
  --data-urlencode "bio=Pop listener for smoke testing." \
  --data-urlencode "location=Cork, Ireland" > /dev/null

RESP_A_MUSIC=$(curl -sS -c "${COOKIE_A}" -b "${COOKIE_A}" -X POST "${BASE_URL}/api/users.php?action=onboarding_music" \
  --data-urlencode "genres=${GENRES_A}" \
  --data-urlencode "artists[]=Arctic Monkeys" \
  --data-urlencode "artists[]=The 1975" \
  --data-urlencode "songs=[{\"title\":\"Do I Wanna Know?\",\"artist\":\"Arctic Monkeys\"},{\"title\":\"About You\",\"artist\":\"The 1975\"}]")
assert_contains "${RESP_A_MUSIC}" '"success":true' "onboarding music user A"

RESP_B_MUSIC=$(curl -sS -c "${COOKIE_B}" -b "${COOKIE_B}" -X POST "${BASE_URL}/api/users.php?action=onboarding_music" \
  --data-urlencode "genres=${GENRES_B}" \
  --data-urlencode "artists[]=Taylor Swift" \
  --data-urlencode "artists[]=Dua Lipa" \
  --data-urlencode "songs=[{\"title\":\"Style\",\"artist\":\"Taylor Swift\"},{\"title\":\"Levitating\",\"artist\":\"Dua Lipa\"}]")
assert_contains "${RESP_B_MUSIC}" '"success":true' "onboarding music user B"

assert_contains "$(curl -sS -c "${COOKIE_A}" -b "${COOKIE_A}" -X POST "${BASE_URL}/api/users.php?action=complete_onboarding")" '"success":true' "complete onboarding user A"
assert_contains "$(curl -sS -c "${COOKIE_B}" -b "${COOKIE_B}" -X POST "${BASE_URL}/api/users.php?action=complete_onboarding")" '"success":true' "complete onboarding user B"

SEARCH_RESP=$(curl -sS -c "${COOKIE_A}" -b "${COOKIE_A}" "${BASE_URL}/api/users.php?action=search&query=Smoke%20Beta&min_age=18&max_age=40&min_compatibility=0")
assert_contains "${SEARCH_RESP}" 'Smoke Beta' "search returns other user"

LIKE_A=$(curl -sS -c "${COOKIE_A}" -b "${COOKIE_A}" -X POST "${BASE_URL}/api/matches.php" \
  --data-urlencode "action=swipe" \
  --data-urlencode "to_user_id=${USER_B_ID}" \
  --data-urlencode "action_type=like")
assert_contains "${LIKE_A}" '"is_match":false' "first like is pending"

LIKE_B=$(curl -sS -c "${COOKIE_B}" -b "${COOKIE_B}" -X POST "${BASE_URL}/api/matches.php" \
  --data-urlencode "action=swipe" \
  --data-urlencode "to_user_id=${USER_A_ID}" \
  --data-urlencode "action_type=like")
assert_contains "${LIKE_B}" '"is_match":true' "mutual like becomes match"

MSG_OK=$(curl -sS -c "${COOKIE_A}" -b "${COOKIE_A}" -X POST "${BASE_URL}/api/messages.php" \
  --data-urlencode "action=send" \
  --data-urlencode "to_user_id=${USER_B_ID}" \
  --data-urlencode "content=Hey from the smoke test")
assert_contains "${MSG_OK}" '"success":true' "matched users can message"

MSG_PHONE=$(curl -sS -c "${COOKIE_A}" -b "${COOKIE_A}" -X POST "${BASE_URL}/api/messages.php" \
  --data-urlencode "action=send" \
  --data-urlencode "to_user_id=${USER_B_ID}" \
  --data-urlencode "content=Call me on 0871234567")
assert_contains "${MSG_PHONE}" 'Phone numbers are not allowed in chat' "phone number blocked in chat"

BLOCK_RESP=$(curl -sS -c "${COOKIE_B}" -b "${COOKIE_B}" -X POST "${BASE_URL}/api/reports.php" \
  --data-urlencode "action=block" \
  --data-urlencode "blocked_id=${USER_A_ID}")
assert_contains "${BLOCK_RESP}" '"success":true' "block user"

MSG_BLOCKED=$(curl -sS -c "${COOKIE_A}" -b "${COOKIE_A}" -X POST "${BASE_URL}/api/messages.php" \
  --data-urlencode "action=send" \
  --data-urlencode "to_user_id=${USER_B_ID}" \
  --data-urlencode "content=You should not receive this")
assert_contains "${MSG_BLOCKED}" 'Users cannot message each other' "blocked users cannot message"

REPORT_RESP=$(curl -sS -c "${COOKIE_B}" -b "${COOKIE_B}" -X POST "${BASE_URL}/api/reports.php" \
  --data-urlencode "action=report" \
  --data-urlencode "reported_id=${USER_A_ID}" \
  --data-urlencode "reason=harassment in test flow")
assert_contains "${REPORT_RESP}" '"success":true' "report flow submit"
REPORT_ID=$(printf '%s' "${REPORT_RESP}" | json_field report_id)

ADMIN_LOGIN=$(curl -sS -c "${COOKIE_ADMIN}" -b "${COOKIE_ADMIN}" -X POST "${BASE_URL}/api/auth.php?action=login" \
  --data-urlencode "email=${ADMIN_EMAIL}" \
  --data-urlencode "password=${ADMIN_PASSWORD}")
assert_contains "${ADMIN_LOGIN}" '"success":true' "admin login"

RESOLVE_RESP=$(curl -sS -c "${COOKIE_ADMIN}" -b "${COOKIE_ADMIN}" -X POST "${BASE_URL}/api/admin.php" \
  --data-urlencode "action=resolve_report" \
  --data-urlencode "report_id=${REPORT_ID}" \
  --data-urlencode "resolution=actioned")
assert_contains "${RESOLVE_RESP}" '"success":true' "admin resolves report and actions user"

LOGIN_SUSPENDED=$(curl -sS -c "${COOKIE_A}" -b "${COOKIE_A}" -X POST "${BASE_URL}/api/auth.php?action=login" \
  --data-urlencode "email=${USER_A_EMAIL}" \
  --data-urlencode "password=${USER_PASSWORD}")
assert_contains "${LOGIN_SUSPENDED}" 'Account suspended' "suspended user cannot log in"

echo "Smoke test completed successfully."
