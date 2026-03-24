from __future__ import annotations

import html
import json
import re
import unicodedata
from pathlib import Path
from typing import Iterable


DOCS_DIR = Path(__file__).resolve().parent
README_PATH = DOCS_DIR / "README.md"
CSS_PATH = DOCS_DIR / "docs.css"
FAVICON_PATH = DOCS_DIR / "favicon.svg"
PLACEHOLDER_IMAGE = "doc-image-placeholder.svg"
SEARCH_SCRIPT_PATH = DOCS_DIR / "search.js"


def slugify(text: str) -> str:
    normalized = unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode("ascii")
    slug = re.sub(r"[^a-z0-9]+", "-", normalized.lower()).strip("-")
    return slug or "secao"


def get_title(markdown_text: str, fallback: str) -> str:
    for line in markdown_text.splitlines():
        if line.startswith("# "):
            return line[2:].strip()
    return fallback


def parse_nav_order() -> list[str]:
    readme_text = README_PATH.read_text(encoding="utf-8")
    ordered_files: list[str] = [README_PATH.name]
    for match in re.finditer(r"\[[^\]]+\]\(([^)]+\.md)\)", readme_text):
        file_name = Path(match.group(1)).name
        if file_name not in ordered_files and (DOCS_DIR / file_name).exists():
            ordered_files.append(file_name)

    for file_path in sorted(DOCS_DIR.glob("*.md")):
        if file_path.name not in ordered_files:
            ordered_files.append(file_path.name)

    return ordered_files


def convert_inline(text: str) -> str:
    placeholders: dict[str, str] = {}

    def stash(value: str) -> str:
        key = f"__INLINE_{len(placeholders)}__"
        placeholders[key] = value
        return key

    def image_replacer(match: re.Match[str]) -> str:
        alt_text = match.group(1).strip() or "Imagem da documentacao"
        alt = html.escape(alt_text, quote=True)
        src = html.escape(rewrite_link(match.group(2)), quote=True)
        return stash(
            "".join(
                [
                    '<figure class="doc-image">',
                    f'<img src="{src}" alt="{alt}" loading="lazy" onerror="this.onerror=null;this.src=\'{PLACEHOLDER_IMAGE}\';this.parentElement.classList.add(\'is-placeholder\')">',
                    f"<figcaption>{html.escape(alt_text)}</figcaption>",
                    "</figure>",
                ]
            )
        )

    def link_replacer(match: re.Match[str]) -> str:
        label = html.escape(match.group(1))
        href = rewrite_link(match.group(2))
        target = ' target="_blank" rel="noreferrer"' if is_external(href) else ""
        return stash(f'<a href="{html.escape(href, quote=True)}"{target}>{label}</a>')

    def code_replacer(match: re.Match[str]) -> str:
        return stash(f"<code>{html.escape(match.group(1))}</code>")

    escaped = html.escape(text)
    escaped = re.sub(r"!\[([^\]]*)\]\(([^)]+)\)", image_replacer, escaped)
    escaped = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", link_replacer, escaped)
    escaped = re.sub(r"`([^`]+)`", code_replacer, escaped)
    escaped = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", escaped)
    escaped = re.sub(r"\*([^*]+)\*", r"<em>\1</em>", escaped)

    for key, value in placeholders.items():
        escaped = escaped.replace(key, value)

    return escaped


def rewrite_link(target: str) -> str:
    target = target.strip()
    if target.endswith(".md"):
        return Path(target).with_suffix(".html").as_posix()
    if ".md#" in target:
        base, anchor = target.split("#", 1)
        return f"{Path(base).with_suffix('.html').as_posix()}#{anchor}"
    return target


def is_external(target: str) -> bool:
    return target.startswith("http://") or target.startswith("https://")


def strip_markdown(markdown_text: str) -> str:
    text = re.sub(r"```.*?```", " ", markdown_text, flags=re.DOTALL)
    text = re.sub(r"!\[([^\]]*)\]\(([^)]+)\)", r" \1 ", text)
    text = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r" \1 ", text)
    text = re.sub(r"[`>#*_\-]", " ", text)
    text = re.sub(r"\s+", " ", text)
    return text.strip()


def build_search_index(navigation: Iterable[dict[str, str]]) -> str:
    search_items: list[dict[str, str]] = []
    for item in navigation:
        source_path = DOCS_DIR / item["source"]
        markdown_text = source_path.read_text(encoding="utf-8")
        headings = [title for title, _, level in render_markdown(markdown_text)[1] if level in {2, 3}]
        content = strip_markdown(markdown_text)
        excerpt = content[:240].rsplit(" ", 1)[0].strip() if len(content) > 240 else content
        search_items.append(
            {
                "title": item["title"],
                "url": item["output"],
                "source": item["source"],
                "headings": " | ".join(headings[:10]),
                "excerpt": excerpt,
                "content": content,
            }
        )

    return json.dumps(search_items, ensure_ascii=False).replace("</", "<\\/")


