document.addEventListener("DOMContentLoaded", () => {
    const toggleOccupation = () => {
        const employmentStatus = document.getElementById("employmentStatus");
        const occupationRow = document.getElementById("occupationRow");
        if (!employmentStatus || !occupationRow) return;
        if (employmentStatus.value === "Employed") {
            occupationRow.style.display = "flex";
        } else {
            occupationRow.style.display = "none";
        }
    };

    window.toggleOccupation = toggleOccupation;
    toggleOccupation();
});
