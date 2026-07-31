(function () {
  var script = document.currentScript;
  var endpoint = script && script.dataset.endpoint ? script.dataset.endpoint : "../PhpFiles/GET/getWebsitePreferences.php";

  function apply(settings) {
    var root = document.documentElement;
    root.lang = settings.language === "fil" ? "fil" : "en";
    root.style.fontSize = String(settings.font_scale || "100") + "%";
    root.classList.toggle("site-high-contrast", Boolean(settings.high_contrast));
    root.classList.toggle("site-reduced-motion", Boolean(settings.reduced_motion));

    if (!document.getElementById("website-preference-styles")) {
      var style = document.createElement("style");
      style.id = "website-preference-styles";
      style.textContent = ".site-high-contrast{filter:contrast(1.2)}.site-high-contrast body{background:#fff;color:#111}.site-high-contrast a{text-decoration:underline}.site-reduced-motion *, .site-reduced-motion *::before, .site-reduced-motion *::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}";
      document.head.appendChild(style);
    }
  }

  fetch(endpoint, { credentials: "same-origin", headers: { Accept: "application/json" } })
    .then(function (response) { return response.ok ? response.json() : null; })
    .then(function (data) { if (data && data.success) apply(data); })
    .catch(function () {});
})();
