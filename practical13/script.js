"use strict";

const apiUrl = "api.php";
const form = document.getElementById("item-form");
const fields = document.getElementById("item-fields");
const nameInput = document.getElementById("item-name");
const categoryInput = document.getElementById("item-category");
const priceInput = document.getElementById("item-price");
const submitButton = document.getElementById("submit-button");
const cancelEditButton = document.getElementById("cancel-edit");
const formTitle = document.getElementById("form-title");
const successMessage = document.getElementById("success-message");
const errorMessage = document.getElementById("error-message");
const loadingIndicator = document.getElementById("loading-indicator");
const tableError = document.getElementById("table-error");
const emptyState = document.getElementById("empty-state");
const itemsTable = document.getElementById("items-table");
const itemsBody = document.getElementById("items-body");
const menuList = document.getElementById("menu-list");
const menuStatus = document.getElementById("menu-status");
const rolePreview = document.getElementById("preview-role");
const accessNotice = document.getElementById("access-notice");
const accessMessage = document.getElementById("access-message");

let csrfToken = "";
let itemsById = new Map();
let editingId = null;

class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.name = "ApiError";
        this.status = status;
    }
}

function showNotice(element, message) {
    element.textContent = message;
    element.hidden = false;
}

function showAccessNotice(message) {
    accessMessage.textContent = message;
    accessNotice.hidden = false;
}

function clearNotices() {
    successMessage.textContent = "";
    successMessage.hidden = true;
    errorMessage.textContent = "";
    errorMessage.hidden = true;
}

function getErrorMessage(error) {
    return error instanceof Error ? error.message : "An unexpected error occurred.";
}

async function request(action, options = {}) {
    const url = new URL(apiUrl, window.location.href);
    url.searchParams.set("action", action);
    if (options.role) {
        url.searchParams.set("role", options.role);
    }

    const headers = { Accept: "application/json" };
    const requestOptions = {
        method: options.method || "GET",
        credentials: "same-origin",
        headers
    };

    if (options.body) {
        headers["Content-Type"] = "application/json";
        headers["X-CSRF-Token"] = csrfToken;
        requestOptions.body = JSON.stringify(options.body);
    }

    let response;
    try {
        response = await fetch(url, requestOptions);
    } catch {
        throw new Error("The server could not be reached. Check that StudentHub and MySQL are running.");
    }

    let payload;
    try {
        payload = await response.json();
    } catch {
        throw new Error("The server returned an invalid response. Please try again.");
    }

    if (!response.ok || payload.status !== "success") {
        const message = typeof payload.message === "string"
            ? payload.message
            : `The request failed with HTTP ${response.status}.`;
        if (response.status === 401) {
            showAccessNotice(`${message} Use the sign-in link below, then reopen this page.`);
        }
        throw new ApiError(message, response.status);
    }

    return payload;
}

function setLoading(isLoading) {
    loadingIndicator.hidden = !isLoading;
    itemsTable.setAttribute("aria-busy", String(isLoading));
}

function setEditMode(item) {
    editingId = item.id;
    nameInput.value = item.name;
    categoryInput.value = item.category;
    priceInput.value = Number(item.price).toFixed(2);
    formTitle.textContent = `Edit item #${item.id}`;
    submitButton.textContent = "Save changes";
    cancelEditButton.hidden = false;
    clearNotices();
    nameInput.focus();
}

function resetForm() {
    form.reset();
    editingId = null;
    formTitle.textContent = "Add an item";
    submitButton.textContent = "Add item";
    cancelEditButton.hidden = true;
}

function createActionButton(label, action, itemId, className) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = `button ${className}`;
    button.textContent = label;
    button.dataset.action = action;
    button.dataset.id = String(itemId);
    return button;
}

function renderItems(items) {
    itemsById = new Map();
    itemsBody.replaceChildren();

    for (const item of items) {
        if (!item || !Number.isInteger(Number(item.id))) {
            continue;
        }
        const id = Number(item.id);
        itemsById.set(id, item);

        const row = document.createElement("tr");
        const idCell = document.createElement("td");
        const nameCell = document.createElement("td");
        const categoryCell = document.createElement("td");
        const priceCell = document.createElement("td");
        const actionsCell = document.createElement("td");
        const actions = document.createElement("div");

        idCell.textContent = String(id);
        nameCell.className = "item-name";
        nameCell.textContent = String(item.name);
        categoryCell.textContent = String(item.category);
        priceCell.textContent = Number(item.price).toFixed(2);
        actions.className = "actions";
        actions.append(
            createActionButton("Edit", "edit", id, "button-secondary"),
            createActionButton("Delete", "delete", id, "button-danger")
        );
        actionsCell.append(actions);
        row.append(idCell, nameCell, categoryCell, priceCell, actionsCell);
        itemsBody.append(row);
    }

    const hasItems = itemsById.size > 0;
    emptyState.hidden = hasItems;
    itemsTable.hidden = !hasItems;
}

