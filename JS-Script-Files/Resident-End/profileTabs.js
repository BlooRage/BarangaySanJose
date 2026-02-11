document.addEventListener("DOMContentLoaded", () => {
    const tabButtons = document.querySelectorAll('button[data-bs-toggle="tab"]');
    if (!tabButtons.length) return;

    const storageKey = "resident_profile_active_tab";

    const activateTab = (id) => {
        const btn = document.querySelector(`button[data-bs-target="${id}"]`);
        if (!btn) return;
        const tab = bootstrap.Tab.getOrCreateInstance(btn);
        tab.show();
    };

    const hash = window.location.hash;
    if (hash && document.querySelector(`button[data-bs-target="${hash}"]`)) {
        activateTab(hash);
    } else {
        const saved = localStorage.getItem(storageKey);
        if (saved) {
            activateTab(saved);
        }
    }

    tabButtons.forEach((btn) => {
        btn.addEventListener("shown.bs.tab", () => {
            const target = btn.getAttribute("data-bs-target");
            if (target) {
                localStorage.setItem(storageKey, target);
                history.replaceState(null, "", target);
            }
        });
    });
});
