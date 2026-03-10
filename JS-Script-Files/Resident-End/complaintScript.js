document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form");
    const submitBtn = form?.querySelector(".submit-btn");
    const incidentDateInput = document.getElementById("incidentDate");
    const incidentDateError = document.getElementById("incidentDateError");
    const feedbackData = document.getElementById("complaintFeedbackData");
    let isSubmitting = false;

    if (!form || !submitBtn) {
        return;
    }

    const today = new Date();
    const todayIso = today.toISOString().split("T")[0];
    const todayDisplay = today.toLocaleDateString(undefined, {
        year: "numeric",
        month: "long",
        day: "numeric",
    });

    const validateIncidentDate = () => {
        if (!incidentDateInput) {
            return true;
        }

        const value = String(incidentDateInput.value || "").trim();
        const isFuture = value !== "" && value > todayIso;

        if (isFuture) {
            const msg = `Incorrect Input. Date must be on or before ${todayDisplay}`;
            incidentDateInput.setCustomValidity(msg);
            if (incidentDateError) {
                incidentDateError.textContent = msg;
                incidentDateError.classList.remove("d-none");
            }
            return false;
        }

        incidentDateInput.setCustomValidity("");
        if (incidentDateError) {
            incidentDateError.textContent = "";
            incidentDateError.classList.add("d-none");
        }
        return true;
    };

    const updateState = () => {
        if (isSubmitting) {
            submitBtn.disabled = true;
            return;
        }
        validateIncidentDate();
        submitBtn.disabled = !form.checkValidity();
    };

    form.addEventListener("input", updateState);
    form.addEventListener("change", updateState);
    incidentDateInput?.addEventListener("input", updateState);
    incidentDateInput?.addEventListener("change", updateState);
    incidentDateInput?.addEventListener("keyup", validateIncidentDate);
    incidentDateInput?.addEventListener("blur", validateIncidentDate);
    incidentDateInput?.addEventListener("invalid", validateIncidentDate);
    form.addEventListener("submit", (event) => {
        if (!validateIncidentDate()) {
            event.preventDefault();
            incidentDateInput?.focus();
            updateState();
            return;
        }

        if (!form.checkValidity()) {
            event.preventDefault();
            updateState();
            return;
        }

        isSubmitting = true;
        submitBtn.disabled = true;
    });

    updateState();

    window.addEventListener("pageshow", () => {
        isSubmitting = false;
        updateState();
    });

    const feedbackType = String(feedbackData?.dataset.feedbackType || "").trim();
    const feedbackMessage = String(feedbackData?.dataset.feedbackMessage || "").trim();
    if (feedbackType && feedbackMessage && window.UniversalModal) {
        const title = feedbackType === "success" ? "Complaint Submitted" : "Submission Failed";
        const buttonClass = feedbackType === "success" ? "btn btn-primary" : "btn btn-danger";
        window.UniversalModal.open({
            title,
            message: feedbackMessage,
            buttons: [{ label: "OK", class: buttonClass }],
        });
    }
});
