document.addEventListener("DOMContentLoaded", () => {
  const postJson = async (body) => {
    const res = await fetch("../PhpFiles/Admin-End/admin_profile_update.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body || {}),
    });
    const text = await res.text();
    let data = null;
    try { data = JSON.parse(text); } catch { data = { success: false, message: "Unexpected server response." }; }
    return { res, data };
  };

  const setMessage = (el, msg, isError = false) => {
    if (!el) return;
    el.textContent = msg || "";
    el.className = isError ? "small mb-2 text-danger" : "small mb-2 text-success";
  };

  const bindSave = (btnId, resultId, buildPayload) => {
    const btn = document.getElementById(btnId);
    const result = document.getElementById(resultId);
    if (!btn) return;

    btn.addEventListener("click", async () => {
      setMessage(result, "");
      btn.disabled = true;
      try {
        const payload = buildPayload();
        const { res, data } = await postJson(payload);
        if (!res.ok || !data?.success) {
          throw new Error(data?.message || "Unable to save changes.");
        }
        setMessage(result, data.message || "Saved.");
        window.UniversalModal?.open?.({
          title: "Success",
          message: data.message || "Saved.",
          buttons: [{ label: "OK", class: "btn btn-success", onClick: () => {} }],
        });
      } catch (e) {
        const msg = e?.message || "Unable to save changes.";
        setMessage(result, msg, true);
      } finally {
        btn.disabled = false;
      }
    });
  };

  bindSave("btnSavePersonal", "personalSaveResult", () => ({
    section: "personal",
    lastname: (document.getElementById("adminLastName")?.value || "").trim(),
    firstname: (document.getElementById("adminFirstName")?.value || "").trim(),
    middlename: (document.getElementById("adminMiddleName")?.value || "").trim(),
    suffix: (document.getElementById("adminSuffix")?.value || "").trim(),
    birthdate: (document.getElementById("adminBirthdate")?.value || "").trim(),
    sex: (document.getElementById("adminSex")?.value || "").trim(),
    civil_status: (document.getElementById("adminCivilStatus")?.value || "").trim(),
    department: (document.getElementById("adminDepartment")?.value || "").trim(),
    position_access: (document.getElementById("adminPositionAccess")?.value || "").trim(),
  }));

  bindSave("btnSaveEmergency", "emergencySaveResult", () => ({
    section: "emergency",
    emergency_contact_name: (document.getElementById("adminEmergencyName")?.value || "").trim(),
    emergency_contact_relationship: (document.getElementById("adminEmergencyRelationship")?.value || "").trim(),
    emergency_contact_phone: (document.getElementById("adminEmergencyPhone")?.value || "").trim(),
    emergency_contact_address: (document.getElementById("adminEmergencyAddress")?.value || "").trim(),
  }));

  bindSave("btnSaveAddress", "addressSaveResult", () => ({
    section: "address",
    address_mode: (document.getElementById("adminAddressMode")?.value || "street").trim(),
    house_number: (document.getElementById("adminHouseNumber")?.value || "").trim(),
    street_name: (document.getElementById("adminStreetName")?.value || "").trim(),
    block_number: (document.getElementById("adminBlockNumber")?.value || "").trim(),
    lot_number: (document.getElementById("adminLotNumber")?.value || "").trim(),
    barangay: (document.getElementById("adminBarangay")?.value || "").trim(),
    municipality_city: (document.getElementById("adminMunicipalityCity")?.value || "").trim(),
    province: (document.getElementById("adminProvince")?.value || "").trim(),
  }));
});
