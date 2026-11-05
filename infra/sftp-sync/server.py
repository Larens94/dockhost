"""sftp-sync sidecar: user sync + jail-scoped file API (panel only, Bearer token)."""

import json
import os
import http.server
import shutil
import urllib.parse

TOKEN = os.environ.get("SFTP_SYNC_TOKEN", "")
MAX_UPLOAD_BYTES = 64 * 1024 * 1024
USERS_CONF = "/etc/sftp/users.conf"


def under_data(path: str) -> bool:
    path = os.path.realpath(str(path))
    return path == "/data" or path.startswith("/data/")


def read_user_uids() -> dict[str, int]:
    uids: dict[str, int] = {}
    if not os.path.isfile(USERS_CONF):
        return uids
    with open(USERS_CONF, encoding="utf-8") as handle:
        for line in handle:
            parts = line.strip().split(":")
            if len(parts) >= 4 and parts[0]:
                try:
                    uids[parts[0]] = int(parts[2])
                except ValueError:
                    continue
    return uids


def jail_root(username: str) -> str:
    name = str(username or "").strip()
    if not name or ":" in name or "/" in name or ".." in name:
        raise ValueError("invalid jail")
    jail = os.path.realpath("/data/" + name)
    if not under_data(jail) or jail == "/data":
        raise ValueError("invalid jail")
    return jail


def normalize_rel_path(raw: str) -> str:
    path = str(raw or "").strip().replace("\\", "/")
    path = path.lstrip("/")
    if path == "" or ".." in path.split("/"):
        raise ValueError("invalid path")
    return path


def resolve_in_jail(username: str, rel_path: str, must_exist: bool = True) -> str:
    root = jail_root(username)
    rel = normalize_rel_path(rel_path)
    target = os.path.realpath(os.path.join(root, rel)) if rel else root
    if not target.startswith(root + os.sep) and target != root:
        raise ValueError("path escape")
    if must_exist and not os.path.exists(target):
        raise ValueError("not found")
    return target


def chown_tree(path: str, uid: int, gid: int | None = None) -> None:
    gid = gid if gid is not None else uid
    if not os.path.exists(path):
        return
    os.chown(path, uid, gid)
    if os.path.isdir(path):
        for entry in os.listdir(path):
            chown_tree(os.path.join(path, entry), uid, gid)


def ensure_jail(name: str, desired: str | None) -> str:
    jail = os.path.realpath("/data/" + name)
    if not under_data(jail) or jail == "/data":
        return jail
    desired_path = os.path.realpath(str(desired or jail))
    if under_data(desired_path) and desired_path != "/data" and desired_path != jail:
        if os.path.isdir(desired_path) and not os.path.exists(jail):
            os.makedirs(os.path.dirname(jail), mode=0o750, exist_ok=True)
            shutil.move(desired_path, jail)
        elif os.path.isdir(desired_path) and os.path.isdir(jail) and not os.listdir(jail):
            for entry in os.listdir(desired_path):
                shutil.move(os.path.join(desired_path, entry), os.path.join(jail, entry))
    os.makedirs(jail, mode=0o755, exist_ok=True)
    os.chown(jail, 0, 0)
    os.chmod(jail, 0o755)
    return jail


