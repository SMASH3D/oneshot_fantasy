/**
 * Thin fetch wrapper for /api/v1 JSON endpoints (same-origin).
 */
const API_PREFIX = "/api/v1";

export async function apiJson(method, path, body = undefined) {
  const opts = {
    method,
    headers: {
      Accept: "application/json",
    },
  };
  if (body !== undefined) {
    opts.headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(`${API_PREFIX}${path}`, opts);
  const text = await res.text();
  let data = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = { detail: text, code: "invalid_json" };
    }
  }
  return { ok: res.ok, status: res.status, data };
}

export function flashError(el, message) {
  if (!el) return;
  el.hidden = false;
  el.className = "ui-flash ui-flash--error";
  el.textContent = message;
}

export function flashOk(el, message) {
  if (!el) return;
  el.hidden = false;
  el.className = "ui-flash ui-flash--ok";
  el.textContent = message;
}

export function clearFlash(el) {
  if (!el) return;
  el.hidden = true;
  el.textContent = "";
}
