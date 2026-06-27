from pathlib import Path
import os, re, zipfile, shutil, datetime

root = Path(".")
old = root / "old_version"
old.mkdir(exist_ok=True)

command = os.getenv("MANUAL_COMMAND") or "audit"
release_version = os.getenv("RELEASE_VERSION") or "1.0.0"

def read(p):
    try:
        return p.read_text(encoding="utf-8", errors="ignore")
    except Exception:
        return ""

def header(txt, key):
    m = re.search(rf"{key}:\s*(.+)", txt, re.I)
    return m.group(1).strip(" */\t") if m else ""

def vkey(v):
    nums = re.findall(r"\d+", v or "0")
    return tuple(map(int, nums[:5])) if nums else (0,)

def slug(s):
    s = s.lower()
    s = re.sub(r"[^a-z0-9_-]+", "-", s)
    return re.sub(r"-+", "-", s).strip("-") or "wordpress-plugin"

candidates = []

for p in root.rglob("*"):
    if not p.is_file() or ".git" in p.parts:
        continue

    rel = p.as_posix()

    if p.suffix.lower() in [".php", ".txt", ".bak"]:
        txt = read(p)
        if "Plugin Name:" in txt:
            candidates.append({
                "path": rel,
                "type": p.suffix.lower(),
                "name": header(txt, "Plugin Name"),
                "version": header(txt, "Version"),
                "size": p.stat().st_size,
                "files": 1,
                "zip_main": ""
            })

    if p.suffix.lower() == ".zip":
        try:
            with zipfile.ZipFile(p) as z:
                files = [i for i in z.infolist() if not i.is_dir()]
                for i in files:
                    if i.filename.lower().endswith(".php"):
                        txt = z.read(i.filename).decode("utf-8", errors="ignore")
                        if "Plugin Name:" in txt:
                            candidates.append({
                                "path": rel,
                                "type": "zip",
                                "name": header(txt, "Plugin Name"),
                                "version": header(txt, "Version"),
                                "size": p.stat().st_size,
                                "files": len(files),
                                "zip_main": i.filename
                            })
                            break
        except Exception:
            pass

selected = None
if candidates:
    selected = sorted(
        candidates,
        key=lambda c: (vkey(c["version"]), c["files"], c["size"], 1 if c["type"] == "zip" else 0),
        reverse=True
    )[0]

lines = [
    "# Project Status",
    "",
    f"Audit date: {datetime.datetime.utcnow()} UTC",
    "",
    "## Candidates",
    "",
    "| Source | Type | Plugin Name | Version | Size | Files | Result |",
    "|---|---|---|---:|---:|---:|---|",
]

for c in candidates:
    result = "SELECTED" if c == selected else "Candidate"
    lines.append(f"| `{c['path']}` | {c['type']} | {c['name']} | {c['version']} | {c['size']} | {c['files']} | {result} |")

if not selected:
    lines.append("")
    lines.append("No valid WordPress plugin source found. Manual review required.")
    Path("PROJECT_STATUS.md").write_text("\n".join(lines), encoding="utf-8")
    raise SystemExit(0)

plugin_slug = slug(Path(selected["zip_main"] or selected["path"]).stem)
active = root / plugin_slug
main_file = active / f"{plugin_slug}.php"

if command in ["setup", "release"]:
    if active.exists():
        shutil.copytree(active, old / f"{plugin_slug}-backup-{datetime.datetime.utcnow().strftime('%Y%m%d%H%M%S')}")
        shutil.rmtree(active)

    active.mkdir(exist_ok=True)

    if selected["type"] == "zip":
        temp = root / ".tmp_extract"
        if temp.exists():
            shutil.rmtree(temp)
        temp.mkdir()
        with zipfile.ZipFile(selected["path"]) as z:
            z.extractall(temp)
        src = temp / selected["zip_main"]
        src_dir = src.parent
        shutil.copytree(src_dir, active, dirs_exist_ok=True)
        shutil.rmtree(temp)
    else:
        shutil.copy2(selected["path"], main_file)

    for d in ["admin", "includes", "public", "assets", "templates", "languages"]:
        (active / d).mkdir(exist_ok=True)

    if not main_file.exists():
        for p in active.rglob("*.php"):
            if "Plugin Name:" in read(p):
                p.rename(main_file)
                break

    for p in list(root.iterdir()):
        if p.name in [plugin_slug, ".github", "old_version", "scripts"]:
            continue
        if p.suffix.lower() in [".zip", ".bak"] or (p.suffix.lower() == ".php" and "Plugin Name:" in read(p)):
            target = old / p.name
            if target.exists():
                target = old / f"{datetime.datetime.utcnow().strftime('%Y%m%d%H%M%S')}-{p.name}"
            shutil.move(str(p), str(target))

Path("README.md").write_text(f"# {selected['name']}\n\nActive source: `{plugin_slug}/`\n\nMain file: `{plugin_slug}/{plugin_slug}.php`\n", encoding="utf-8")
Path("CHANGELOG.md").write_text(f"# Changelog\n\n## Setup\n\n- Selected source: `{selected['path']}`\n- Active folder: `{plugin_slug}/`\n", encoding="utf-8")
Path("AI_RULES.md").write_text(f"# AI Rules\n\nActive folder: `{plugin_slug}/`\nMain file: `{plugin_slug}/{plugin_slug}.php`\nOld versions: `old_version/`\n", encoding="utf-8")
Path("TODO.md").write_text("# TODO\n\n- Test plugin activation.\n- Test release ZIP.\n- Test updater if exists.\n", encoding="utf-8")
Path("LICENSE").write_text("GPL-2.0-or-later\n", encoding="utf-8")
Path(".gitignore").write_text("*.log\n*.tmp\n.tmp_extract/\nbuild/\ndist/\n", encoding="utf-8")

lines += [
    "",
    "## Selected source",
    "",
    f"- Selected: `{selected['path']}`",
    f"- Plugin Name: {selected['name']}",
    f"- Version: {selected['version']}",
    f"- Active folder: `{plugin_slug}/`",
    f"- Main file: `{plugin_slug}/{plugin_slug}.php`",
]

Path("PROJECT_STATUS.md").write_text("\n".join(lines), encoding="utf-8") 
