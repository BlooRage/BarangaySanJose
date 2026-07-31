(function () {
  var runtimeScriptUrl = document.currentScript && document.currentScript.src ? document.currentScript.src : "";
  var CACHE_PREFIX = "public-page-prefetch:";
  var DEFAULT_MAX_AGE_MS = 2 * 60 * 1000;
  var routePreloadInFlight = Object.create(null);

  function getStorage() {
    try {
      return window.sessionStorage;
    } catch (error) {
      return null;
    }
  }

  function toAbsoluteUrl(value) {
    return new URL(String(value || ""), document.baseURI).toString();
  }

  function buildCacheKey(url) {
    return CACHE_PREFIX + toAbsoluteUrl(url);
  }

  function readCacheRecord(url) {
    var storage = getStorage();
    if (!storage) {
      return null;
    }

    try {
      var raw = storage.getItem(buildCacheKey(url));
      if (!raw) {
        return null;
      }

      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== "object" || !("data" in parsed)) {
        return null;
      }

      return parsed;
    } catch (error) {
      return null;
    }
  }

  function readCachedJson(url, maxAgeMs) {
    var record = readCacheRecord(url);
    if (!record) {
      return null;
    }

    var age = Date.now() - Number(record.timestamp || 0);
    if (typeof maxAgeMs === "number" && age > maxAgeMs) {
      return null;
    }

    return record.data;
  }

  function writeCachedJson(url, data) {
    var storage = getStorage();
    if (!storage) {
      return;
    }

    try {
      storage.setItem(buildCacheKey(url), JSON.stringify({
        timestamp: Date.now(),
        data: data
      }));
    } catch (error) {}
  }

  function normalizeFetchOptions(options) {
    var normalized = Object.assign({}, options || {});
    delete normalized.maxAgeMs;
    return normalized;
  }

  async function fetchJson(url, options) {
    var absoluteUrl = toAbsoluteUrl(url);
    var maxAgeMs = options && typeof options.maxAgeMs === "number"
      ? options.maxAgeMs
      : DEFAULT_MAX_AGE_MS;
    var freshPayload = readCachedJson(absoluteUrl, maxAgeMs);
    var staleRecord = readCacheRecord(absoluteUrl);

    if (freshPayload !== null) {
      return freshPayload;
    }

    try {
      var response = await fetch(absoluteUrl, normalizeFetchOptions(options));
      var data = await response.json();

      if (!response.ok) {
        if (staleRecord && "data" in staleRecord) {
          return staleRecord.data;
        }
        throw new Error((data && data.message) || "Unable to load content.");
      }

      writeCachedJson(absoluteUrl, data);
      return data;
    } catch (error) {
      if (staleRecord && "data" in staleRecord) {
        return staleRecord.data;
      }
      throw error;
    }
  }

  function getAppRootUrl() {
    var brandLink = document.getElementById("navbarBrand");
    if (brandLink && brandLink.href) {
      return new URL(brandLink.href, document.baseURI).toString();
    }

    if (document.body && document.body.dataset && document.body.dataset.cmsEndpoint) {
      try {
        return new URL("../../", new URL(document.body.dataset.cmsEndpoint, document.baseURI)).toString();
      } catch (error) {}
    }

    return new URL("./", document.baseURI).toString();
  }

  function getRouteKeyFromUrl(url) {
    try {
      var pathname = new URL(url, document.baseURI).pathname.replace(/\/+$/, "").toLowerCase();
      if (pathname.endsWith("/news")) {
        return "news";
      }
      if (pathname.endsWith("/faq")) {
        return "faq";
      }
    } catch (error) {}

    return "";
  }

  function buildRouteRequests(routeKey) {
    var appRootUrl = getAppRootUrl();

    if (routeKey === "news") {
      return [
        {
          url: new URL("PhpFiles/GET/getSiteContent.php?page=announcements", appRootUrl).toString(),
          options: { credentials: "omit" }
        },
        {
          url: new URL("PhpFiles/GET/getPublicNews.php", appRootUrl).toString(),
          options: { headers: { Accept: "application/json" } }
        },
        {
          url: new URL("PhpFiles/GET/getPublicAnnouncements.php", appRootUrl).toString(),
          options: { headers: { Accept: "application/json" } }
        }
      ];
    }

    if (routeKey === "faq") {
      return [
        {
          url: new URL("PhpFiles/GET/getSiteContent.php?page=faq", appRootUrl).toString(),
          options: { credentials: "omit" }
        },
        {
          url: new URL("PhpFiles/GET/getFaqItems.php", appRootUrl).toString(),
          options: { credentials: "omit" }
        }
      ];
    }

    return [];
  }

  function preloadRoute(routeKey) {
    if (routePreloadInFlight[routeKey]) {
      return routePreloadInFlight[routeKey];
    }

    var requests = buildRouteRequests(routeKey);
    if (!requests.length) {
      return Promise.resolve([]);
    }

    routePreloadInFlight[routeKey] = Promise.allSettled(requests.map(function (request) {
      return fetchJson(request.url, request.options || {});
    })).finally(function () {
      delete routePreloadInFlight[routeKey];
    });

    return routePreloadInFlight[routeKey];
  }

  function scheduleBackgroundPrefetch(routeKeys) {
    var uniqueRouteKeys = [];

    routeKeys.forEach(function (routeKey) {
      if (!routeKey || uniqueRouteKeys.indexOf(routeKey) !== -1) {
        return;
      }
      uniqueRouteKeys.push(routeKey);
    });

    if (!uniqueRouteKeys.length) {
      return;
    }

    var warmRoutes = function () {
      uniqueRouteKeys.forEach(function (routeKey) {
        preloadRoute(routeKey).catch(function () {});
      });
    };
    warmRoutes();
  }

  function attachNavPrefetch() {
    var navLinks = Array.prototype.slice.call(document.querySelectorAll("#navbarLinks a.nav-link[href]"));
    var currentRouteKey = getRouteKeyFromUrl(window.location.href);
    var backgroundRoutes = [];

    navLinks.forEach(function (link) {
      var routeKey = getRouteKeyFromUrl(link.href);
      if (!routeKey) {
        return;
      }

      if (routeKey !== currentRouteKey) {
        backgroundRoutes.push(routeKey);
      }

      var warmRoute = function () {
        preloadRoute(routeKey).catch(function () {});
      };

      link.addEventListener("mouseenter", warmRoute, { passive: true });
      link.addEventListener("touchstart", warmRoute, { passive: true });
      link.addEventListener("focus", warmRoute, { passive: true });
    });

    scheduleBackgroundPrefetch(backgroundRoutes);
  }

  window.PublicPagePrefetch = {
    fetchJson: fetchJson,
    getRouteKeyFromUrl: getRouteKeyFromUrl,
    preloadRoute: preloadRoute,
    readCachedJson: readCachedJson,
    writeCachedJson: writeCachedJson
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", attachNavPrefetch, { once: true });
  } else {
    attachNavPrefetch();
  }

  if (runtimeScriptUrl) {
    var preferenceScript = document.createElement("script");
    preferenceScript.src = new URL("websitePreferences.js", runtimeScriptUrl).toString();
    preferenceScript.dataset.endpoint = new URL("../PhpFiles/GET/getWebsitePreferences.php", runtimeScriptUrl).toString();
    document.head.appendChild(preferenceScript);
  }
})();