def render_markdown(markdown_text: str) -> tuple[str, list[tuple[str, str, int]]]:
    lines = markdown_text.splitlines()
    output: list[str] = []
    headings: list[tuple[str, str, int]] = []
    paragraph_lines: list[str] = []
    list_stack: list[str] = []
    in_code_block = False
    code_language = ""
    code_lines: list[str] = []
    in_blockquote = False
    blockquote_lines: list[str] = []

    def flush_paragraph() -> None:
        nonlocal paragraph_lines
        if paragraph_lines:
            text = " ".join(line.strip() for line in paragraph_lines).strip()
            if text:
                output.append(f"<p>{convert_inline(text)}</p>")
            paragraph_lines = []

    def close_lists() -> None:
        nonlocal list_stack
        flush_paragraph()
        while list_stack:
            output.append(f"</{list_stack.pop()}>")

    def flush_code() -> None:
        nonlocal code_lines, code_language
        language_class = f' class="language-{html.escape(code_language, quote=True)}"' if code_language else ""
        code_html = html.escape("\n".join(code_lines))
        output.append(f"<pre><code{language_class}>{code_html}</code></pre>")
        code_lines = []
        code_language = ""

    def flush_blockquote() -> None:
        nonlocal blockquote_lines, in_blockquote
        if not blockquote_lines:
            in_blockquote = False
            return
        block_text = " ".join(line.strip() for line in blockquote_lines if line.strip())
        output.append(f"<blockquote>{convert_inline(block_text)}</blockquote>")
        blockquote_lines = []
        in_blockquote = False

    for line in lines:
        stripped = line.strip()

        if in_code_block:
            if stripped.startswith("```"):
                flush_code()
                in_code_block = False
            else:
                code_lines.append(line)
            continue

        if stripped.startswith("```"):
            flush_blockquote()
            close_lists()
            flush_paragraph()
            in_code_block = True
            code_language = stripped[3:].strip()
            code_lines = []
            continue

        if stripped.startswith(">"):
            close_lists()
            flush_paragraph()
            in_blockquote = True
            blockquote_lines.append(stripped[1:].strip())
            continue

        if in_blockquote:
            flush_blockquote()

        if not stripped:
            close_lists()
            flush_paragraph()
            continue

        if stripped == "---":
            close_lists()
            flush_paragraph()
            output.append("<hr>")
            continue

        heading_match = re.match(r"^(#{1,6})\s+(.*)$", stripped)
        if heading_match:
            close_lists()
            flush_paragraph()
            level = len(heading_match.group(1))
            heading_text = heading_match.group(2).strip()
            anchor = slugify(heading_text)
            headings.append((heading_text, anchor, level))
            output.append(
                f'<h{level} id="{anchor}">{convert_inline(heading_text)}</h{level}>'
            )
            continue

        image_only_match = re.match(r"^!\[[^\]]*\]\([^)]+\)$", stripped)
        if image_only_match:
            close_lists()
            flush_paragraph()
            output.append(convert_inline(stripped))
            continue

        ordered_match = re.match(r"^(\d+)\.\s+(.*)$", stripped)
        unordered_match = re.match(r"^[-*]\s+(.*)$", stripped)
        if ordered_match or unordered_match:
            flush_paragraph()
            list_type = "ol" if ordered_match else "ul"
            item_text = ordered_match.group(2) if ordered_match else unordered_match.group(1)
            if not list_stack or list_stack[-1] != list_type:
                close_lists()
                output.append(f"<{list_type}>")
                list_stack.append(list_type)
            output.append(f"<li>{convert_inline(item_text)}</li>")
            continue

        paragraph_lines.append(stripped)

    if in_blockquote:
        flush_blockquote()
    if in_code_block:
        flush_code()
    close_lists()
    flush_paragraph()

    return "\n".join(output), headings


def build_sidebar(navigation: Iterable[dict[str, str]], current_output: str) -> str:
    items: list[str] = []
    for item in navigation:
        active = " is-active" if item["output"] == current_output else ""
        items.append(
            "\n".join(
                [
                    f'<a class="nav-link{active}" href="{html.escape(item["output"], quote=True)}">',
                    f'  <span class="nav-label">{html.escape(item["title"])}</span>',
                    f'  <span class="nav-file">{html.escape(item["source"])}</span>',
                    "</a>",
                ]
            )
        )
    return "\n".join(items)


def build_toc(headings: list[tuple[str, str, int]]) -> str:
    relevant = [heading for heading in headings if heading[2] in {2, 3}]
    if not relevant:
        return '<div class="toc-empty">Sem seções internas nesta página.</div>'

    items = []
    for title, anchor, level in relevant:
        css_class = "toc-link toc-sub" if level == 3 else "toc-link"
        items.append(f'<a class="{css_class}" href="#{html.escape(anchor, quote=True)}">{html.escape(title)}</a>')
    return "\n".join(items)


