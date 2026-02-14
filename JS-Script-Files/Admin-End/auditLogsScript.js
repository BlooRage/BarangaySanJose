(() => {
  const el = (id) => document.getElementById(id);

  const state = {
    q: "",
    timer: null,
  };

  const safeText = (v) => {
    const s = String(v ?? "").trim();
    return s !== "" ? s : "—";
  };

  const truncate = (s, n = 60) => {
    const t = String(s ?? "");
    if (t.length <= n) return t;
    return t.slice(0, n - 1) + "…";
  };

  const load = async () => {
    const tbody = el("auditTbody");
    if (tbody) {
      tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">Loading...</td></tr>`;
    }

    const params = new URLSearchParams();
    params.set("fetch_audit_logs", "1");
    if (state.q.trim()) params.set("q", state.q.trim());
    params.set("limit", "200");

    const res = await fetch(`../PhpFiles/Admin-End/auditLogs.php?${params.toString()}`, {
      headers: { Accept: "application/json" },
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data || !data.success) {
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Failed to load audit logs.</td></tr>`;
      }
      return;
    }

    const rows = Array.isArray(data.data) ? data.data : [];
    if (!rows.length) {
      if (tbody) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-4">No records found.</td></tr>`;
      }
      return;
    }

    if (!tbody) return;
    tbody.innerHTML = "";
    rows.forEach((r) => {
      const tr = document.createElement("tr");

      const ts = document.createElement("td");
      ts.style.whiteSpace = "nowrap";
      ts.innerText = safeText(r.action_timestamp);

      const user = document.createElement("td");
      user.innerText = safeText(r.user_id);

      const role = document.createElement("td");
      role.innerText = safeText(r.role_access);

      const mod = document.createElement("td");
      mod.innerText = safeText(r.module_affected);

      const tgt = document.createElement("td");
      tgt.innerText = `${safeText(r.target_type)} #${safeText(r.target_id)}`;

      const act = document.createElement("td");
      act.innerText = safeText(r.action_type);

      const field = document.createElement("td");
      field.innerText = safeText(r.field_changed);

      const oldV = document.createElement("td");
      oldV.title = String(r.old_value ?? "");
      oldV.innerText = truncate(safeText(r.old_value), 60);

      const newV = document.createElement("td");
      newV.title = String(r.new_value ?? "");
      newV.innerText = truncate(safeText(r.new_value), 60);

      const rem = document.createElement("td");
      rem.title = String(r.remarks ?? "");
      rem.innerText = truncate(safeText(r.remarks), 60);

      tr.appendChild(ts);
      tr.appendChild(user);
      tr.appendChild(role);
      tr.appendChild(mod);
      tr.appendChild(tgt);
      tr.appendChild(act);
      tr.appendChild(field);
      tr.appendChild(oldV);
      tr.appendChild(newV);
      tr.appendChild(rem);

      tbody.appendChild(tr);
    });
  };

  const wire = () => {
    const search = el("auditSearch");
    const refresh = el("btnAuditRefresh");

    if (search) {
      search.addEventListener("input", () => {
        state.q = search.value || "";
        if (state.timer) window.clearTimeout(state.timer);
        state.timer = window.setTimeout(load, 250);
      });
    }
    if (refresh) refresh.addEventListener("click", load);
  };

  document.addEventListener("DOMContentLoaded", () => {
    wire();
    load();
  });
})();

