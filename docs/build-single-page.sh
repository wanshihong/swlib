#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCS_DIR="${SCRIPT_DIR}"
META_DIR="${DOCS_DIR}/_meta"
ORDER_FILE="${META_DIR}/order.json"
TEMPLATE_FILE="${META_DIR}/single-page-template.html"
OUTPUT_FILE="${DOCS_DIR}/swlib-manual.html"

if [[ ! -f "${ORDER_FILE}" ]]; then
  echo "[build-single-page] missing order file: ${ORDER_FILE}" >&2
  exit 1
fi

if [[ ! -f "${TEMPLATE_FILE}" ]]; then
  echo "[build-single-page] missing template file: ${TEMPLATE_FILE}" >&2
  exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
  echo "[build-single-page] python3 is required" >&2
  exit 1
fi

TMP_JSON="$(mktemp)"
trap 'rm -f "${TMP_JSON}"' EXIT

python3 - "${DOCS_DIR}" "${ORDER_FILE}" > "${TMP_JSON}" <<'PY'
import json
import os
import sys
from datetime import datetime

docs_dir = sys.argv[1]
order_file = sys.argv[2]

with open(order_file, "r", encoding="utf-8") as f:
    order = json.load(f)

seen = set()
docs = []
warnings = []

for group in order.get("groups", []):
    group_name = group.get("name", "未命名分组")
    for rel in group.get("files", []):
        path = os.path.join(docs_dir, rel)
        if not os.path.isfile(path):
            warnings.append(f"missing file in order.json: {rel}")
            continue
        with open(path, "r", encoding="utf-8") as md:
            raw = md.read()
        first = "未命名文档"
        for line in raw.splitlines():
            if line.strip().startswith("#"):
                first = line.lstrip("#").strip()
                break
        docs.append({
            "group": group_name,
            "file": rel,
            "title": first,
            "markdown": raw,
        })
        seen.add(rel)

for name in sorted(os.listdir(docs_dir)):
    if not name.endswith(".md"):
        continue
    if name in seen:
        continue
    p = os.path.join(docs_dir, name)
    with open(p, "r", encoding="utf-8") as md:
        raw = md.read()
    first = "未命名文档"
    for line in raw.splitlines():
        if line.strip().startswith("#"):
            first = line.lstrip("#").strip()
            break
    docs.append({
        "group": "未分类",
        "file": name,
        "title": first,
        "markdown": raw,
    })
    warnings.append(f"unlisted file appended to 未分类: {name}")

payload = {
    "generated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
    "docs": docs,
    "warnings": warnings,
}

print(json.dumps(payload, ensure_ascii=False))
for w in warnings:
    print(f"[build-single-page] warning: {w}", file=sys.stderr)
PY

python3 - "${TEMPLATE_FILE}" "${TMP_JSON}" "${OUTPUT_FILE}" <<'PY'
import sys

template_path, payload_path, output_path = sys.argv[1], sys.argv[2], sys.argv[3]
with open(template_path, "r", encoding="utf-8") as f:
    template = f.read()
with open(payload_path, "r", encoding="utf-8") as f:
    payload = f.read().replace("</script>", "<\\/script>")
html = template.replace("__DOC_PAYLOAD__", payload)
with open(output_path, "w", encoding="utf-8") as f:
    f.write(html)
PY

echo "[build-single-page] generated ${OUTPUT_FILE}"