class Handler(http.server.BaseHTTPRequestHandler):
    def log_message(self, *args):  # noqa: D401
        return

    def _authorized(self) -> bool:
        return self.headers.get("Authorization") == "Bearer " + TOKEN

    def _json(self, status: int, payload: dict) -> None:
        body = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _read_json(self) -> dict:
        length = int(self.headers.get("Content-Length", 0))
        raw = self.rfile.read(length) if length > 0 else b"{}"
        data = json.loads(raw or b"{}")
        return data if isinstance(data, dict) else {}

    def do_GET(self) -> None:
        parsed = urllib.parse.urlparse(self.path)
        if parsed.path == "/health":
            self.send_response(200)
            self.end_headers()
            self.wfile.write(b"ok")
            return
        if parsed.path == "/files/download":
            if not self._authorized():
                self.send_response(401)
                self.end_headers()
                return
            qs = urllib.parse.parse_qs(parsed.query)
            jail = (qs.get("jail") or [""])[0]
            rel = (qs.get("path") or [""])[0]
            try:
                target = resolve_in_jail(jail, rel, must_exist=True)
                if not os.path.isfile(target):
                    raise ValueError("not a file")
            except ValueError:
                self.send_response(404)
                self.end_headers()
                return
            size = os.path.getsize(target)
            self.send_response(200)
            self.send_header("Content-Type", "application/octet-stream")
            self.send_header("Content-Length", str(size))
            name = os.path.basename(target)
            self.send_header("Content-Disposition", f'attachment; filename="{name}"')
            self.end_headers()
            with open(target, "rb") as handle:
                shutil.copyfileobj(handle, self.wfile)
            return
        self.send_response(404)
        self.end_headers()

    def do_POST(self) -> None:
        parsed = urllib.parse.urlparse(self.path)
        if not self._authorized():
            self.send_response(401 if parsed.path.startswith("/") else 404)
            self.end_headers()
            return

        if parsed.path == "/sync":
            self._handle_sync()
            return
        if parsed.path == "/files/list":
            self._handle_list()
            return
        if parsed.path == "/files/mkdir":
            self._handle_mkdir()
            return
        if parsed.path == "/files/delete":
            self._handle_delete()
            return
        if parsed.path == "/files/upload":
            self._handle_upload()
            return

        self.send_response(404)
        self.end_headers()

    def _handle_sync(self) -> None:
        body = self._read_json()
        os.makedirs("/etc/sftp", exist_ok=True)
        lines: list[str] = []
        user_uids: dict[str, int] = {}
        for user in body.get("users") or []:
            name = str(user.get("username", ""))
            password = str(user.get("password", ""))
            try:
                uid = int(user.get("uid") or 1001)
            except Exception:
                uid = 1001
            if uid < 1001:
                uid = 1001
            if name and password and ":" not in name and ":" not in password:
                ensure_jail(name, user.get("directory") or ("/data/" + name))
                lines.append("%s:%s:%d:%d" % (name, password, uid, uid))
                user_uids[name] = uid
        with open(USERS_CONF, "w", encoding="utf-8") as handle:
            handle.write(("\n".join(lines) + "\n") if lines else "")
        for directory in body.get("directories") or []:
            path = os.path.realpath(str(directory))
            if under_data(path):
                os.makedirs(path, mode=0o755, exist_ok=True)
                for username, uid in user_uids.items():
                    jail = jail_root(username)
                    if path == jail or path.startswith(jail + os.sep):
                        if path == jail:
                            os.chown(path, 0, 0)
                            os.chmod(path, 0o755)
                        else:
                            os.chown(path, uid, uid)
                            os.chmod(path, 0o775)
                        break
        self._json(200, {"ok": True})

    def _handle_list(self) -> None:
        body = self._read_json()
        jail = str(body.get("jail") or "")
        rel = str(body.get("path") or "")
        try:
            target = resolve_in_jail(jail, rel, must_exist=True)
            if not os.path.isdir(target):
                raise ValueError("not a directory")
        except ValueError as exc:
            self._json(400, {"error": str(exc)})
            return
        entries = []
        for name in sorted(os.listdir(target)):
            full = os.path.join(target, name)
            st = os.stat(full)
            entries.append(
                {
                    "name": name,
                    "type": "dir" if os.path.isdir(full) else "file",
                    "size": st.st_size,
                    "mtime": int(st.st_mtime),
                }
            )
        self._json(200, {"path": rel, "entries": entries})

    def _handle_mkdir(self) -> None:
        body = self._read_json()
        jail = str(body.get("jail") or "")
        rel = str(body.get("path") or "")
        try:
            root = jail_root(jail)
            rel_norm = normalize_rel_path(rel)
            target = os.path.realpath(os.path.join(root, rel_norm))
            if not target.startswith(root + os.sep):
                raise ValueError("path escape")
            uids = read_user_uids()
            uid = uids.get(jail, 1001)
            os.makedirs(target, mode=0o775, exist_ok=True)
            chown_tree(target, uid, uid)
        except ValueError as exc:
            self._json(400, {"error": str(exc)})
            return
        except OSError as exc:
            self._json(500, {"error": str(exc)})
            return
        self._json(200, {"ok": True})

    def _handle_delete(self) -> None:
        body = self._read_json()
        jail = str(body.get("jail") or "")
        rel = str(body.get("path") or "")
        try:
            target = resolve_in_jail(jail, rel, must_exist=True)
            root = jail_root(jail)
            if target == root:
                raise ValueError("cannot delete jail root")
        except ValueError as exc:
            self._json(400, {"error": str(exc)})
            return
        try:
            if os.path.isdir(target):
                shutil.rmtree(target)
            else:
                os.remove(target)
        except OSError as exc:
            self._json(500, {"error": str(exc)})
            return
        self._json(200, {"ok": True})

    def _handle_upload(self) -> None:
        jail = str(self.headers.get("X-Jail") or "")
        rel_dir = str(self.headers.get("X-Relative-Dir") or "")
        filename = os.path.basename(str(self.headers.get("X-Filename") or ""))
        if filename in ("", ".", "..") or "/" in filename or "\\" in filename:
            self._json(400, {"error": "invalid filename"})
            return
        try:
            length = int(self.headers.get("Content-Length", 0))
        except ValueError:
            length = 0
        if length <= 0 or length > MAX_UPLOAD_BYTES:
            self._json(400, {"error": "invalid size"})
            return
        try:
            root = jail_root(jail)
            rel_norm = normalize_rel_path(rel_dir)
            directory = os.path.realpath(os.path.join(root, rel_norm)) if rel_norm else root
            if not directory.startswith(root + os.sep) and directory != root:
                raise ValueError("path escape")
            os.makedirs(directory, mode=0o775, exist_ok=True)
            uids = read_user_uids()
            uid = uids.get(jail, 1001)
            chown_tree(directory, uid, uid)
            target = os.path.join(directory, filename)
            if os.path.exists(target):
                raise ValueError("file exists")
            with open(target, "xb") as handle:
                remaining = length
                while remaining > 0:
                    chunk = self.rfile.read(min(65536, remaining))
                    if not chunk:
                        break
                    handle.write(chunk)
                    remaining -= len(chunk)
            os.chown(target, uid, uid)
            os.chmod(target, 0o664)
        except ValueError as exc:
            self._json(400, {"error": str(exc)})
            return
        except OSError as exc:
            self._json(500, {"error": str(exc)})
            return
        self._json(200, {"ok": True, "path": normalize_rel_path(os.path.join(rel_dir, filename))})


if __name__ == "__main__":
    http.server.HTTPServer(("0.0.0.0", 8787), Handler).serve_forever()
