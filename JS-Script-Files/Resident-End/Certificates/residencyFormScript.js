(() => {
  const form = document.querySelector("form");
  const agreement = document.getElementById("agreementResidency");
  const submitBtn = document.querySelector(".submit-btn");
  if (!form || !agreement || !submitBtn) return;

  const updateSubmitState = () => {
    submitBtn.disabled = !(form.checkValidity() && agreement.checked);
  };

  agreement.addEventListener("change", updateSubmitState);
  form.addEventListener("input", updateSubmitState);
  form.addEventListener("change", updateSubmitState);

  updateSubmitState();
})();
