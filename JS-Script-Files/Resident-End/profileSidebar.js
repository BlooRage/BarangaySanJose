document.addEventListener("DOMContentLoaded", () => {
    const burgerBtn = document.getElementById("btn-burger");
    const sidebar = document.getElementById("div-sidebarWrapper");
    if (!burgerBtn || !sidebar) return;

    burgerBtn.addEventListener("click", () => {
        sidebar.classList.toggle("show");
    });
});