def build_home_hero(navigation: Iterable[dict[str, str]]) -> str:
    nav_by_source = {item["source"]: item for item in navigation}
    featured_sources = [
        "manual_usuario.md",
        "guia_rapido.md",
        "tutoriais.md",
        "integracao.md",
    ]
    quick_links: list[str] = []
    for source in featured_sources:
        item = nav_by_source.get(source)
        if not item:
            continue
        quick_links.append(
            f'<a class="hero-link" href="{html.escape(item["output"], quote=True)}">{html.escape(item["title"])}</a>'
        )

    total_pages = len([item for item in navigation if item["source"] != "README.md"])

    return """<section class="home-hero">
    <div class="hero-copy">
        <span class="hero-kicker">Base Documental Oficial</span>
        <h2>Referência institucional para operação, governança e integração do infodoc-sisged.</h2>
        <p>Este portal consolida a documentação funcional e técnica do sistema em um acervo navegável, padronizado e pronto para consulta por equipes administrativas, operacionais e de tecnologia.</p>
        <div class="hero-links">""" + "".join(quick_links) + """</div>
    </div>
    <div class="hero-stats">
        <div class="hero-stat">
            <strong>""" + str(total_pages) + """</strong>
            <span>documentos publicados para consulta contínua</span>
        </div>
        <div class="hero-stat">
            <strong>3</strong>
            <span>frentes principais: adoção, suporte e integração</span>
        </div>
        <div class="hero-stat">
            <strong>HTML estatico</strong>
            <span>acesso local imediato, sem dependência de stack adicional</span>
        </div>
    </div>
</section>"""


def render_page(*, title: str, content_html: str, sidebar_html: str, toc_html: str, page_label: str, page_class: str, hero_html: str = "", search_index_json: str = "[]") -> str:
    return f"""<!DOCTYPE html>
<html lang=\"pt-BR\">
<head>
  <meta charset=\"UTF-8\">
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
  <title>{html.escape(title)} - infodoc-sisged</title>
    <meta name=\"theme-color\" content=\"#18252b\">
    <link rel=\"icon\" type=\"image/svg+xml\" href=\"{FAVICON_PATH.name}\">
  <link rel=\"stylesheet\" href=\"{CSS_PATH.name}\">
</head>
<body class=\"{html.escape(page_class, quote=True)}\">
  <div class=\"docs-shell\">
    <aside class=\"sidebar\">
      <div class=\"brand\">
        <a href=\"index.html\">infodoc-sisged</a>
                <p>Portal documental para consulta local, apoio operacional e referência técnica.</p>
            </div>
            <div class="sidebar-search">
                <label class="search-label" for="docs-search-input">Busca local</label>
                <input id="docs-search-input" class="search-input" type="search" placeholder="Buscar páginas, tópicos e termos" autocomplete="off">
                <div id="docs-search-results" class="search-results" hidden></div>
      </div>
      <nav class=\"nav-list\">
        {sidebar_html}
      </nav>
    </aside>
    <main class=\"content-area\">
      <header class=\"page-header\">
        <div>
          <span class=\"eyebrow\">{html.escape(page_label)}</span>
          <h1>{html.escape(title)}</h1>
        </div>
      </header>
            {hero_html}
      <div class=\"page-grid\">
        <article class=\"document\">
          {content_html}
        </article>
        <aside class=\"toc\">
          <div class=\"toc-card\">
            <h2>Nesta pagina</h2>
            {toc_html}
          </div>
        </aside>
      </div>
    </main>
  </div>
    <script>window.DOCS_SEARCH_INDEX = {search_index_json};</script>
    <script src="{SEARCH_SCRIPT_PATH.name}"></script>
</body>
</html>
"""


def main() -> None:
    ordered_files = parse_nav_order()
    navigation: list[dict[str, str]] = []

    for file_name in ordered_files:
        source_path = DOCS_DIR / file_name
        markdown_text = source_path.read_text(encoding="utf-8")
        output_name = "index.html" if file_name == "README.md" else source_path.with_suffix(".html").name
        navigation.append(
            {
                "source": file_name,
                "output": output_name,
                "title": get_title(markdown_text, source_path.stem.replace("_", " ").title()),
            }
        )

    search_index_json = build_search_index(navigation)

    for item in navigation:
        source_path = DOCS_DIR / item["source"]
        markdown_text = source_path.read_text(encoding="utf-8")
        content_html, headings = render_markdown(markdown_text)
        page_slug = slugify(item["title"])
        page_class = f"page page-{page_slug}"
        if item["output"] == "index.html":
            page_class += " page-home"
        hero_html = build_home_hero(navigation) if item["output"] == "index.html" else ""
        page_html = render_page(
            title=item["title"],
            content_html=content_html,
            sidebar_html=build_sidebar(navigation, item["output"]),
            toc_html=build_toc(headings),
            page_label=item["source"],
            page_class=page_class,
            hero_html=hero_html,
            search_index_json=search_index_json,
        )
        (DOCS_DIR / item["output"]).write_text(page_html, encoding="utf-8")


if __name__ == "__main__":
    main()