async function loadItems() {
    setLoading(true);
    tableError.hidden = true;
    try {
        const result = await request("read");
        renderItems(Array.isArray(result.data) ? result.data : []);
    } catch (error) {
        itemsTable.hidden = true;
        emptyState.hidden = true;
        showNotice(tableError, getErrorMessage(error));
    } finally {
        setLoading(false);
    }
}

async function loadMenu(role) {
    menuStatus.textContent = "Loading navigation…";
    menuList.replaceChildren();
    try {
        const result = await request("get_menu", { role });
        const entries = Array.isArray(result.data) ? result.data : [];
        for (const entry of entries) {
            if (typeof entry.title !== "string" || typeof entry.link !== "string") {
                continue;
            }

            const target = new URL(entry.link, window.location.href);
            if (target.origin !== window.location.origin) {
                continue;
            }

            const listItem = document.createElement("li");
            const link = document.createElement("a");
            link.href = target.href;
            link.textContent = entry.title;
            listItem.append(link);
            menuList.append(listItem);
        }
        menuStatus.textContent = menuList.childElementCount > 0
            ? `Showing ${role} navigation (preview only).`
            : "No navigation entries are available for this role.";
    } catch (error) {
        menuStatus.textContent = getErrorMessage(error);
    }
}

async function initialize() {
    setLoading(true);
    try {
        const result = await request("csrf");
        if (typeof result.data?.csrf_token !== "string" || result.data.csrf_token === "") {
            throw new Error("The server did not provide a security token.");
        }
        csrfToken = result.data.csrf_token;
        await Promise.all([loadItems(), loadMenu(rolePreview.value)]);
    } catch (error) {
        itemsTable.hidden = true;
        emptyState.hidden = true;
        showNotice(tableError, getErrorMessage(error));
        menuStatus.textContent = getErrorMessage(error);
        setLoading(false);
    }
}

form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearNotices();

    const name = nameInput.value.trim();
    const category = categoryInput.value.trim();
    const price = Number(priceInput.value);

    if (!name || !category || name.length > 100 || category.length > 100) {
        showNotice(errorMessage, "Enter an item name and category (100 characters or fewer).");
        return;
    }
    if (
        !Number.isFinite(price)
        || price <= 0
        || Math.abs(Math.round(price * 100) - price * 100) > 0.00001
    ) {
        showNotice(errorMessage, "Enter a positive price with no more than two decimal places.");
        return;
    }
    if (!form.reportValidity()) {
        return;
    }

    const action = editingId === null ? "create" : "update";
    const body = { name, category, price };
    if (editingId !== null) {
        body.id = editingId;
    }

    fields.disabled = true;
    submitButton.disabled = true;
    cancelEditButton.disabled = true;
    try {
        const result = await request(action, { method: "POST", body });
        showNotice(successMessage, result.message);
        resetForm();
        await loadItems();
    } catch (error) {
        showNotice(errorMessage, getErrorMessage(error));
    } finally {
        fields.disabled = false;
        submitButton.disabled = false;
        cancelEditButton.disabled = false;
    }
});

itemsBody.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action][data-id]");
    if (!button || !itemsBody.contains(button)) {
        return;
    }

    const id = Number(button.dataset.id);
    const item = itemsById.get(id);
    if (!item) {
        showNotice(errorMessage, "That inventory item is no longer available. Refresh the list and try again.");
        return;
    }

    clearNotices();
    if (button.dataset.action === "edit") {
        setEditMode(item);
        return;
    }
    if (button.dataset.action !== "delete") {
        return;
    }
    if (!window.confirm(`Delete "${item.name}" from the inventory? This cannot be undone.`)) {
        return;
    }

    button.disabled = true;
    try {
        const result = await request("delete", { method: "POST", body: { id } });
        showNotice(successMessage, result.message);
        if (editingId === id) {
            resetForm();
        }
        await loadItems();
    } catch (error) {
        button.disabled = false;
        showNotice(errorMessage, getErrorMessage(error));
    }
});

cancelEditButton.addEventListener("click", () => {
    resetForm();
    clearNotices();
});

rolePreview.addEventListener("change", () => {
    loadMenu(rolePreview.value);
});

initialize();
