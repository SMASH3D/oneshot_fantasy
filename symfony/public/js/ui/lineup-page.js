import { apiJson, clearFlash, flashError, flashOk } from "./api.js";

function qs(sel, root = document) {
  return root.querySelector(sel);
}

async function loadParticipants(tournamentId) {
  const { ok, data } = await apiJson("GET", `/tournaments/${tournamentId}/participants`);
  if (!ok || !Array.isArray(data)) return [];
  return data;
}

function participantOptionsHtml(participants) {
  return participants
    .map(
      (p) =>
        `<option value="${p.id}">${escapeHtml(p.name || p.display_name || "")} (${p.id.slice(0, 8)}…)</option>`,
    )
    .join("");
}

function escapeHtml(s) {
  return String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

export function initLineupPage(root) {
  const leagueId = root.dataset.leagueId;
  const roundId = root.dataset.fantasyRoundId;
  const membershipId = root.dataset.membershipId;
  const tournamentId = root.dataset.tournamentId;
  const flash = qs("[data-ui-flash]", root);
  const slotsEl = qs("[data-lineup-slots]", root);
  const btnSave = qs("[data-action-save]", root);
  const btnSubmit = qs("[data-action-submit]", root);
  const statusEl = qs("[data-lineup-status]", root);
  const pollMs = Number(root.dataset.pollMs || "5000");

  let participants = [];
  let pollTimer = null;

  function lineupPath() {
    return `/leagues/${leagueId}/rounds/${roundId}/lineups/${membershipId}`;
  }

  async function fetchLineup() {
    return apiJson("GET", lineupPath());
  }

  function renderSlots(lineup) {
    const slots = lineup.slots || [];
    statusEl.textContent = lineup.status || "—";
    const locked = lineup.status === "locked" || lineup.status === "void";
    btnSave.disabled = locked;
    btnSubmit.disabled = locked || lineup.status !== "draft";

    slotsEl.replaceChildren();
    for (const slot of slots) {
      const row = document.createElement("div");
      row.className = "ui-form-row";
      row.dataset.orderIndex = String(slot.order_index);
      row.innerHTML = `
        <label>${escapeHtml(slot.slot_role)} <span class="ui-muted">#${slot.order_index}</span></label>
        <select class="ui-input" data-slot-role="${escapeHtml(slot.slot_role)}" ${locked ? "disabled" : ""}>
          <option value="">— empty —</option>
          ${participantOptionsHtml(participants)}
        </select>
      `;
      const sel = row.querySelector("select");
      if (slot.participant_id) sel.value = slot.participant_id;
      slotsEl.appendChild(row);
    }
  }

  async function refresh() {
    const { ok, status, data } = await fetchLineup();
    if (!ok) {
      flashError(flash, data?.detail || `Lineup load failed (${status})`);
      return;
    }
    clearFlash(flash);
    renderSlots(data);
  }

  async function saveLineup() {
    clearFlash(flash);
    const slots = [];
    for (const row of slotsEl.querySelectorAll(".ui-form-row")) {
      const select = row.querySelector("select");
      const role = select.dataset.slotRole;
      const orderIndex = Number(row.dataset.orderIndex);
      slots.push({
        order_index: orderIndex,
        slot_role: role,
        participant_id: select.value || null,
      });
    }
    const { ok, status, data } = await apiJson("PUT", lineupPath(), { slots });
    if (!ok) {
      flashError(flash, data?.detail || `Save failed (${status})`);
      return;
    }
    flashOk(flash, "Lineup saved.");
    renderSlots(data);
  }

  async function submitLineup() {
    clearFlash(flash);
    await saveLineup();
    const { ok, status, data } = await apiJson("POST", `${lineupPath()}/submit`);
    if (!ok) {
      flashError(flash, data?.detail || `Submit failed (${status})`);
      await refresh();
      return;
    }
    flashOk(flash, `Submitted: ${data.status} at ${data.submitted_at || ""}`);
    await refresh();
  }

  btnSave.addEventListener("click", () => saveLineup().catch((e) => flashError(flash, String(e))));
  btnSubmit.addEventListener("click", () => submitLineup().catch((e) => flashError(flash, String(e))));

  async function bootstrap() {
    participants = await loadParticipants(tournamentId);
    await refresh();
    pollTimer = window.setInterval(refresh, pollMs);
  }

  bootstrap().catch((e) => flashError(flash, String(e.message || e)));

  window.addEventListener(
    "beforeunload",
    () => {
      if (pollTimer) window.clearInterval(pollTimer);
    },
    { once: true },
  );
}

const root = document.querySelector("[data-page='lineup']");
if (root) initLineupPage(root);
