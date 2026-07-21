#!/bin/bash
set -e

OUT="patch_ollama.sh"

cat > "$OUT" <<'PATCH'
#!/bin/bash
set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}AI_Agentina Ollama Patch${NC}"

[ -f main.py ] || { echo -e "${RED}main.py not found.${NC}"; exit 1; }
command -v python3 >/dev/null || { echo -e "${RED}python3 not installed.${NC}"; exit 1; }

BACKUP="main.py.backup.$(date +%Y%m%d_%H%M%S)"
cp main.py "$BACKUP"
echo -e "${GREEN}Backup: $BACKUP${NC}"

if grep -q "num_predict" main.py; then
  echo -e "${YELLOW}Patch already applied.${NC}"
  exit 0
fi

python3 <<'PY'
from pathlib import Path
import re
p=Path("main.py")
t=p.read_text()
pat=r'ollama\.chat\((.*?)messages=\s*\[(.*?)\]\s*\)'
m=re.search(pat,t,re.S)
if not m:
    raise SystemExit("ollama.chat() not found")
block="""ollama.chat(
%smessages=[
%s],
        options={
            "num_predict": 256,
            "num_ctx": 2048,
            "temperature": 0.2,
            "top_k": 20,
            "top_p": 0.8,
            "num_thread": 4
        }
)"""%(m.group(1),m.group(2))
t=t[:m.start()]+block+t[m.end():]
p.write_text(t)
PY

python3 -m py_compile main.py
echo -e "${GREEN}Patch applied successfully.${NC}"

read -p "Restart agent now? (y/N): " a
if [[ "$a" =~ ^[Yy]$ ]]; then
  pkill -f "python.*main.py" || true
  nohup python3 main.py >/tmp/ai_agent.log 2>&1 &
  echo -e "${GREEN}Agent restarted.${NC}"
fi
PATCH

chmod +x "$OUT"
echo "Created $OUT"
