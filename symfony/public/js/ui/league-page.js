import { apiJson, clearFlash, flashError } from "./api.js";

function qs(sel, root = document) {
  return root.querySelector(sel);
}

/**
 * Light polling: refresh member count + fantasy rounds list from API (read-only).
 */
export function initLeaguePage(root) {
  const leagueId = root.dataset.leagueId;
  const membersEl = qs("[data-league-members-count]", root);
  const roundsEl = qs("[data-league-rounds]", root);
  const flash = qs("[data-ui-flash]", root);
  const pollMs = Number(root.dataset.pollMs || "8000");

  async function refresh() {
    const [memRes, roundRes] = await Promise.all([
      apiJson("GET", `/leagues/${leagueId}/members`),
      apiJson("GET", `/leagues/${leagueId}/rounds`),
    ]);
    if (!memRes.ok) {
      flashError(flash, memRes.data?.detail || "Failed to load members");
      return;
    }
    if (!roundRes.ok) {
      flashError(flash, roundRes.data?.detail || "Failed to load rounds");
      return;
    }
    clearFlash(flash);
    membersEl.textContent = String(memRes.data.length);
    roundsEl.replaceChildren();
    for (const r of roundRes.data) {
      const li = document.createElement("li");
      li.innerHTML = `<strong>${escapeHtml(r.name)}</strong> <span class="ui-muted">order ${r.order_index}</span>`;
      roundsEl.appendChild(li);
    }
  }

  function escapeHtml(s) {
    return String(s)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  const timer = window.setInterval(refresh, pollMs);
  refresh().catch((e) => flashError(flash, String(e.message || e)));

  window.addEventListener("beforeunload", () => window.clearInterval(timer), { once: true });
}

const root = document.querySelector("[data-page='league']");
if (root) initLeaguePage(root);
