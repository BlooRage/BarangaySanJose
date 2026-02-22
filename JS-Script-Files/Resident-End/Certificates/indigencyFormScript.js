(() => {
  const form = document.querySelector("form");
  const agreement = document.getElementById("agreementIndigency");
  const submitBtn = document.querySelector(".submit-btn");
  if (!form || !agreement || !submitBtn) return;

  const updateSubmitState = () => {
    const isValid = form.checkValidity();
    submitBtn.disabled = !(agreement.checked && isValid);
  };

  form.addEventListener("input", updateSubmitState);
  form.addEventListener("change", updateSubmitState);
  agreement.addEventListener("change", updateSubmitState);
  updateSubmitState();
})();
