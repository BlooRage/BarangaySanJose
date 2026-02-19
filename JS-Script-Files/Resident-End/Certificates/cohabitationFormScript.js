document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("cohabitationForm");
    const agree = document.getElementById("cohabitationAgree");
    const submitBtn = document.getElementById("cohabitationSubmit");
    if (!form || !agree || !submitBtn) return;

    const updateSubmitState = () => {
        const isValid = form.checkValidity();
        submitBtn.disabled = !(agree.checked && isValid);
    };

    updateSubmitState();
    form.addEventListener("input", updateSubmitState);
    form.addEventListener("change", updateSubmitState);
});
