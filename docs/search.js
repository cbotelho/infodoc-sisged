(() => {
  const submenuToggles = document.querySelectorAll("[data-submenu-toggle]");
  const input = document.getElementById("docs-search-input");
  const results = document.getElementById("docs-search-results");
  const index = Array.isArray(window.DOCS_SEARCH_INDEX) ? window.DOCS_SEARCH_INDEX : [];

  submenuToggles.forEach((toggle) => {
    const parent = toggle.parentElement;
    const targetId = toggle.getAttribute("aria-controls");
    const submenu = targetId ? document.getElementById(targetId) : null;
    const caret = toggle.querySelector(".caret-icon");

    if (!parent || !submenu || !caret) {
      return;
    }

    const isOpen = parent.classList.contains("is-open");
    submenu.setAttribute("aria-hidden", isOpen ? "false" : "true");
    toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");

    toggle.addEventListener("click", (event) => {
      event.preventDefault();
      const open = parent.classList.toggle("is-open");
      submenu.setAttribute("aria-hidden", open ? "false" : "true");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      caret.classList.toggle("fa-caret-up", open);
      caret.classList.toggle("fa-caret-down", !open);
    });
  });

  if (!input || !results || index.length === 0) {
    return;
  }

  const normalize = (value) =>
    value
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();

  const score = (entry, terms) => {
    const title = normalize(entry.title || "");
    const headings = normalize(entry.headings || "");
    const content = normalize(entry.content || "");
    let points = 0;

    for (const term of terms) {
      if (!term) {
        continue;
      }
      if (title.includes(term)) {
        points += 10;
      }
      if (headings.includes(term)) {
        points += 6;
      }
      if (content.includes(term)) {
        points += 2;
      }
    }

    return points;
  };

  const renderEmpty = (message) => {
    results.hidden = false;
    results.innerHTML = `<div class="search-empty">${message}</div>`;
  };

  const renderMatches = (matches) => {
    if (matches.length === 0) {
      renderEmpty("Nenhum resultado encontrado para a busca informada.");
      return;
    }

    results.hidden = false;
    results.innerHTML = matches
      .slice(0, 8)
      .map(
        (entry) => `
          <a class="search-result" href="${entry.url}">
            <span class="search-result-title">${entry.title}</span>
            <span class="search-result-meta">${entry.source}</span>
            <span class="search-result-excerpt">${entry.excerpt}</span>
          </a>
        `
      )
      .join("");
  };

  input.addEventListener("input", () => {
    const query = normalize(input.value);
    if (!query) {
      results.hidden = true;
      results.innerHTML = "";
      return;
    }

    const terms = query.split(/\s+/).filter(Boolean);
    const matches = index
      .map((entry) => ({ ...entry, _score: score(entry, terms) }))
      .filter((entry) => entry._score > 0)
      .sort((left, right) => right._score - left._score || left.title.localeCompare(right.title, "pt-BR"));

    renderMatches(matches);
  });

  input.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      input.value = "";
      results.hidden = true;
      results.innerHTML = "";
      input.blur();
    }
  });
})();