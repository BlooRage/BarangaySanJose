document.addEventListener("DOMContentLoaded", () => {
    const activateTab = (btn) => {
        if (!btn) return;
        const tab = bootstrap.Tab.getOrCreateInstance(btn);
        tab.show();
    };

    // Top-level tabs (Profile / Household)
    const topButtons = document.querySelectorAll('.profile-tabs button[data-bs-toggle="tab"]');
    const topKey = "resident_profile_active_tab";

    const topHash = window.location.hash;
    if (topHash && document.querySelector(`.profile-tabs button[data-bs-target="${topHash}"]`)) {
        activateTab(document.querySelector(`.profile-tabs button[data-bs-target="${topHash}"]`));
    } else {
        const saved = localStorage.getItem(topKey);
        if (saved && document.querySelector(`.profile-tabs button[data-bs-target="${saved}"]`)) {
            activateTab(document.querySelector(`.profile-tabs button[data-bs-target="${saved}"]`));
        }
    }

    topButtons.forEach((btn) => {
        btn.addEventListener("shown.bs.tab", () => {
            const target = btn.getAttribute("data-bs-target");
            if (target) {
                localStorage.setItem(topKey, target);
                history.replaceState(null, "", target);
            }
        });
    });
});
