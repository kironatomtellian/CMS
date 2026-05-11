/**
 * Kiron CMS — single-file client app.
 *
 * Reads { key, schema, data, mtime } from #editor-data, renders a form, and
 * lets you save it back. Schema shape is documented in inc/schemas.php.
 */
(function () {
  "use strict";

  const base = (window.CMS && window.CMS.base) || "";

  // --- DOM helpers ---------------------------------------------------------

  function el(tag, attrs, children) {
    const node = document.createElement(tag);
    if (attrs) {
      for (const k in attrs) {
        const v = attrs[k];
        if (v == null || v === false) continue;
        if (k === "class") node.className = v;
        else if (k === "html") node.innerHTML = v;
        else if (k === "text") node.textContent = v;
        else if (k.startsWith("on") && typeof v === "function") node.addEventListener(k.slice(2), v);
        else if (k.startsWith("data-") || k === "for" || k === "type" || k === "name") node.setAttribute(k, v);
        else node[k] = v;
      }
    }
    if (children) {
      (Array.isArray(children) ? children : [children]).forEach((c) => {
        if (c == null) return;
        node.append(c.nodeType ? c : document.createTextNode(String(c)));
      });
    }
    return node;
  }

  function api(path, opts) {
    return fetch(base + path, opts).then(async (r) => {
      let body = null;
      try { body = await r.json(); } catch (e) {}
      if (!r.ok || (body && body.ok === false)) {
        const err = new Error((body && body.error) || r.statusText || "Request failed");
        err.body = body;
        err.status = r.status;
        throw err;
      }
      return body || {};
    });
  }

  // --- Toast ---------------------------------------------------------------

  const toastEl = document.getElementById("toast");
  let toastTimer = null;

  function toast(msg, kind) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.className = "toast toast--show" + (kind ? " toast--" + kind : "");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toastEl.className = "toast";
    }, 3200);
  }

  // --- Path helpers --------------------------------------------------------

  function getAt(obj, path) {
    let cur = obj;
    for (const seg of path) {
      if (cur == null) return undefined;
      cur = cur[seg];
    }
    return cur;
  }

  // --- Form rendering ------------------------------------------------------

  // Each rendered field stores a "read()" function on its DOM node. The
  // top-level submit walks the tree and calls them to produce the JSON.

  function renderField(field, value, onDirty) {
    if (field.type === "object") return renderObject(field, value, onDirty);
    if (field.type === "list") return renderList(field, value, onDirty);
    if (field.type === "image") return renderImage(field, value, onDirty);
    if (field.type === "select") return renderSelect(field, value, onDirty);
    if (field.type === "textarea") return renderTextarea(field, value, onDirty);
    if (field.type === "date") return renderText(field, value, onDirty, { inputType: "date" });
    return renderText(field, value, onDirty, {});
  }

  function renderText(field, value, onDirty, opts) {
    const input = el("input", {
      class: "field__input",
      type: opts.inputType || "text",
      value: value == null ? "" : String(value),
      placeholder: field.placeholder || "",
      oninput: onDirty,
    });
    return wrapField(field, input, () => coerce(field, input.value));
  }

  function renderTextarea(field, value, onDirty) {
    const ta = el("textarea", {
      class: "field__textarea",
      rows: field.rows || 4,
      placeholder: field.placeholder || "",
      oninput: onDirty,
    });
    ta.value = value == null ? "" : String(value);
    return wrapField(field, ta, () => coerce(field, ta.value));
  }

  function renderSelect(field, value, onDirty) {
    const sel = el("select", { class: "field__select", onchange: onDirty });
    (field.options || []).forEach(([v, label]) => {
      const o = el("option", { value: v, text: label });
      if (String(value ?? "") === String(v)) o.selected = true;
      sel.append(o);
    });
    return wrapField(field, sel, () => coerce(field, sel.value));
  }

  function renderImage(field, value, onDirty) {
    const pathInput = el("input", {
      class: "field__input image__input",
      type: "text",
      value: value == null ? "" : String(value),
      placeholder: "/img/...",
      oninput: () => { updatePreview(); onDirty(); },
    });
    const preview = el("div", { class: "image__preview" }, "no image");
    const file = el("input", { type: "file", accept: "image/*" });
    const progress = el("span", { class: "image__progress" });
    const uploadBtn = el("button", {
      class: "btn btn--small",
      type: "button",
      onclick: () => file.click(),
    }, "Upload…");
    const clearBtn = el("button", {
      class: "btn btn--small btn--ghost",
      type: "button",
      onclick: () => { pathInput.value = ""; updatePreview(); onDirty(); },
    }, "Clear");

    file.addEventListener("change", async () => {
      const f = file.files && file.files[0];
      if (!f) return;
      const form = new FormData();
      form.append("file", f);
      progress.textContent = "Uploading " + f.name + "…";
      try {
        const r = await api("/api/upload", { method: "POST", body: form });
        pathInput.value = r.path;
        updatePreview();
        onDirty();
        progress.textContent = "Uploaded.";
        toast("Image uploaded.", "ok");
        setTimeout(() => { progress.textContent = ""; }, 2400);
      } catch (e) {
        progress.textContent = "";
        toast("Upload failed: " + e.message, "err");
      } finally {
        file.value = "";
      }
    });

    function updatePreview() {
      const p = pathInput.value.trim();
      if (!p) {
        preview.style.backgroundImage = "";
        preview.textContent = "no image";
      } else {
        preview.textContent = "";
        preview.style.backgroundImage = "url(" + JSON.stringify(p).slice(1, -1) + ")";
      }
    }
    updatePreview();

    const body = el("div", { class: "image__body" }, [
      pathInput,
      el("div", { class: "image__row" }, [uploadBtn, clearBtn, progress, file]),
    ]);
    // hide native file input
    file.style.display = "none";

    const wrap = el("div", { class: "image" }, [preview, body]);
    return wrapField(field, wrap, () => coerce(field, pathInput.value));
  }

  function wrapField(field, control, read) {
    const labelText = field.label || field.key || "";
    const cls = "field" + (field.optional ? " field--optional" : "");
    const lbl = el("label", { class: cls }, [
      el("span", { class: "field__label", text: labelText }),
      control,
      field.help ? el("span", { class: "field__help", text: field.help }) : null,
    ]);
    lbl._read = read;
    return lbl;
  }

  function renderObject(field, value, onDirty) {
    const obj = (value && typeof value === "object" && !Array.isArray(value)) ? value : {};
    const inner = el("div");
    const reads = [];
    (field.fields || []).forEach((sub) => {
      const sv = obj[sub.key];
      const node = renderField(sub, sv, onDirty);
      reads.push({ key: sub.key, node });
      inner.append(node);
    });
    const group = el("div", { class: "group" }, [
      field.label ? el("h3", { class: "group__title", text: field.label }) : null,
      inner,
    ]);
    group._read = () => {
      const out = {};
      reads.forEach(({ key, node }) => {
        const v = node._read();
        if (v === undefined) return;
        out[key] = v;
      });
      return out;
    };
    return group;
  }

  function renderList(field, value, onDirty) {
    const items = Array.isArray(value) ? value.slice() : [];
    const itemSpec = field.item || {};

    // root container
    const listBox = el("div", { class: "list" });
    const empty = el("div", { class: "list--empty", text: "Nothing yet — click Add below." });

    const itemNodes = []; // { node, read, kind? }

    function refreshLabels() {
      itemNodes.forEach(({ node }, i) => {
        const head = node.querySelector(".list__item-head strong");
        if (head) head.textContent = (field.item_label || "Item") + " " + (i + 1);
      });
      if (itemNodes.length === 0 && listBox.contains(empty) === false) {
        listBox.prepend(empty);
      } else if (itemNodes.length > 0 && listBox.contains(empty)) {
        empty.remove();
      }
    }

    function addItem(initialValue, initialKind) {
      const entry = buildListItem(field, itemSpec, initialValue, initialKind, onDirty, {
        remove() {
          const idx = itemNodes.indexOf(entry);
          if (idx > -1) {
            itemNodes.splice(idx, 1);
            entry.node.remove();
            refreshLabels();
            onDirty();
          }
        },
        move(dir) {
          const idx = itemNodes.indexOf(entry);
          const j = idx + dir;
          if (idx < 0 || j < 0 || j >= itemNodes.length) return;
          const other = itemNodes[j];
          itemNodes[idx] = other;
          itemNodes[j] = entry;
          if (dir < 0) listBox.insertBefore(entry.node, other.node);
          else listBox.insertBefore(other.node, entry.node);
          refreshLabels();
          onDirty();
        },
      });
      itemNodes.push(entry);
      const addRow = listBox.querySelector(".list__add");
      if (addRow) listBox.insertBefore(entry.node, addRow);
      else listBox.append(entry.node);
      refreshLabels();
    }

    // Add bar
    let addRow;
    if (itemSpec.one_of) {
      const buttons = Object.entries(itemSpec.one_of).map(([kind, def]) =>
        el("button", {
          class: "btn btn--small",
          type: "button",
          onclick: () => { addItem(undefined, kind); onDirty(); },
        }, "+ " + (def.label || kind))
      );
      addRow = el("div", { class: "list__add" }, buttons);
    } else {
      addRow = el("div", { class: "list__add" }, [
        el("button", {
          class: "btn btn--small",
          type: "button",
          onclick: () => { addItem(undefined); onDirty(); },
        }, "+ Add " + (field.item_label || "item").toLowerCase()),
      ]);
    }

    // Seed existing items
    items.forEach((v) => {
      let kind;
      if (itemSpec.one_of && v && typeof v === "object" && v.kind && itemSpec.one_of[v.kind]) {
        kind = v.kind;
      } else if (itemSpec.one_of) {
        kind = Object.keys(itemSpec.one_of)[0];
      }
      addItem(v, kind);
    });
    listBox.append(addRow);
    refreshLabels();

    const wrap = el("div", { class: "field" }, [
      field.label ? el("span", { class: "field__label", text: field.label }) : null,
      listBox,
      field.help ? el("span", { class: "field__help", text: field.help }) : null,
    ]);
    wrap._read = () => itemNodes.map((e) => e.read());
    return wrap;
  }

  function buildListItem(parentField, itemSpec, initialValue, kind, onDirty, hooks) {
    const head = el("div", { class: "list__item-head" }, [
      el("strong", { text: (parentField.item_label || "Item") }),
      el("div", { class: "list__item-actions" }, [
        el("button", { class: "icon-btn", type: "button", title: "Move up", "data-act": "up", onclick: () => hooks.move(-1) }, "↑"),
        el("button", { class: "icon-btn", type: "button", title: "Move down", "data-act": "down", onclick: () => hooks.move(1) }, "↓"),
        el("button", { class: "icon-btn", type: "button", title: "Remove", "data-act": "remove", onclick: () => {
          if (confirm("Remove this item?")) hooks.remove();
        } }, "×"),
      ]),
    ]);

    const body = el("div", { class: "list__item-body" });

    let read;
    if (itemSpec.one_of) {
      const def = itemSpec.one_of[kind];
      const kindLabel = el("p", { class: "muted", style: "margin: 0 0 12px; font-size: 12px;", text: def.label });
      body.append(kindLabel);
      const reads = [];
      (def.fields || []).forEach((sub) => {
        const sv = initialValue && typeof initialValue === "object" ? initialValue[sub.key] : undefined;
        const node = renderField(sub, sv, onDirty);
        reads.push({ key: sub.key, node });
        body.append(node);
      });
      read = () => {
        const out = { kind };
        reads.forEach(({ key, node }) => {
          const v = node._read();
          if (v === undefined) return;
          out[key] = v;
        });
        return out;
      };
    } else if (itemSpec.scalar) {
      // Single-field scalar item — value stored directly, not wrapped in an object.
      const sub = itemSpec.fields[0];
      const node = renderField(sub, initialValue, onDirty);
      body.append(node);
      read = () => node._read();
    } else {
      const reads = [];
      (itemSpec.fields || []).forEach((sub) => {
        const sv = initialValue && typeof initialValue === "object" ? initialValue[sub.key] : undefined;
        const node = renderField(sub, sv, onDirty);
        reads.push({ key: sub.key, node });
        body.append(node);
      });
      read = () => {
        const out = {};
        reads.forEach(({ key, node }) => {
          const v = node._read();
          if (v === undefined) return;
          out[key] = v;
        });
        return out;
      };
    }

    const node = el("div", { class: "list__item" }, [head, body]);
    return { node, read };
  }

  // --- Coercion ------------------------------------------------------------

  function coerce(field, raw) {
    if (raw == null) raw = "";
    if (typeof raw === "string") raw = raw.trim();
    if (raw === "") {
      // Empty fields are null for optional / select-with-blank, otherwise ""
      if (field.optional || (field.type === "select" && hasBlankOption(field))) return null;
      return "";
    }
    if (field.type === "select") {
      // Find the value's "true" type from the options (numeric strings stay strings here).
      return raw;
    }
    return raw;
  }

  function hasBlankOption(field) {
    return (field.options || []).some((opt) => opt[0] === "" || opt[0] === null);
  }

  // --- Top-level editor wiring --------------------------------------------

  const dataNode = document.getElementById("editor-data");
  const editorBody = document.querySelector("[data-editor-body]");
  const editorRoot = document.querySelector("[data-editor]");
  const statusNode = document.querySelector("[data-status]");
  const saveBtn = document.querySelector('[data-action="save"]');
  let rootRead = null;
  let dirty = false;
  let mtime = 0;
  let key = "";

  function setStatus(text, kind) {
    if (!statusNode) return;
    statusNode.className = "panel__meta" + (kind ? " status-" + kind : "");
    statusNode.textContent = text;
  }

  function markDirty() {
    if (!dirty) {
      dirty = true;
      setStatus("Unsaved changes", "dirty");
    }
  }

  function readForm() {
    const out = rootRead();
    return out;
  }

  if (dataNode && editorBody) {
    let payload;
    try {
      payload = JSON.parse(dataNode.textContent);
    } catch (e) {
      editorBody.innerHTML = '<p class="muted">Could not parse editor data.</p>';
      return;
    }
    key = payload.key;
    mtime = payload.mtime;
    editorBody.innerHTML = "";

    if (payload.schema.root_is_list) {
      const listField = {
        type: "list",
        key: "",
        label: null,
        item_label: payload.schema.list.item_label,
        item: payload.schema.list.item,
      };
      const node = renderList(listField, payload.data, markDirty);
      editorBody.append(node);
      rootRead = () => node._read();
    } else {
      const reads = [];
      (payload.schema.fields || []).forEach((field) => {
        const node = renderField(field, payload.data ? payload.data[field.key] : undefined, markDirty);
        reads.push({ key: field.key, node });
        editorBody.append(node);
      });
      rootRead = () => {
        const out = {};
        reads.forEach(({ key, node }) => {
          const v = node._read();
          if (v === undefined) return;
          out[key] = v;
        });
        return out;
      };
    }

    setStatus("Loaded.");
  }

  if (saveBtn) {
    saveBtn.addEventListener("click", async () => {
      if (!rootRead) return;
      const data = readForm();
      saveBtn.disabled = true;
      saveBtn.classList.add("btn--busy");
      setStatus("Saving…", "saving");
      try {
        const r = await api("/api/save", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ key, data }),
        });
        dirty = false;
        mtime = r.mtime;
        setStatus("Saved · " + new Date(mtime * 1000).toLocaleTimeString(), "saved");
        toast("Saved.", "ok");
      } catch (e) {
        setStatus("Save failed: " + e.message, "err");
        toast("Save failed: " + e.message, "err");
      } finally {
        saveBtn.disabled = false;
        saveBtn.classList.remove("btn--busy");
      }
    });
  }

  // Warn on unload when dirty
  window.addEventListener("beforeunload", (e) => {
    if (dirty) {
      e.preventDefault();
      e.returnValue = "";
    }
  });

  // --- Publish -------------------------------------------------------------

  const publishBtn = document.getElementById("publishBtn");
  if (publishBtn) {
    publishBtn.addEventListener("click", async () => {
      if (dirty) {
        if (!confirm("You have unsaved changes that won't be published. Continue anyway?")) return;
      }
      const dlg = openPublishDialog();
      try {
        const r = await api("/api/publish", { method: "POST" });
        dlg.complete(r.log || []);
      } catch (e) {
        dlg.fail(e.message, (e.body && e.body.log) || []);
      }
    });
  }

  function openPublishDialog() {
    const log = el("pre", { text: "Starting publish…\n" });
    const closeBtn = el("button", { class: "btn", type: "button", text: "Close", disabled: true, onclick: close });

    const dialog = el("div", { class: "dialog" }, [
      el("div", { class: "dialog__head" }, [el("h2", { class: "dialog__title", text: "Publishing" })]),
      el("div", { class: "dialog__body" }, [log]),
      el("div", { class: "dialog__foot" }, [closeBtn]),
    ]);
    const backdrop = el("div", { class: "dialog-backdrop" }, [dialog]);
    document.body.append(backdrop);
    requestAnimationFrame(() => backdrop.classList.add("dialog-backdrop--show"));

    function close() {
      backdrop.classList.remove("dialog-backdrop--show");
      setTimeout(() => backdrop.remove(), 200);
    }

    return {
      complete(lines) {
        log.textContent = (lines || []).join("\n") + "\n\n✓ Published.";
        closeBtn.disabled = false;
        closeBtn.classList.add("btn--primary");
        toast("Published to website.", "ok");
      },
      fail(msg, lines) {
        log.textContent = (lines || []).join("\n") + "\n\n✗ " + msg;
        closeBtn.disabled = false;
        closeBtn.classList.add("btn--danger");
        toast("Publish failed.", "err");
      },
    };
  }
})();
