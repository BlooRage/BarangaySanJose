(() => {
  const form = document.querySelector("form");
  const arrangement = document.getElementById("residencyArrangement");
  const docType = document.getElementById("residencyDocumentType");
  const supportingInput = document.getElementById("supportingFile");
  const supportingList = document.getElementById("supportingSelectedFile");
  const supportingDropzone = document.getElementById("supportingDropzone");
  const agreement = document.getElementById("agreementResidency");
  const submitBtn = document.querySelector(".submit-btn");
  if (!form || !arrangement || !docType || !supportingInput || !supportingList || !supportingDropzone || !agreement || !submitBtn) return;

  const options = {
    renting: [
      "Lease contract",
      "Written confirmation from homeowner",
      "Homeowner valid ID"
    ],
    relatives: [
      "Authorization from homeowner",
      "Homeowner valid ID"
    ]
  };

  const updateSubmitState = () => {
    const isValid = form.checkValidity();
    submitBtn.disabled = !(isValid && agreement.checked);
  };

  const renderDocOptions = () => {
    const arrangementValue = arrangement.value;
    docType.innerHTML = "";
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = "Select supporting document type";
    placeholder.selected = true;
    docType.appendChild(placeholder);

    if (!arrangementValue) {
      docType.disabled = true;
      updateSubmitState();
      return;
    }

    docType.disabled = false;
    const key = arrangementValue === "relatives" ? "relatives" : "renting";
    options[key].forEach((label) => {
      const option = document.createElement("option");
      option.value = label;
      option.textContent = label;
      docType.appendChild(option);
    });
    updateSubmitState();
  };

  const renderFile = (input, target) => {
    const names = Array.from(input.files || []).map((f) => f.name);
    target.textContent = names.length ? `Selected: ${names.join(", ")}` : "No file selected";
    updateSubmitState();
  };

  arrangement.addEventListener("change", renderDocOptions);
  supportingInput.addEventListener("change", () => renderFile(supportingInput, supportingList));
  agreement.addEventListener("change", updateSubmitState);
  form.addEventListener("input", updateSubmitState);
  form.addEventListener("change", updateSubmitState);

  const bindDropzone = (dropzone, input, preview) => {
    ["dragenter", "dragover"].forEach((eventName) => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.classList.add("is-dragging");
      });
    });

    ["dragleave", "drop"].forEach((eventName) => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        dropzone.classList.remove("is-dragging");
      });
    });

    dropzone.addEventListener("drop", (e) => {
      const dt = e.dataTransfer;
      if (dt && dt.files && dt.files.length) {
        input.files = dt.files;
        renderFile(input, preview);
      }
    });
  };

  bindDropzone(supportingDropzone, supportingInput, supportingList);

  renderDocOptions();
  renderFile(supportingInput, supportingList);
  updateSubmitState();
})();
