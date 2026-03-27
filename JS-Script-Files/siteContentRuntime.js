(function () {
  function query(root, selector) {
    if (!root || typeof root.querySelector !== "function") {
      return null;
    }
    return root.querySelector(selector);
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function stripHtml(value) {
    var wrapper = document.createElement("div");
    wrapper.innerHTML = String(value || "");
    return (wrapper.textContent || wrapper.innerText || "").replace(/\s+/g, " ").trim();
  }

  function resolveAssetUrl(root, value) {
    var raw = String(value || "").trim();
    if (!raw) {
      return "";
    }
    if (/^(?:https?:)?\/\//i.test(raw) || raw.indexOf("data:") === 0) {
      return raw;
    }

    var doc = root && root.ownerDocument ? root.ownerDocument : document;
    var body = doc.body;
    var assetBase = body && body.dataset ? String(body.dataset.cmsAssetBase || "") : "";
    var relativePath = assetBase + raw.replace(/^\/+/, "");

    try {
      return new URL(relativePath, doc.baseURI).toString();
    } catch (error) {
      return raw;
    }
  }

  function setImage(root, selector, value, altText) {
    var el = query(root, selector);
    if (!el) {
      return;
    }
    var url = resolveAssetUrl(root, value);
    if (!url) {
      return;
    }
    el.src = url;
    if (altText) {
      el.alt = altText;
    }
  }

  function setHtml(root, selector, value) {
    var el = query(root, selector);
    if (!el) {
      return;
    }
    el.innerHTML = String(value || "");
  }

  function unwrapBlockHtml(value) {
    var wrapper = document.createElement("div");
    wrapper.innerHTML = String(value || "").trim();
    if (wrapper.childElementCount > 0) {
      var children = Array.from(wrapper.children);
      var paragraphChildren = children.every(function (child) {
        return child.tagName === "P";
      });
      if (paragraphChildren) {
        return children.map(function (child) {
          return child.innerHTML;
        }).join("<br><br>");
      }
      if (wrapper.childElementCount === 1 && wrapper.firstElementChild && wrapper.firstElementChild.tagName === "P") {
        return wrapper.firstElementChild.innerHTML;
      }
    }
    return wrapper.innerHTML;
  }

  function setInlineHtml(root, selector, value) {
    var el = query(root, selector);
    if (!el) {
      return;
    }
    el.innerHTML = unwrapBlockHtml(value);
  }

  function setText(root, selector, value) {
    var el = query(root, selector);
    if (!el) {
      return;
    }
    el.textContent = String(value == null ? "" : value);
  }

  function telHref(number) {
    var digits = String(number || "").replace(/[^\d+]/g, "");
    return digits ? "tel:" + digits.replace(/^\+/, "") : "#";
  }

  function renderHome(root, payload) {
    setImage(root, '[data-cms-home="banner-image"]', payload.banner_image, "Home Banner");
    setImage(root, '[data-cms-home="about-image"]', payload.about_image, "About Us");
    setHtml(root, '[data-cms-home="about-message"]', payload.about_message_html || "");
    setHtml(root, '[data-cms-home="mission-message"]', payload.mission_message_html || "");
    setHtml(root, '[data-cms-home="vision-message"]', payload.vision_message_html || "");
    setHtml(root, '[data-cms-home="history-message"]', payload.history_message_html || "");

    renderHomeCouncil(root, Array.isArray(payload.council_members) ? payload.council_members : []);
  }

  function renderHomeCouncil(root, members) {
    var councilTrack = query(root, '[data-cms-home="council-list"]');
    if (!councilTrack) {
      return;
    }

    councilTrack.innerHTML = members.map(function (member, index) {
      var imageUrl = resolveAssetUrl(root, member.image || "");
      var figureClass = index === 0 ? "figure council-card captain-card" : "figure council-card";
      return [
        '<figure class="' + figureClass + '">',
        '  <img src="' + escapeHtml(imageUrl) + '" id="carouselImg" class="img-fluid mx-5" alt="' + escapeHtml(member.name || "Council Member") + '">',
        '  <figcaption class="figure-caption">',
        '    <h3 class="mt-3">' + escapeHtml(member.name || "") + "</h3>",
        '    <p>' + escapeHtml(member.position || "") + "</p>",
        "  </figcaption>",
        "</figure>"
      ].join("");
    }).join("");
  }

  function buildCouncilMembersFromGovernment(payload) {
    var members = [];
    if (!payload || typeof payload !== "object") {
      return members;
    }

    var punongName = stripHtml(payload.punong_barangay_name_html || "");
    var punongPosition = stripHtml(payload.punong_barangay_position_html || "Punong Barangay");
    if (punongName) {
      members.push({
        name: punongName,
        position: punongPosition,
        image: payload.punong_barangay_image || ""
      });
    }

    var officials = Array.isArray(payload.officials) ? payload.officials : [];
    officials.forEach(function (official) {
      var name = stripHtml(official.name_html || "");
      if (!name) {
        return;
      }
      members.push({
        name: name,
        position: stripHtml(official.position_html || ""),
        image: official.image || ""
      });
    });

    return members;
  }

  function renderGovernment(root, payload) {
    setImage(root, '[data-cms-government="banner-image"]', payload.banner_image, "Government Banner");
    setInlineHtml(root, '[data-cms-government="banner-title"]', payload.banner_title_html || "");
    setInlineHtml(root, '[data-cms-government="banner-message"]', payload.banner_message_html || "");
    setImage(root, '[data-cms-government="punong-image"]', payload.punong_barangay_image, "Punong Barangay");
    setInlineHtml(root, '[data-cms-government="punong-name"]', payload.punong_barangay_name_html || "");
    setInlineHtml(root, '[data-cms-government="punong-position"]', payload.punong_barangay_position_html || "");
    setHtml(root, '[data-cms-government="punong-message"]', payload.punong_barangay_welcome_message_html || "");

    var officialsContainer = query(root, '[data-cms-government-list="officials"]');
    if (officialsContainer) {
      var officials = Array.isArray(payload.officials) ? payload.officials : [];
      officialsContainer.innerHTML = officials.map(function (official) {
        var imageUrl = resolveAssetUrl(root, official.image || "");
        return [
          '<div class="col">',
          '  <div class="p-3">',
          '    <figure class="figure">',
          '      <img src="' + escapeHtml(imageUrl) + '" id="officialImg" class="img-fluid" alt="' + escapeHtml(stripHtml(official.name_html || "Official")) + '">',
          '      <figcaption class="figure-caption">',
          '        <h3 class="mt-1">' + (official.name_html || "") + "</h3>",
          '        <p>' + (official.position_html || "") + "</p>",
          "      </figcaption>",
          "    </figure>",
          "  </div>",
          "</div>"
        ].join("");
      }).join("");
    }

    var areasContainer = query(root, '[data-cms-government-list="areas"]');
    if (areasContainer) {
      var areas = Array.isArray(payload.areas) ? payload.areas : [];
      areasContainer.innerHTML = areas.map(function (area) {
        return [
          '<div class="col">',
          '  <div class="p-4 deptBox vicinityCard">',
          '    <h3>' + (area.title_html || "") + "</h3>",
          '    <p class="vicinitySub">' + (area.description_html || "") + "</p>",
          "  </div>",
          "</div>"
        ].join("");
      }).join("");
    }
  }

  function renderServices(root, payload) {
    setImage(root, '[data-cms-services="banner-image"]', payload.banner_image, "Services Banner");
    setInlineHtml(root, '[data-cms-services="banner-title"]', payload.banner_title_html || "");
    setInlineHtml(root, '[data-cms-services="banner-message"]', payload.banner_message_html || "");

    var servicesContainer = query(root, '[data-cms-services-list="items"]');
    if (!servicesContainer) {
      return;
    }
    var services = Array.isArray(payload.services) ? payload.services : [];
    servicesContainer.innerHTML = services.map(function (service) {
      return [
        '<div class="col">',
        '  <div class="p-4 servBox">',
        '    <h3>' + (service.title_html || "") + "</h3>",
        '    <div class="cms-runtime-richtext">' + (service.description_html || "") + "</div>",
        '    <a href="../login"><button id="deptBtn" type="button" class="btn">Apply</button></a>',
        "  </div>",
        "</div>"
      ].join("");
    }).join("");
  }

  function renderAnnouncements(root, payload) {
    setImage(root, '[data-cms-announcements="banner-image"]', payload.banner_image, "News Banner");
    setInlineHtml(root, '[data-cms-announcements="banner-title"]', payload.banner_title_html || "");
    setInlineHtml(root, '[data-cms-announcements="banner-message"]', payload.banner_message_html || "");
  }

  function renderFaq(root, payload) {
    setImage(root, '[data-cms-faq="banner-image"]', payload.banner_image, "FAQ Banner");
    setInlineHtml(root, '[data-cms-faq="banner-title"]', payload.banner_title_html || "");
    setInlineHtml(root, '[data-cms-faq="banner-message"]', payload.banner_message_html || "");

    var leftContainer = query(root, '[data-cms-faq-list="left"]');
    var rightContainer = query(root, '[data-cms-faq-list="right"]');
    if (!leftContainer || !rightContainer) {
      return;
    }

    var items = Array.isArray(payload.faq_items) ? payload.faq_items.filter(function (item) {
      return stripHtml(item.question || "") !== "" || stripHtml(item.answer || "") !== "";
    }) : [];

    function buildFaqMarkup(item, index, sideId, isOpen) {
      var collapseId = "cmsFaqPreviewCollapse" + index;
      return [
        '<div class="accordionItemGroup">',
        '  <h2 class="accordionHeader">',
        '    <button class="accordionButton' + (isOpen ? "" : " collapsed") + '" type="button" data-bs-toggle="collapse" data-bs-target="#' + escapeHtml(collapseId) + '" aria-expanded="' + (isOpen ? "true" : "false") + '" aria-controls="' + escapeHtml(collapseId) + '">',
        '      <span class="iconWrapper"><i class="fa-solid fa-caret-down"></i></span>',
        '      <h5 class="questionTitle">' + escapeHtml(item.question || "") + "</h5>",
        "    </button>",
        "  </h2>",
        '  <div id="' + escapeHtml(collapseId) + '" class="accordionCollapse collapse' + (isOpen ? " show" : "") + '" data-bs-parent="#' + escapeHtml(sideId) + '">',
        '    <div class="accordionBody">' + String(item.answer || "<p>No answer available.</p>") + "</div>",
        "  </div>",
        "</div>"
      ].join("");
    }

    if (items.length === 0) {
      leftContainer.innerHTML = [
        '<div class="accordionItemGroup">',
        '  <div class="accordionBody">',
        "    <p>No FAQ items available yet.</p>",
        "  </div>",
        "</div>"
      ].join("");
      rightContainer.innerHTML = "";
      return;
    }

    leftContainer.id = leftContainer.id || "cmsFaqPreviewLeft";
    rightContainer.id = rightContainer.id || "cmsFaqPreviewRight";
    var leftHtml = "";
    var rightHtml = "";
    items.forEach(function (item, index) {
      var sideId = index % 2 === 0 ? leftContainer.id : rightContainer.id;
      var markup = buildFaqMarkup(item, index + 1, sideId, index === 0);
      if (index % 2 === 0) {
        leftHtml += markup;
      } else {
        rightHtml += markup;
      }
    });
    leftContainer.innerHTML = leftHtml;
    rightContainer.innerHTML = rightHtml;
  }

  function renderContact(root, payload) {
    setImage(root, '[data-cms-contact="banner-image"]', payload.banner_image, "Contact Banner");
    setInlineHtml(root, '[data-cms-contact="banner-title"]', payload.banner_title_html || "");
    setInlineHtml(root, '[data-cms-contact="banner-message"]', payload.banner_message_html || "");
    setInlineHtml(root, '[data-cms-contact="emergency-title"]', payload.emergency_title_html || "");
    setInlineHtml(root, '[data-cms-contact="emergency-description"]', payload.emergency_description_html || "");

    var emergencyContainer = query(root, '[data-cms-contact-list="emergency-hotlines"]');
    if (emergencyContainer) {
      var emergencyHotlines = Array.isArray(payload.emergency_hotlines) ? payload.emergency_hotlines : [];
      emergencyContainer.innerHTML = emergencyHotlines.map(function (item) {
        var numberText = stripHtml(item.number_html || "");
        return [
          '<div class="col-6 col-md-2">',
          '  <p class="areaTitle">' + (item.title_html || "") + "</p>",
          '  <a href="' + escapeHtml(telHref(numberText)) + '" class="phoneNumber">' + (item.number_html || "") + "</a>",
          "</div>"
        ].join("");
      }).join("");
    }

    var areaContainer = query(root, '[data-cms-contact-list="area-hotlines"]');
    if (areaContainer) {
      var areaHotlines = Array.isArray(payload.area_hotlines) ? payload.area_hotlines : [];
      areaContainer.innerHTML = areaHotlines.map(function (item) {
        var numberText = stripHtml(item.number_html || "");
        return [
          '<div class="col-12 col-md-6 col-lg-4">',
          '  <div class="contactItem contactCard deptBox">',
          '    <p class="contactAreaName">' + (item.title_html || "") + "</p>",
          '    <p class="contactSubInfo">' + (item.location_html || "") + "</p>",
          '    <a href="' + escapeHtml(telHref(numberText)) + '" class="contactNumber">' + (item.number_html || "") + "</a>",
          "  </div>",
          "</div>"
        ].join("");
      }).join("");
    }
  }

  function renderLogin(root, payload) {
    var loginRoot = query(root, '[data-cms-login-root]');
    if (!loginRoot) {
      return;
    }
    var loginUrl = resolveAssetUrl(root, payload.login_image || "");
    var registerUrl = resolveAssetUrl(root, payload.register_image || "");
    if (loginUrl) {
      loginRoot.style.setProperty("--cms-login-image-url", 'url("' + loginUrl.replace(/"/g, '\\"') + '")');
    }
    if (registerUrl) {
      loginRoot.style.setProperty("--cms-register-image-url", 'url("' + registerUrl.replace(/"/g, '\\"') + '")');
    }
  }

  function renderPage(pageKey, root, payload) {
    if (!payload || typeof payload !== "object") {
      return;
    }

    switch (String(pageKey || "").toLowerCase()) {
      case "home":
        renderHome(root, payload);
        break;
      case "government":
        renderGovernment(root, payload);
        break;
      case "services":
        renderServices(root, payload);
        break;
      case "announcements":
        renderAnnouncements(root, payload);
        break;
      case "faq":
        renderFaq(root, payload);
        break;
      case "contact":
        renderContact(root, payload);
        break;
      case "login":
        renderLogin(root, payload);
        break;
    }
  }

  async function fetchPage(pageKey, endpoint) {
    var separator = endpoint.indexOf("?") === -1 ? "?" : "&";
    var response = await fetch(endpoint + separator + "page=" + encodeURIComponent(pageKey), {
      credentials: "omit"
    });
    var payload = await response.json();
    if (!response.ok || !payload || payload.success !== true) {
      throw new Error((payload && payload.message) || "Unable to load page content.");
    }
    return payload.payload || {};
  }

  async function initPage() {
    if (!document.body || !document.body.dataset) {
      return;
    }

    var pageKey = String(document.body.dataset.cmsPage || "").trim().toLowerCase();
    if (!pageKey) {
      return;
    }

    if (window.CMS_PREVIEW_PAYLOAD && typeof window.CMS_PREVIEW_PAYLOAD === "object") {
      renderPage(pageKey, document, window.CMS_PREVIEW_PAYLOAD);
      return;
    }

    var endpoint = String(document.body.dataset.cmsEndpoint || "").trim();
    if (!endpoint) {
      return;
    }

    try {
      var payload = await fetchPage(pageKey, endpoint);
      renderPage(pageKey, document, payload);
      if (pageKey === "home") {
        try {
          var governmentPayload = await fetchPage("government", endpoint);
          renderHomeCouncil(document, buildCouncilMembersFromGovernment(governmentPayload));
        } catch (error) {
          console.error(error);
        }
      }
    } catch (error) {
      console.error(error);
    }
  }

  window.CmsSiteContentRuntime = {
    escapeHtml: escapeHtml,
    fetchPage: fetchPage,
    renderPage: renderPage,
    resolveAssetUrl: resolveAssetUrl,
    stripHtml: stripHtml
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initPage);
  } else {
    initPage();
  }
})();
