#!/usr/bin/env bash
# Søk på bleikoya.net med applikasjonspassord.
#
# Bruk:
#   sok.sh q=vedtekter
#   sok.sh type=category category=dugnad
#   sok.sh type=events after=2025-01-01 before=2025-12-31
#   sok.sh --post 1234        # hent hele innlegget med id 1234
#   sok.sh --sjekk            # test at innloggingen virker
#
# Innlogging leses fra $BLEIKOYA_CREDENTIALS, eller ~/.config/bleikoya/credentials.
# Fila skal inneholde:
#   BLEIKOYA_USER=brukernavn
#   BLEIKOYA_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx

set -euo pipefail

SITE="${BLEIKOYA_SITE:-https://bleikoya.net}"
CRED_FILE="${BLEIKOYA_CREDENTIALS:-$HOME/.config/bleikoya/credentials}"

if [[ -f "$CRED_FILE" ]]; then
	# shellcheck disable=SC1090
	source "$CRED_FILE"
fi

AUTH=()
if [[ -n "${BLEIKOYA_USER:-}" && -n "${BLEIKOYA_APP_PASSWORD:-}" ]]; then
	AUTH=(-u "$BLEIKOYA_USER:$BLEIKOYA_APP_PASSWORD")
else
	echo "ADVARSEL: Fant ikke innlogging i $CRED_FILE. Søker uten innlogging, private innlegg blir ikke med." >&2
fi

pretty() {
	if command -v jq >/dev/null 2>&1; then
		jq .
	elif command -v python3 >/dev/null 2>&1; then
		python3 -m json.tool
	else
		cat
	fi
}

urlencode() {
	local s="$1" out="" c
	for ((i = 0; i < ${#s}; i++)); do
		c="${s:i:1}"
		case "$c" in
			[a-zA-Z0-9.~_-]) out+="$c" ;;
			*) out+=$(printf '%%%02X' "'$c") ;;
		esac
	done
	printf '%s' "$out"
}

fetch() {
	local url="$1" code body
	body=$(curl -sS -w '\n%{http_code}' ${AUTH[@]+"${AUTH[@]}"} "$url")
	code="${body##*$'\n'}"
	body="${body%$'\n'*}"
	if [[ "$code" == "401" || "$code" == "403" ]]; then
		echo "FEIL: Innloggingen ble avvist ($code). Sjekk brukernavn og applikasjonspassord i $CRED_FILE." >&2
		exit 1
	elif [[ "$code" != "200" ]]; then
		echo "FEIL: $url svarte med HTTP $code" >&2
		echo "$body" >&2
		exit 1
	fi
	printf '%s' "$body"
}

case "${1:-}" in
	--sjekk)
		result=$(fetch "$SITE/wp-json/bleikoya/v1/search?type=events&limit=1")
		if echo "$result" | grep -q '"includes_private": *true'; then
			echo "OK: Innlogget som ${BLEIKOYA_USER:-?}. Private innlegg blir med i søk."
		else
			echo "IKKE INNLOGGET: Søket virker, men private innlegg blir ikke med."
			echo "Sjekk $CRED_FILE (se BRUKSANVISNING.md)."
			exit 2
		fi
		;;
	--post)
		[[ -n "${2:-}" ]] || { echo "Bruk: sok.sh --post <id>" >&2; exit 1; }
		fetch "$SITE/wp-json/wp/v2/posts/$2?_fields=id,date,title,link,status,content" | pretty
		;;
	-h|--help|"")
		sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'
		;;
	*)
		query=""
		for arg in "$@"; do
			key="${arg%%=*}"
			val="${arg#*=}"
			query+="&$(urlencode "$key")=$(urlencode "$val")"
		done
		fetch "$SITE/wp-json/bleikoya/v1/search?${query#&}" | pretty
		;;
esac
