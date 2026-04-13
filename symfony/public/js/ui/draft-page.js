import { apiJson, clearFlash, flashError, flashOk } from "./api.js";

function qs(sel, root = document) {
  return root.querySelector(sel);
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

async function loadParticipants(tournamentId) {
  const { ok, data } = await apiJson("GET", `/tournaments/${tournamentId}/participants`);
  if (!ok || !Array.isArray(data)) return [];
  return data;
}

async function loadMembers(leagueId) {
  const { ok, data } = await apiJson("GET", `/leagues/${leagueId}/members`);
  if (!ok || !Array.isArray(data)) return [];
  return data;
}

async function loadDraft(leagueId) {
  return apiJson("GET", `/leagues/${leagueId}/draft`);
}

function memberLabel(m) {
  const nick = m.nickname;
  if (nick != null && String(nick).trim() !== "") return String(nick);
  return `Team ${String(m.id).slice(0, 8)}…`;
}

function renderPicks(tbody, picks, memberLabels, participantLabels) {
  tbody.replaceChildren();
  for (const p of picks || []) {
    const tr = document.createElement("tr");
    const mid = p.league_membership_id;
    const pid = p.participant_id;
    const team =
      (mid && memberLabels.get(mid)) || (mid ? `${String(mid).slice(0, 8)}…` : "—");
    const player =
      (pid && participantLabels.get(pid)) || (pid ? `${String(pid).slice(0, 8)}…` : "—");
    tr.innerHTML = `<td>${escapeHtml(String(p.pick_index))}</td><td>${escapeHtml(team)}</td><td>${escapeHtml(player)}</td>`;
    tbody.appendChild(tr);
  }
}

function syncParticipantOptionsDisabled(select, takenSet) {
  const current = select.value;
  for (const o of select.querySelectorAll("option[value]")) {
    const v = o.value;
    if (!v) continue;
    o.disabled = takenSet.has(v);
  }
  if (current && takenSet.has(current)) {
    select.value = "";
  }
}

export function initDraftPage(root) {
  const leagueId = root.dataset.leagueId;
  const tournamentId = root.dataset.tournamentId;
  const membershipId = root.dataset.membershipId || "";
  const flash = qs("[data-ui-flash]", root);
  const select = qs("[data-participant-select]", root);
  const btnPick = qs("[data-action-pick]", root);
  const btnRefresh = qs("[data-action-refresh-draft]", root);
  const pollMs = Number(root.dataset.pollMs || "4000");

  let pollTimer = null;
  /** @type {Array<Record<string, unknown>>} */
  let participants = [];
  let memberLabels = new Map();
  let participantLabels = new Map();
  /** @type {Record<string, unknown> | null} */
  let lastDraft = null;
  let submitting = false;

  function renderDraftState(data) {
    lastDraft = data;

    qs("[data-draft-status]", root).textContent = data.status != null ? String(data.status) : "—";
    qs("[data-draft-pick-index]", root).textContent =
      data.current_pick_index != null ? String(data.current_pick_index) : "—";

    const snakeEl = qs("[data-draft-snake]", root);
    if (snakeEl) {
      snakeEl.textContent = data.snake ? "On" : "Off";
    }

    const picks = Array.isArray(data.picks) ? data.picks : [];
    const countEl = qs("[data-draft-pick-count]", root);
    if (countEl) countEl.textContent = String(picks.length);

    const onClockId = data.on_the_clock_membership_id ? String(data.on_the_clock_membership_id) : "";
    const onClockLabel = onClockId
      ? memberLabels.get(onClockId) || `${onClockId.slice(0, 8)}…`
      : "—";
    qs("[data-draft-on-clock]", root).textContent = onClockLabel;

    const turnBanner = qs("[data-draft-turn-banner]", root);
    if (turnBanner) {
      if (data.status !== "in_progress") {
        turnBanner.hidden = true;
        turnBanner.textContent = "";
        turnBanner.className = "ui-draft-banner";
      } else {
        turnBanner.hidden = false;
        const yourTurn = !!(membershipId && onClockId && membershipId === onClockId);
        turnBanner.className = yourTurn
          ? "ui-draft-banner ui-draft-banner--your-turn"
          : "ui-draft-banner ui-draft-banner--wait";
        turnBanner.textContent = yourTurn
          ? "Your pick — choose an available player and confirm."
          : `On the clock: ${onClockLabel}. You can watch the board update; picks are disabled until it is your turn.`;
      }
    }

    const taken = new Set((data.taken_participant_ids || []).map((id) => String(id)));
    renderPicks(qs("[data-draft-picks]", root), picks, memberLabels, participantLabels);
    syncParticipantOptionsDisabled(select, taken);

    const yourTurn =
      data.status === "in_progress" &&
      !!(membershipId && onClockId && membershipId === onClockId);
    const pid = select.value;
    const canPick =
      data.status === "in_progress" &&
      !!membershipId &&
      !!pid &&
      yourTurn &&
      !taken.has(pid);

    btnPick.disabled = !canPick || submitting;
    const labelEl = qs("[data-pick-label]", root);
    if (labelEl) {
      labelEl.textContent = submitting ? "Submitting…" : "Confirm pick";
    }
  }

  async function refresh() {
    const { ok, status, data } = await loadDraft(leagueId);
    if (!ok) {
      flashError(flash, data?.detail || `Draft load failed (${status})`);
      return;
    }
    clearFlash(flash);
    renderDraftState(data);
  }

  async function bootstrap() {
    const [memberRows, plist] = await Promise.all([loadMembers(leagueId), loadParticipants(tournamentId)]);
    memberLabels = new Map(memberRows.map((m) => [String(m.id), memberLabel(m)]));
    participants = plist;
    participantLabels = new Map(participants.map((p) => [String(p.id), String(p.name || p.display_name || p.id)]));

    select.replaceChildren();
    const ph = document.createElement("option");
    ph.value = "";
    ph.textContent = "Select player…";
    select.appendChild(ph);
    for (const p of participants) {
      const o = document.createElement("option");
      o.value = String(p.id);
      o.textContent = `${p.name || p.display_name || p.id} (${String(p.id).slice(0, 8)}…)`;
      select.appendChild(o);
    }

    await refresh();
    pollTimer = window.setInterval(refresh, pollMs);
  }

  select.addEventListener("change", () => {
    if (lastDraft) renderDraftState(lastDraft);
  });

  if (btnRefresh) {
    btnRefresh.addEventListener("click", () => {
      refresh().catch((e) => flashError(flash, String(e.message || e)));
    });
  }

  btnPick.addEventListener("click", async () => {
    clearFlash(flash);
    const participantId = select.value;
    if (!membershipId || !participantId) {
      flashError(flash, "Add ?membership=… to the URL and choose a player.");
      return;
    }
    submitting = true;
    if (lastDraft) renderDraftState(lastDraft);
    const { ok, status, data } = await apiJson("POST", `/leagues/${leagueId}/draft/picks`, {
      league_membership_id: membershipId,
      participant_id: participantId,
    });
    submitting = false;
    if (!ok) {
      flashError(flash, data?.detail || `Pick failed (${status})`);
      if (lastDraft) renderDraftState(lastDraft);
      await refresh();
      return;
    }
    flashOk(flash, `Pick recorded (#${data.pick_index})`);
    select.value = "";
    await refresh();
  });

  bootstrap().catch((e) => flashError(flash, String(e.message || e)));

  window.addEventListener(
    "beforeunload",
    () => {
      if (pollTimer) window.clearInterval(pollTimer);
    },
    { once: true },
  );
}

const root = document.querySelector("[data-page='draft']");
if (root) initDraftPage(root);
