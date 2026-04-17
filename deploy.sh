#!/usr/bin/env bash
# =============================================================
#  deploy.sh — Deploy FTP incremental con git
#  Lee la config de .deploy.conf en la raíz del proyecto.
#
#  Uso:
#    bash deploy.sh             # incremental (desde último deploy)
#    bash deploy.sh --full      # sube todos los ficheros trackeados
#    bash deploy.sh --dry       # previsualiza sin subir nada
#    bash deploy.sh --since=abc # desde un commit concreto
#    bash deploy.sh --init      # crea .deploy.conf en este directorio
#    bash deploy.sh --help
# =============================================================
set -euo pipefail

VERSION="1.0.0"
CONF_FILE=".deploy.conf"
LAST_FILE=".last_deploy"

# ── Colores ───────────────────────────────────────────────────
R='\033[0;31m'; G='\033[0;32m'; Y='\033[0;33m'
C='\033[0;36m'; B='\033[1m'; D='\033[2m'; RST='\033[0m'

# ── Flags ─────────────────────────────────────────────────────
FULL=false; DRY=false; INIT=false; SINCE=""

for arg in "$@"; do
  case "$arg" in
    --full)      FULL=true ;;
    --dry)       DRY=true  ;;
    --init)      INIT=true ;;
    --since=*)   SINCE="${arg#*=}" ;;
    --help|-h)
      echo -e "${B}deploy.sh${RST} v$VERSION\n"
      echo    "  Deploy FTP incremental usando git como fuente de verdad."
      echo    ""
      echo    "Uso:"
      echo    "  bash deploy.sh [opciones]"
      echo    ""
      echo    "Opciones:"
      echo    "  --init          Crea .deploy.conf en el directorio actual"
      echo    "  --full          Sube todos los ficheros trackeados por git"
      echo    "  --dry           Muestra qué se subiría sin subir nada"
      echo    "  --since=HASH    Sube cambios desde el commit indicado"
      echo    "  --help          Muestra esta ayuda"
      echo    ""
      echo    "Config: .deploy.conf en la raíz del proyecto (no lo subas a git)"
      exit 0
      ;;
    *)
      echo -e "${R}Opción desconocida:${RST} $arg  (usa --help)"
      exit 1
      ;;
  esac
done

# ── --init ────────────────────────────────────────────────────
if $INIT; then
  if [ -f "$CONF_FILE" ]; then
    echo -e "${Y}Ya existe $CONF_FILE — edítalo directamente.${RST}"
    exit 0
  fi
  cat > "$CONF_FILE" << 'EOF'
# .deploy.conf — configuración de deploy FTP
# ¡Añade .deploy.conf a tu .gitignore antes de hacer commit!

HOST=ftp.example.com
PORT=21
USER=ftpuser
PASS=ftppassword
REMOTE=/public_html/mi-proyecto

# Patrones a excluir (regex extendida de grep -E, separados por |)
# EXCLUDE="\.sql$|README|deploy\.sh|node_modules|vendor|\.env"
EOF
  echo -e "${G}✓ Creado $CONF_FILE${RST} — rellena tus datos FTP."
  echo -e "  ${Y}Añade .deploy.conf a .gitignore antes de hacer commit.${RST}"
  exit 0
fi

# ── Leer .deploy.conf ─────────────────────────────────────────
if [ ! -f "$CONF_FILE" ]; then
  echo -e "${R}Error:${RST} no se encuentra $CONF_FILE"
  echo -e "Ejecuta ${B}bash deploy.sh --init${RST} para crear la plantilla."
  exit 1
fi

# shellcheck source=.deploy.conf
source "$CONF_FILE"

# Validar variables obligatorias
for var in HOST USER PASS REMOTE; do
  [ -n "${!var:-}" ] || { echo -e "${R}Error:${RST} $var no definido en $CONF_FILE"; exit 1; }
done

PORT="${PORT:-21}"
EXCLUDE="${EXCLUDE:-}"

# ── Comprobar dependencias ────────────────────────────────────
for cmd in curl git; do
  command -v "$cmd" >/dev/null || { echo -e "${R}Error: '$cmd' no encontrado.${RST}"; exit 1; }
done

git rev-parse --git-dir >/dev/null 2>&1 || {
  echo -e "${R}Error: no estás en un repositorio git.${RST}"; exit 1;
}

# ── Determinar qué ficheros subir ─────────────────────────────
CURRENT=$(git rev-parse HEAD)
SHORT="${CURRENT:0:7}"

if [ -n "$SINCE" ]; then
  FILES=$(git diff --name-only --diff-filter=d "$SINCE" HEAD)
  MODE="desde commit ${SINCE:0:7}"
elif $FULL; then
  FILES=$(git ls-files)
  MODE="completo"
elif [ -f "$LAST_FILE" ]; then
  PREV=$(cat "$LAST_FILE")
  FILES=$(git diff --name-only --diff-filter=d "$PREV" HEAD 2>/dev/null || git ls-files)
  MODE="incremental desde ${PREV:0:7}"
else
  FILES=$(git ls-files)
  MODE="completo (primer deploy)"
fi

# Aplicar exclusiones
if [ -n "$EXCLUDE" ] && [ -n "${FILES:-}" ]; then
  FILES=$(echo "$FILES" | grep -Ev "$EXCLUDE" || true)
fi

# ── Cabecera ──────────────────────────────────────────────────
PROJ=$(basename "$PWD")
echo -e "${C}${B}┌─ $PROJ${RST}${C} → ftp://$HOST:$PORT$REMOTE${RST}"
echo -e "${C}│${RST}  commit ${D}[$SHORT]${RST}  ·  modo: $MODE"

if [ -z "${FILES:-}" ]; then
  echo -e "${C}│${RST}"
  echo -e "${C}└─${RST} ${G}✓ Sin cambios. El servidor ya está al día.${RST}"
  exit 0
fi

COUNT=$(echo "$FILES" | grep -c . || echo 0)
echo -e "${C}│${RST}  $COUNT fichero(s) a subir:"
echo -e "${C}│${RST}"
while IFS= read -r f; do
  echo -e "${C}│${RST}    · $f"
done <<< "$FILES"
echo -e "${C}│${RST}"

# ── Dry run ───────────────────────────────────────────────────
if $DRY; then
  echo -e "${C}└─${RST} ${Y}--dry: nada subido.${RST}"
  exit 0
fi

# ── Upload via curl FTP ───────────────────────────────────────
ERRORS=0
while IFS= read -r file; do
  [ -f "$file" ] || continue   # ignorar ficheros borrados

  printf "${C}│${RST}  ↑ %-50s " "$file"

  ERR=$(curl --silent --show-error \
    --user "$USER:$PASS" \
    --ftp-create-dirs \
    -T "$file" \
    "ftp://$HOST:$PORT$REMOTE/$file" 2>&1) && OK=true || OK=false

  if $OK; then
    echo -e "${G}✓${RST}"
  else
    echo -e "${R}✗${RST}"
    echo -e "${C}│${RST}     ${D}${ERR}${RST}"
    ERRORS=$((ERRORS + 1))
  fi
done <<< "$FILES"

echo -e "${C}│${RST}"
if [ "$ERRORS" -eq 0 ]; then
  echo "$CURRENT" > "$LAST_FILE"
  echo -e "${C}└─${RST} ${G}${B}✓ Deploy OK${RST} — $COUNT fichero(s) subido(s)  ${D}[$SHORT]${RST}"
else
  echo -e "${C}└─${RST} ${R}⚠ $ERRORS error(es). Corrige y vuelve a ejecutar.${RST}"
  exit 1
fi
