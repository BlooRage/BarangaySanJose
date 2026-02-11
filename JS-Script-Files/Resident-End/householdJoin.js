document.addEventListener("DOMContentLoaded", () => {
    const joinBtn = document.getElementById("btnJoinHousehold");
    if (!joinBtn) return;

    joinBtn.addEventListener("click", async () => {
        const codeInput = document.getElementById("householdJoinCode");
        const result = document.getElementById("householdJoinResult");
        const code = codeInput ? codeInput.value.trim() : "";
        if (result) {
            result.className = "small mt-2 text-muted";
            result.textContent = "Joining household...";
        }
        joinBtn.disabled = true;
        try {
            const res = await fetch("../PhpFiles/SMSHandlers/household_invite_join.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ code }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || "Failed to join household.");
            }
            if (result) {
                result.className = "small mt-2 text-success";
                result.textContent = data.message || "Joined household successfully.";
            }
            window.dispatchEvent(new Event("household:updated"));
        } catch (err) {
            if (result) {
                result.className = "small mt-2 text-danger";
                result.textContent = err?.message || "Failed to join household.";
            }
        } finally {
            joinBtn.disabled = false;
        }
    });
});
